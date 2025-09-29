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
        Schema::create('cms_assets', function (Blueprint $table) {
            $table->id();

            // File Information
            $table->string('filename')->index(); // Sanitized filename
            $table->string('original_name'); // Original uploaded filename
            $table->string('mime_type', 100)->index();
            $table->string('extension', 10)->index();
            $table->bigInteger('size'); // File size in bytes

            // Image/Video Dimensions
            $table->integer('width')->nullable()->index();
            $table->integer('height')->nullable()->index();
            $table->integer('duration')->nullable(); // For videos in seconds

            // File Storage
            $table->string('disk', 50)->default('public');
            $table->string('path'); // Relative path from disk root
            $table->string('url'); // Full URL to file
            $table->string('hash', 64)->unique()->index(); // SHA-256 hash for duplicate detection

            // Processed Versions
            $table->json('thumbnails')->nullable(); // Thumbnail URLs and sizes
            $table->json('responsive_urls')->nullable(); // Different sizes for responsive images
            $table->json('metadata')->nullable(); // EXIF data, color palette, etc.

            // Content Information
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->json('tags')->nullable(); // Array of tags

            // Organization
            $table->string('folder_path')->nullable()->index(); // Virtual folder path
            $table->foreignId('folder_id')->nullable()->constrained('cms_asset_folders')->nullOnDelete();

            // Upload Information
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();

            // Access Control
            $table->boolean('is_public')->default(true)->index();
            $table->json('permissions')->nullable(); // Custom permissions

            // Usage Tracking
            $table->integer('download_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();

            // Processing Status
            $table->boolean('is_processed')->default(false)->index();
            $table->json('processing_status')->nullable(); // Track processing steps
            $table->text('processing_error')->nullable();

            // Optimization
            $table->bigInteger('original_size')->nullable(); // Size before optimization
            $table->float('compression_ratio')->nullable();
            $table->boolean('is_optimized')->default(false);

            // CDN Integration
            $table->string('cdn_url')->nullable();
            $table->boolean('is_on_cdn')->default(false);
            $table->timestamp('cdn_synced_at')->nullable();

            // Versioning
            $table->integer('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('cms_assets')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['mime_type', 'created_at']);
            $table->index(['folder_path', 'created_at']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['is_public', 'created_at']);
            $table->index(['size', 'created_at']);
            $table->index(['extension', 'created_at']);

            // Full-text search
            $table->fullText(['title', 'description', 'alt_text', 'original_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_assets');
    }
};