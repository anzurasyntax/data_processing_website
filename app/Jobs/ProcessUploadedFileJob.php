<?php

namespace App\Jobs;

use App\Models\UploadedFile;
use App\Services\DatasetVersionService;
use App\Services\PythonProcessingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessUploadedFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $uploadedFileId)
    {
    }

    public function handle(PythonProcessingService $pythonService, DatasetVersionService $versionService): void
    {
        $file = UploadedFile::find($this->uploadedFileId);
        if (! $file) {
            Log::error('ProcessUploadedFileJob: file not found', [
                'uploaded_file_id' => $this->uploadedFileId,
            ]);
            return;
        }

        $file->update([
            'processing_status' => 'processing',
        ]);

        $absolutePath = $versionService->latestAbsolutePath($file);
        if (! file_exists($absolutePath)) {
            $file->update([
                'processing_status' => 'failed',
            ]);
            Log::error('ProcessUploadedFileJob: physical file missing', [
                'uploaded_file_id' => $file->id,
                'file_path' => $absolutePath,
            ]);
            return;
        }

        Log::info('ProcessUploadedFileJob: python quality_check start', [
            'uploaded_file_id' => $file->id,
            'slug' => $file->slug,
        ]);

        try {
            $qualityResult = $pythonService->process('quality_check.py', [
                'file_type' => $file->file_type,
                'file_path' => $absolutePath,
            ]);

            // Cache full result for UI rendering (avoid re-running Python on page refresh)
            Cache::put("quality_result:{$file->id}", $qualityResult, now()->addDay());

            $updates = [
                'processing_status' => 'completed',
            ];

            if (isset($qualityResult['total_rows'])) {
                $updates['rows_count'] = (int) $qualityResult['total_rows'];
            }
            if (isset($qualityResult['total_columns'])) {
                $updates['columns_count'] = (int) $qualityResult['total_columns'];
            }
            if (isset($qualityResult['quality_score'])) {
                $updates['quality_score'] = (float) $qualityResult['quality_score'];
            }

            $file->update($updates);

            Log::info('ProcessUploadedFileJob: python quality_check success', [
                'uploaded_file_id' => $file->id,
                'status' => $file->processing_status,
            ]);
        } catch (Exception $e) {
            $file->update([
                'processing_status' => 'failed',
            ]);

            Log::error('ProcessUploadedFileJob: python quality_check failed', [
                'uploaded_file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

