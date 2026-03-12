<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUploadedFileRequest;
use App\Jobs\ProcessUploadedFileJob;
use App\Models\UploadedFile;
use App\Services\DatasetVersionService;
use App\Services\UploadedFileService;
use App\Services\PythonProcessingService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UploadedFileController extends Controller
{
    public function __construct(
        private UploadedFileService $service,
        private PythonProcessingService $pythonService,
        private DatasetVersionService $versionService
    ) {}

    public function dashboard(): Factory|View
    {
        $userId = (int) Auth::id();

        $totalFiles = UploadedFile::where('user_id', $userId)->count();
        $totalSizeBytes = UploadedFile::where('user_id', $userId)->sum('file_size');

        $recentFiles = UploadedFile::where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get();

        $qualitySummaries = [];
        $qualityScores = [];

        foreach ($recentFiles as $file) {
            $score = $file->quality_score;
            $qualitySummaries[$file->id] = [
                'score' => $score,
                'is_clean' => null, // only available in full Python result; keep null
                'processing_status' => $file->processing_status ?? 'pending',
            ];
            if ($score !== null) {
                $qualityScores[] = $score;
            }
        }

        $averageQuality = count($qualityScores) > 0
            ? round(array_sum($qualityScores) / count($qualityScores))
            : null;

        $processingCounts = [
            'pending' => UploadedFile::where('user_id', $userId)->where('processing_status', 'pending')->count(),
            'processing' => UploadedFile::where('user_id', $userId)->where('processing_status', 'processing')->count(),
            'completed' => UploadedFile::where('user_id', $userId)->where('processing_status', 'completed')->count(),
            'failed' => UploadedFile::where('user_id', $userId)->where('processing_status', 'failed')->count(),
        ];

        return view('files.dashboard', [
            'totalFiles' => $totalFiles,
            'totalSizeBytes' => $totalSizeBytes,
            'recentFiles' => $recentFiles,
            'qualitySummaries' => $qualitySummaries,
            'averageQuality' => $averageQuality,
            'processingCounts' => $processingCounts,
        ]);
    }

    public function index(): Factory|View
    {
        $files = UploadedFile::where('user_id', Auth::id())
            ->latest()
            ->get();
        return view('files.create', compact('files'));
    }

    public function store(StoreUploadedFileRequest $request): RedirectResponse
    {
        Log::info('File upload start', [
            'user_id' => Auth::id(),
            'file_type' => $request->file_type,
        ]);

        $file = $this->service->store($request->file_type, $request->file('file'), (int) Auth::id());

        Log::info('File upload success', [
            'user_id' => Auth::id(),
            'file_slug' => $file->slug,
            'file_size' => $file->file_size,
        ]);

        // Phase 2: queue heavy processing instead of blocking request
        $file->update([
            'processing_status' => 'pending',
        ]);

        // Create initial dataset version pointing at original file
        $this->versionService->createInitialVersion($file);

        ProcessUploadedFileJob::dispatch($file->id);

        return redirect()->route('files.quality', $file)
            ->with('success', 'File uploaded. Dataset is currently being analyzed...');
    }

    public function quality(string $slug): Factory|View|RedirectResponse
    {
        try {
            $file = $this->service->findForUserBySlug($slug, (int) Auth::id());

            // Prefer cached result from background job
            $qualityResult = Cache::get("quality_result:{$file->id}");

            // If processing isn't completed yet, render page with polling message
            if (in_array($file->processing_status, ['pending', 'processing'], true)) {
                return view('files.quality', compact('file', 'qualityResult'));
            }

            // If completed but cache missing, fall back to synchronous generation (compat)
            if (!$qualityResult && $file->processing_status === 'completed') {
                $qualityResult = $this->pythonService->process('quality_check.py', [
                    'file_type' => $file->file_type,
                    'file_path' => $this->versionService->latestAbsolutePath($file),
                ]);
                Cache::put("quality_result:{$file->id}", $qualityResult, now()->addDay());
            }

            if ($qualityResult && (!isset($qualityResult['quality_score']) || !isset($qualityResult['total_rows']))) {
                throw new \Exception('Invalid quality check result');
            }

            return view('files.quality', compact('file', 'qualityResult'));
        } catch (\Exception $e) {
            $file = $this->service->findForUserBySlug($slug, (int) Auth::id());
            $errorMessage = 'Failed to check file quality: ' . $e->getMessage();


            return view('files.quality', [
                'file' => $file,
                'error' => $errorMessage,
                'qualityResult' => null
            ]);
        }
    }

    public function status(string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            $file = $this->service->findForUserBySlug($slug, (int) Auth::id());

            return response()->json([
                'status' => 'success',
                'message' => 'Status fetched successfully',
                'data' => [
                    'processing_status' => $file->processing_status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch status',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function destroy(string $slug): RedirectResponse
    {
        try {
            $file = $this->service->findForUserBySlug($slug, (int) Auth::id());
            $filePath = storage_path("app/public/{$file->file_path}");
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $file->delete();

            return redirect()->route('files.upload')
                ->with('success', 'File deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('files.upload')
                ->with('error', 'Failed to delete file: ' . $e->getMessage());
        }
    }
}
