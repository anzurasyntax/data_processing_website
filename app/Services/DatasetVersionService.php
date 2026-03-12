<?php

namespace App\Services;

use App\Models\DatasetVersion;
use App\Models\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatasetVersionService
{
    public function createInitialVersion(UploadedFile $file): DatasetVersion
    {
        // Version 1 always points at the original uploaded file
        $version = DatasetVersion::firstOrCreate(
            [
                'uploaded_file_id' => $file->id,
                'version_number' => 1,
            ],
            [
                'file_path' => $file->file_path,
                'operations_json' => null,
                'rows_count' => $file->rows_count,
                'columns_count' => $file->columns_count,
            ]
        );

        Log::info('DatasetVersion created (initial)', [
            'uploaded_file_id' => $file->id,
            'version_number' => $version->version_number,
            'file_path' => $version->file_path,
        ]);

        return $version;
    }

    public function latestFor(UploadedFile $file): ?DatasetVersion
    {
        return $file->versions()
            ->orderByDesc('version_number')
            ->first();
    }

    public function latestAbsolutePath(UploadedFile $file): string
    {
        $version = $this->latestFor($file) ?? $this->createInitialVersion($file);

        return storage_path('app/public/' . $version->file_path);
    }

    protected function nextVersionNumber(UploadedFile $file): int
    {
        $latest = $this->latestFor($file);
        return $latest ? $latest->version_number + 1 : 1;
    }

    protected function buildVersionPath(UploadedFile $file, int $versionNumber): string
    {
        $basePath = $file->file_path;
        $dir = dirname($basePath);
        $name = pathinfo($basePath, PATHINFO_FILENAME);
        $ext = pathinfo($basePath, PATHINFO_EXTENSION);

        $ext = $ext ? ('.' . $ext) : '';

        return trim($dir, '/') . '/' . $name . '-v' . $versionNumber . $ext;
    }

    /**
     * Create a new version by copying the source file and recording operations.
     *
     * @param UploadedFile $file
     * @param array<string,mixed>|null $operationsSummary
     */
    public function createVersionFromExisting(
        UploadedFile $file,
        ?array $operationsSummary = null,
        ?int $rows = null,
        ?int $cols = null
    ): DatasetVersion {
        $latest = $this->latestFor($file) ?? $this->createInitialVersion($file);
        $nextVersion = $this->nextVersionNumber($file);

        $sourcePath = $latest->file_path;
        $newPath = $this->buildVersionPath($file, $nextVersion);

        Storage::disk('public')->copy($sourcePath, $newPath);

        $version = DatasetVersion::create([
            'uploaded_file_id' => $file->id,
            'version_number' => $nextVersion,
            'file_path' => $newPath,
            'operations_json' => $operationsSummary,
            'rows_count' => $rows,
            'columns_count' => $cols,
        ]);

        Log::info('DatasetVersion created (new)', [
            'uploaded_file_id' => $file->id,
            'version_number' => $version->version_number,
            'file_path' => $version->file_path,
        ]);

        return $version;
    }

    public function listVersions(UploadedFile $file)
    {
        return $file->versions()
            ->orderBy('version_number')
            ->get();
    }

    public function findVersion(UploadedFile $file, int $versionNumber): ?DatasetVersion
    {
        return $file->versions()
            ->where('version_number', $versionNumber)
            ->first();
    }

    public function revertToVersion(UploadedFile $file, int $versionNumber): DatasetVersion
    {
        $target = $this->findVersion($file, $versionNumber);

        if (! $target) {
            throw new \RuntimeException("Version {$versionNumber} not found for file {$file->id}");
        }

        $operations = [
            'operations' => [
                "revert_to_version_{$versionNumber}",
            ],
        ];

        $version = $this->createVersionFromExisting(
            $file,
            $operations,
            $target->rows_count,
            $target->columns_count
        );

        return $version;
    }

    public function absolutePathForVersion(UploadedFile $file, int $versionNumber): string
    {
        $version = $this->findVersion($file, $versionNumber);

        if (! $version) {
            throw new \RuntimeException("Version {$versionNumber} not found");
        }

        return storage_path('app/public/' . $version->file_path);
    }
}

