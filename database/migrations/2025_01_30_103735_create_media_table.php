<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id(); // Auto-increment ID
            $table->string('original_name'); // Original file name
            $table->string('file_name'); // Stored file name (unique)
            $table->string('file_path'); // Relative file path
            $table->string('mime_type'); // File MIME type
            $table->string('file_size'); // File size in bytes
            $table->unsignedBigInteger('uploaded_by'); // ID of the user who uploaded the file
            $table->timestamps(); // Timestamps for created_at and updated_at
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
