<?php

namespace App\Services;

use App\Models\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadedFileService
{
    public function store($fileType, $file, ?int $userId = null): UploadedFile
    {
        $originalName = (string) $file->getClientOriginalName();

        // Sanitize original filename to avoid problematic characters
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $baseSlug = Str::slug($base) ?: 'file';

        // Keep slug unique per user; also keep filename readable.
        $slug = $baseSlug;
        if ($userId !== null) {
            $i = 2;
            while (UploadedFile::where('user_id', $userId)->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i;
                $i++;
            }
        } else {
            // API / legacy uploads without user: keep globally unique-ish
            $slug = $baseSlug . '-' . Str::lower(Str::random(6));
        }

        // Prefer the validated extension; fall back to provided file type
        $safeExt = $ext ?: $fileType;
        $fileName = $slug . '.' . $safeExt;
        $dir = $userId ? "uploads/user-{$userId}" : 'uploads/public';
        $path = Storage::disk('public')->putFileAs($dir, $file, $fileName);

        return UploadedFile::create(array_filter([
            'user_id'          => $userId,
            'slug'             => $slug,
            'original_name'    => $originalName,
            'file_type'        => $fileType,
            'file_path'        => $path,
            'mime_type'        => $file->getClientMimeType(),
            'file_size'        => $file->getSize(),
            // Phase 1 metadata defaults
            'dataset_size'     => $file->getSize(),
            'processing_status'=> 'pending',
        ], fn ($v) => $v !== null));
    }

    public function find(int|string $id): UploadedFile
    {
        return UploadedFile::findOrFail($id);
    }

    public function findForUser(int|string $id, int $userId): UploadedFile
    {
        return UploadedFile::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    public function findForUserBySlug(string $slug, int $userId): UploadedFile
    {
        return UploadedFile::where('slug', $slug)
            ->where('user_id', $userId)
            ->firstOrFail();
    }
}
