<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->unsignedBigInteger('rows_count')->nullable()->after('file_size');
            $table->unsignedInteger('columns_count')->nullable()->after('rows_count');
            $table->unsignedBigInteger('dataset_size')->nullable()->after('columns_count');
            $table->float('quality_score')->nullable()->after('dataset_size');
            $table->string('processing_status')->default('pending')->after('quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->dropColumn([
                'rows_count',
                'columns_count',
                'dataset_size',
                'quality_score',
                'processing_status',
            ]);
        });
    }
};

