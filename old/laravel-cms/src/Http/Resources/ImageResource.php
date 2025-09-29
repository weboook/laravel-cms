<?php

namespace Webook\LaravelCMS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Image Resource
 *
 * API resource for image content with conditional field inclusion.
 */
class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_name' => $this->original_name,
            'alt_text' => $this->alt_text,
            'title' => $this->title,
            'description' => $this->when($this->description, $this->description),

            // URLs
            'url' => $this->url,
            'path' => $this->path,
            'thumbnails' => $this->when($this->thumbnails, $this->thumbnails),

            // File information
            'file_info' => [
                'size' => $this->size,
                'size_human' => $this->formatFileSize($this->size),
                'mime_type' => $this->mime_type,
                'extension' => pathinfo($this->filename, PATHINFO_EXTENSION),
            ],

            // Image dimensions
            'dimensions' => $this->when($this->width && $this->height, [
                'width' => $this->width,
                'height' => $this->height,
                'aspect_ratio' => $this->width && $this->height ? round($this->width / $this->height, 2) : null,
            ]),

            // Metadata
            'metadata' => $this->when($this->metadata, $this->metadata),
            'tags' => $this->when($this->tags, $this->tags),
            'category' => $this->when($this->category, $this->category),

            // Status flags
            'is_featured' => $this->when(isset($this->is_featured), (bool) $this->is_featured),
            'is_optimized' => $this->when(isset($this->is_optimized), (bool) $this->is_optimized),
            'has_watermark' => $this->when(isset($this->has_watermark), (bool) $this->has_watermark),

            // Analytics
            'analytics' => $this->when($this->analytics, [
                'views' => $this->analytics['views'] ?? 0,
                'downloads' => $this->analytics['downloads'] ?? 0,
                'usage_count' => $this->analytics['usage_count'] ?? 0,
            ]),

            // User information
            'uploaded_by' => new UserResource($this->whenLoaded('uploader')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),

            // Timestamps
            'uploaded_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Security and validation
            'file_hash' => $this->when($this->file_hash, $this->file_hash),
            'virus_scan' => $this->when($this->virus_scan_result, [
                'status' => $this->virus_scan_result,
                'scanned_at' => $this->virus_scan_at?->toISOString(),
            ]),

            // Permissions
            'permissions' => [
                'view' => true, // Images are generally viewable
                'edit' => $this->when(
                    $request->user()?->can('update', $this->resource),
                    true
                ),
                'delete' => $this->when(
                    $request->user()?->can('delete', $this->resource),
                    true
                ),
                'download' => $this->when(
                    $request->user()?->can('download', $this->resource),
                    true
                ),
            ],

            // Links
            'links' => [
                'self' => route('api.images.show', ['id' => $this->id]),
                'update' => route('api.images.update'),
                'delete' => route('api.images.destroy', ['id' => $this->id]),
                'download' => $this->when(
                    $request->user()?->can('download', $this->resource),
                    Storage::disk('public')->url($this->path)
                ),
                'thumbnails' => $this->getThumbnailLinks(),
            ],

            // EXIF data (if available and allowed)
            'exif' => $this->when(
                $this->exif_data &&
                ($request->user()?->can('view-exif', $this->resource) ?? false),
                $this->getFilteredExifData()
            ),
        ];
    }

    /**
     * Format file size in human readable format.
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    /**
     * Get thumbnail links.
     */
    private function getThumbnailLinks(): array
    {
        if (!$this->thumbnails) {
            return [];
        }

        $links = [];
        foreach ($this->thumbnails as $size => $path) {
            $links[$size] = Storage::disk('public')->url($path);
        }

        return $links;
    }

    /**
     * Get filtered EXIF data (remove sensitive information).
     */
    private function getFilteredExifData(): array
    {
        if (!$this->exif_data) {
            return [];
        }

        $allowedFields = [
            'camera_make',
            'camera_model',
            'lens_model',
            'focal_length',
            'aperture',
            'shutter_speed',
            'iso',
            'flash',
            'orientation',
            'color_space',
            'white_balance',
            'exposure_mode',
            'scene_type',
        ];

        return array_intersect_key($this->exif_data, array_flip($allowedFields));
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->toISOString(),
                'image_formats_supported' => config('cms.images.allowed_extensions', []),
                'max_upload_size' => config('cms.images.max_file_size', 10240) . 'KB',
            ],
        ];
    }
}