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
        Schema::create('cms_asset_usage', function (Blueprint $table) {
            $table->id();

            // Asset Reference
            $table->foreignId('asset_id')->constrained('cms_assets')->cascadeOnDelete();

            // Usage Context
            $table->string('usable_type'); // Model class name (e.g., Page, Post, etc.)
            $table->unsignedBigInteger('usable_id'); // Model ID
            $table->string('field_name')->nullable(); // Which field uses this asset
            $table->json('context')->nullable(); // Additional context data

            // Usage Type
            $table->enum('usage_type', [
                'content', 'featured_image', 'gallery', 'attachment', 'background', 'thumbnail'
            ])->default('content');

            // Metadata
            $table->json('metadata')->nullable(); // Usage-specific metadata
            $table->timestamp('used_at')->useCurrent();

            $table->timestamps();

            // Indexes
            $table->index(['asset_id', 'usable_type', 'usable_id']);
            $table->index(['usable_type', 'usable_id']);
            $table->index(['usage_type', 'used_at']);

            // Composite unique key to prevent duplicate usage records
            $table->unique(['asset_id', 'usable_type', 'usable_id', 'field_name'], 'unique_asset_usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_asset_usage');
    }
};