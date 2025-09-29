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
        Schema::create('cms_asset_chunks', function (Blueprint $table) {
            $table->id();

            // Chunk Identification
            $table->string('upload_id')->index(); // Unique identifier for the upload session
            $table->string('chunk_hash', 64)->index(); // Hash of this specific chunk
            $table->integer('chunk_number'); // Sequential chunk number
            $table->integer('total_chunks'); // Total expected chunks

            // File Information
            $table->string('original_filename');
            $table->string('mime_type');
            $table->bigInteger('total_size'); // Total file size
            $table->bigInteger('chunk_size'); // Size of this chunk

            // Storage
            $table->string('disk', 50)->default('local');
            $table->string('chunk_path'); // Path to stored chunk file

            // Upload Session
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('session_id')->nullable(); // Browser session ID
            $table->json('metadata')->nullable(); // Additional upload metadata

            // Status
            $table->enum('status', ['pending', 'uploaded', 'processing', 'completed', 'failed', 'expired'])
                  ->default('pending')->index();
            $table->timestamp('expires_at')->index(); // Chunk expiration time

            $table->timestamps();

            // Indexes
            $table->index(['upload_id', 'chunk_number']);
            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'created_at']);

            // Unique constraint
            $table->unique(['upload_id', 'chunk_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_asset_chunks');
    }
};