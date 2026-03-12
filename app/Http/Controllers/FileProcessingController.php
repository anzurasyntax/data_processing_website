<?php

namespace App\Http\Controllers;

use App\Models\UploadedFile;
use App\Services\DatasetVersionService;
use App\Services\PythonProcessingService;
use App\Services\UploadedFileService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FileProcessingController extends Controller
{
    public function __construct(
        private UploadedFileService $fileService,
        private PythonProcessingService $pythonService,
        private DatasetVersionService $versionService
    ) {}

    public function index(): Factory|View
    {
        $files = UploadedFile::where('user_id', Auth::id())
            ->latest()
            ->get();
        return view('files.index', compact('files'));
    }

    public function show(string $slug): View|Factory|RedirectResponse

    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $result = $this->pythonService->process('process_file.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
            ]);

            return view('files.preview', compact('file', 'result'));
        } catch (\Exception $e) {
            return redirect()->route('files.list')
                ->with('error', 'Failed to process file: ' . $e->getMessage());
        }
    }

    public function updateCell(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'row_index' => 'required|integer|min:0',
            'column' => 'required|string',
            'value' => 'nullable|string'
        ]);

        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $result = $this->pythonService->process('update_cell.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
                'row_index' => $validated['row_index'],
                'column' => $validated['column'],
                'value' => $validated['value']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cell updated successfully',
                'column_stats' => $result['column_stats'],
                'total_duplicate_rows' => $result['total_duplicate_rows'],
                'outlier_map' => $result['outlier_map'],
                'updated_value' => $result['updated_value']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function cleanData(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'operations' => 'required|array',
            'operations.*.type' => 'required|string',
            'operations.*.method' => 'nullable|string',
            'operations.*.column' => 'nullable|string',
            'operations.*.columns' => 'nullable|array',
            'operations.*.value' => 'nullable',
            'operations.*.lower_percentile' => 'nullable|numeric',
            'operations.*.upper_percentile' => 'nullable|numeric',
        ]);

        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            // Create a new version file from latest and clean that version in-place
            $operationsSummary = [
                'operations' => array_map(
                    fn (array $op) => $op['type'] . (isset($op['column']) ? ' (' . $op['column'] . ')' : ''),
                    $validated['operations']
                ),
            ];

            $newVersion = $this->versionService->createVersionFromExisting($file, $operationsSummary);

            $result = $this->pythonService->process('clean_data.py', [
                'file_type' => $file->file_type,
                'file_path' => storage_path('app/public/' . $newVersion->file_path),
                'operations' => $validated['operations'],
            ]);

            $newVersion->update([
                'rows_count' => $result['cleaned_rows'] ?? $newVersion->rows_count,
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Data cleaned successfully',
                'original_rows' => $result['original_rows'],
                'cleaned_rows' => $result['cleaned_rows'],
                'rows_removed' => $result['rows_removed'],
                'applied_operations' => $result['applied_operations']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function qualityCheck(string $slug): JsonResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $result = $this->pythonService->process('quality_check.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
            ]);

            return response()->json([
                'success' => true,
                'quality_score' => $result['quality_score'],
                'is_clean' => $result['is_clean'],
                'total_rows' => $result['total_rows'],
                'total_columns' => $result['total_columns'],
                'total_missing' => $result['total_missing'],
                'total_duplicate_rows' => $result['total_duplicate_rows'],
                'total_outliers' => $result['total_outliers'],
                'issues' => $result['issues'],
                'issues_by_type' => $result['issues_by_type'],
                'column_quality' => $result['column_quality']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function visualize(string $slug): View|Factory|RedirectResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());
            return view('files.visualize', compact('file'));
        } catch (\Exception $e) {
            return redirect()->route('files.list')
                ->with('error', 'File not found: ' . $e->getMessage());
        }
    }

    public function visualizeData(string $slug): JsonResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $result = $this->pythonService->process('visualize_data.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function visualizeSuggestions(string $slug): JsonResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $result = $this->pythonService->process('visualize_suggestions.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'columns' => [],
                'suggested_charts' => [],
                'suggested_correlation_columns' => [],
                'suggested_regression_pairs' => []
            ], 500);
        }
    }

    public function visualizeBuild(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'charts' => 'nullable|array',
            'charts.*.type' => 'required|string',
            'charts.*.column' => 'nullable|string',
            'charts.*.x_column' => 'nullable|string',
            'charts.*.y_column' => 'nullable|string',
            'correlation_columns' => 'nullable|array',
            'correlation_columns.*' => 'string',
            'regression' => 'nullable|array',
            'regression.x_column' => 'nullable|string',
            'regression.y_column' => 'nullable|string',
        ]);

        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $result = $this->pythonService->process('visualize_build.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
                'charts' => $validated['charts'] ?? [],
                'correlation_columns' => $validated['correlation_columns'] ?? [],
                'regression' => $validated['regression'] ?? [],
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'charts' => [],
                'correlation' => null,
                'regression' => null
            ], 500);
        }
    }

    public function insightStrategy(string $slug): View|Factory|RedirectResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());
            return view('files.insight_strategy', compact('file'));
        } catch (\Exception $e) {
            return redirect()->route('files.list')
                ->with('error', 'File not found: ' . $e->getMessage());
        }
    }

    public function insightStrategyData(string $slug): JsonResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $result = $this->pythonService->process('insight_strategy_engine.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'key_findings' => [],
                'risks' => [],
                'strategies' => []
            ], 500);
        }
    }

    public function versions(string $slug): JsonResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());
            $versions = $this->versionService->listVersions($file)->map(function ($v) {
                return [
                    'version_number' => $v->version_number,
                    'rows_count' => $v->rows_count,
                    'columns_count' => $v->columns_count,
                    'created_at' => $v->created_at,
                    'operations' => $v->operations_json['operations'] ?? null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Versions fetched successfully',
                'data' => $versions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch versions',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function revert(string $slug, int $version): JsonResponse
    {
        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());
            $newVersion = $this->versionService->revertToVersion($file, $version);

            return response()->json([
                'status' => 'success',
                'message' => "Reverted to version {$version}",
                'data' => [
                    'new_version_number' => $newVersion->version_number,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to revert version',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function export(string $slug, int $version, string $format)
    {
        $format = strtolower($format);
        $allowed = ['csv', 'xlsx', 'json', 'parquet'];

        if (! in_array($format, $allowed, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unsupported export format',
                'data' => ['allowed' => $allowed],
            ], 422);
        }

        try {
            $file = $this->fileService->findForUserBySlug($slug, (int) Auth::id());

            $versionPath = $this->versionService->absolutePathForVersion($file, $version);
            $sourceType = $file->file_type;

            $ext = pathinfo($versionPath, PATHINFO_EXTENSION);
            if (strtolower($ext) === $format) {
                return response()->download($versionPath);
            }

            $relativeDir = 'exports';
            $baseName = pathinfo($versionPath, PATHINFO_FILENAME);
            $exportRelative = $relativeDir . '/' . $baseName . '-v' . $version . '.' . $format;
            $exportAbsolute = storage_path('app/public/' . $exportRelative);

            $this->pythonService->process('export_dataset.py', [
                'file_type' => $sourceType,
                'file_path' => $versionPath,
                'target_format' => $format,
                'output_path' => $exportAbsolute,
            ]);

            return response()->download($exportAbsolute);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export dataset',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
