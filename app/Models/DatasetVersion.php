<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetVersion extends Model
{
    protected $fillable = [
        'uploaded_file_id',
        'version_number',
        'file_path',
        'operations_json',
        'rows_count',
        'columns_count',
    ];

    protected $casts = [
        'operations_json' => 'array',
    ];

    public function uploadedFile(): BelongsTo
    {
        return $this->belongsTo(UploadedFile::class);
    }
}

