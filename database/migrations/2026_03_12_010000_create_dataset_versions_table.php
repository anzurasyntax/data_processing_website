<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_file_id')
                ->constrained('uploaded_files')
                ->onDelete('cascade');
            $table->unsignedInteger('version_number');
            $table->string('file_path');
            $table->json('operations_json')->nullable();
            $table->unsignedBigInteger('rows_count')->nullable();
            $table->unsignedInteger('columns_count')->nullable();
            $table->timestamps();

            $table->unique(['uploaded_file_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_versions');
    }
};

