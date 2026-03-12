<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileDetailResource;
use App\Http\Resources\FilesResource;
use App\Jobs\ProcessUploadedFileJob;
use App\Models\UploadedFile;
use App\Services\DatasetVersionService;
use App\Services\PythonProcessingService;
use Illuminate\Http\Request;
use App\Services\UploadedFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UploadedFileController extends Controller
{
    protected UploadedFileService $fileService;
    protected PythonProcessingService $pythonService;
    protected DatasetVersionService $versionService;

    public function __construct(
        UploadedFileService $fileService,
        PythonProcessingService $pythonService,
        DatasetVersionService $versionService
    )
    {
        $this->fileService   = $fileService;
        $this->pythonService = $pythonService;
        $this->versionService = $versionService;
    }


    public function index(): AnonymousResourceCollection
    {
        $files = UploadedFile::all();
        return FilesResource::collection($files);
    }

    public function upload(Request $request): JsonResponse
    {
        Log::info('API file upload start');

        // Validate request
        $validator = Validator::make($request->all(), [
            'file_type' => 'required|string|in:txt,csv,xml,xlsx',
            'file' => 'required|file|mimes:csv,txt,xml,xlsx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $uploadedFile = $this->fileService->store(
            $request->input('file_type'),
            $request->file('file')
        );

        Log::info('API file upload success', [
            'file_id' => $uploadedFile->id,
            'file_slug' => $uploadedFile->slug,
        ]);

        // Phase 2: queue processing instead of blocking API call
        $uploadedFile->update([
            'processing_status' => 'pending',
        ]);

        // Ensure initial version exists
        $this->versionService->createInitialVersion($uploadedFile);
        ProcessUploadedFileJob::dispatch($uploadedFile->id);

        return response()->json([
            'status' => 'success',
            'message' => 'File uploaded successfully. Dataset is currently being analyzed...',
            'data' => [
                'id' => $uploadedFile->id,
                'original_name' => $uploadedFile->original_name,
                'file_type' => $uploadedFile->file_type,
                'file_path' => $uploadedFile->file_path,
                'mime_type' => $uploadedFile->mime_type,
                'file_size' => $uploadedFile->file_size,
                'rows_count' => $uploadedFile->rows_count,
                'columns_count' => $uploadedFile->columns_count,
                'dataset_size' => $uploadedFile->dataset_size,
                'quality_score' => $uploadedFile->quality_score,
                'processing_status' => $uploadedFile->processing_status,
                'quality_check' => null,
            ],
        ]);
    }


    public function show(int $id): FileDetailResource|JsonResponse
    {
        try {
            $file = $this->fileService->find($id);

            if (!$file) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'File not found',
                    'data' => null,
                ], 404);
            }

            $result = $this->pythonService->process('process_file.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
            ]);

            $resource = new FileDetailResource([
                'file'   => $file,
                'result' => $result
            ]);

            return $resource->additional([
                'status' => 'success',
                'message' => 'File processed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process file',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $file = $this->fileService->find($id);

            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found',
                    'data' => null,
                ], 404);
            }

            // Delete physical file
            $filePath = storage_path("app/public/{$file->file_path}");
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete database record
            $file->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'File deleted successfully',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete file',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function qualityCheck(int $id): JsonResponse
    {
        try {
            $file = $this->fileService->find($id);

            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found',
                    'data' => null,
                ], 404);
            }

            $result = $this->pythonService->process('quality_check.py', [
                'file_type' => $file->file_type,
                'file_path' => $this->versionService->latestAbsolutePath($file),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Quality check completed',
                'data' => [
                    'quality_score' => $result['quality_score'],
                    'is_clean' => $result['is_clean'],
                    'total_rows' => $result['total_rows'],
                    'total_columns' => $result['total_columns'],
                    'total_missing' => $result['total_missing'],
                    'total_duplicate_rows' => $result['total_duplicate_rows'],
                    'total_outliers' => $result['total_outliers'],
                    'issues' => $result['issues'],
                    'issues_by_type' => $result['issues_by_type'],
                    'column_quality' => $result['column_quality'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check file quality',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function cleanData(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'operations' => 'required|array',
            'operations.*.type' => 'required|string',
            'operations.*.method' => 'nullable|string',
            'operations.*.column' => 'nullable|string',
            'operations.*.columns' => 'nullable|array',
            'operations.*.value' => 'nullable',
            'operations.*.lower_percentile' => 'nullable|numeric',
            'operations.*.upper_percentile' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        try {
            $file = $this->fileService->find($id);

            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found',
                    'data' => null,
                ], 404);
            }

            $operations = $request->input('operations');

            $operationsSummary = [
                'operations' => array_map(
                    fn (array $op) => $op['type'] . (isset($op['column']) ? ' (' . $op['column'] . ')' : ''),
                    $operations
                ),
            ];

            $newVersion = $this->versionService->createVersionFromExisting($file, $operationsSummary);

            $result = $this->pythonService->process('clean_data.py', [
                'file_type' => $file->file_type,
                'file_path' => storage_path('app/public/' . $newVersion->file_path),
                'operations' => $operations,
            ]);

            $newVersion->update([
                'rows_count' => $result['cleaned_rows'] ?? $newVersion->rows_count,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $result['message'] ?? 'Data cleaned successfully',
                'data' => [
                    'original_rows' => $result['original_rows'],
                    'cleaned_rows' => $result['cleaned_rows'],
                    'rows_removed' => $result['rows_removed'],
                    'applied_operations' => $result['applied_operations'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clean data',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
