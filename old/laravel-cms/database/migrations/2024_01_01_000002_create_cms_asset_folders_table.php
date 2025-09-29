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
        Schema::create('cms_asset_folders', function (Blueprint $table) {
            $table->id();

            // Folder Information
            $table->string('name')->index();
            $table->string('slug')->index(); // URL-friendly name
            $table->string('path')->unique(); // Full path from root (e.g., /photos/2024/events)
            $table->text('description')->nullable();

            // Hierarchy
            $table->foreignId('parent_id')->nullable()->constrained('cms_asset_folders')->cascadeOnDelete();
            $table->integer('depth')->default(0)->index(); // For efficient querying
            $table->string('tree_path')->nullable()->index(); // Materialized path (e.g., 1.2.5)

            // Organization
            $table->integer('sort_order')->default(0);
            $table->string('color', 7)->nullable(); // Hex color for UI

            // Access Control
            $table->boolean('is_public')->default(true)->index();
            $table->json('permissions')->nullable(); // Custom permissions per folder
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            // Statistics
            $table->integer('assets_count')->default(0); // Cached count
            $table->bigInteger('total_size')->default(0); // Total size of all assets in folder

            // Metadata
            $table->json('metadata')->nullable(); // Custom metadata
            $table->json('settings')->nullable(); // Folder-specific settings

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['parent_id', 'sort_order']);
            $table->index(['depth', 'sort_order']);
            $table->index(['is_public', 'created_at']);
        });

        // Add foreign key constraint to cms_assets table
        Schema::table('cms_assets', function (Blueprint $table) {
            $table->foreign('folder_id')->references('id')->on('cms_asset_folders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_assets', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
        });

        Schema::dropIfExists('cms_asset_folders');
    }
};