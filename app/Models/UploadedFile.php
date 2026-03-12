<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UploadedFile extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'original_name',
        'file_type',
        'file_path',
        'mime_type',
        'file_size',
        'rows_count',
        'columns_count',
        'dataset_size',
        'quality_score',
        'processing_status',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DatasetVersion::class);
    }
}
