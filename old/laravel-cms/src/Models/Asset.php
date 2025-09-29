<?php

namespace Webook\LaravelCMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cms_assets';

    protected $fillable = [
        'filename',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'duration',
        'disk',
        'path',
        'url',
        'hash',
        'thumbnails',
        'responsive_urls',
        'metadata',
        'title',
        'description',
        'alt_text',
        'caption',
        'tags',
        'folder_path',
        'folder_id',
        'uploaded_by',
        'uploaded_at',
        'is_public',
        'permissions',
        'download_count',
        'last_accessed_at',
        'is_processed',
        'processing_status',
        'processing_error',
        'original_size',
        'compression_ratio',
        'is_optimized',
        'cdn_url',
        'is_on_cdn',
        'cdn_synced_at',
        'version',
        'parent_id',
    ];

    protected $casts = [
        'thumbnails' => 'array',
        'responsive_urls' => 'array',
        'metadata' => 'array',
        'tags' => 'array',
        'permissions' => 'array',
        'processing_status' => 'array',
        'uploaded_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'cdn_synced_at' => 'datetime',
        'is_public' => 'boolean',
        'is_processed' => 'boolean',
        'is_optimized' => 'boolean',
        'is_on_cdn' => 'boolean',
        'compression_ratio' => 'float',
    ];

    protected $appends = [
        'type',
        'formatted_size',
        'is_image',
        'is_video',
        'is_document',
        'thumbnail_url',
        'download_url',
    ];

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'uploaded_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'folder_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_id');
    }

    public function usage(): HasMany
    {
        return $this->hasMany(AssetUsage::class);
    }

    public function usages(): MorphMany
    {
        return $this->morphMany(AssetUsage::class, 'usable');
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    public function scopeDocuments($query)
    {
        return $query->whereIn('mime_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ]);
    }

    public function scopeAudio($query)
    {
        return $query->where('mime_type', 'like', 'audio/%');
    }

    public function scopeInFolder($query, $folder)
    {
        if (is_string($folder)) {
            return $query->where('folder_path', $folder);
        }

        if (is_int($folder) || (is_object($folder) && method_exists($folder, 'getKey'))) {
            $folderId = is_object($folder) ? $folder->getKey() : $folder;
            return $query->where('folder_id', $folderId);
        }

        return $query;
    }

    public function scopeSearch($query, $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('alt_text', 'like', "%{$term}%")
              ->orWhere('original_name', 'like', "%{$term}%")
              ->orWhereJsonContains('tags', $term);
        });
    }

    public function scopeByType($query, $type)
    {
        switch ($type) {
            case 'image':
                return $query->images();
            case 'video':
                return $query->videos();
            case 'document':
                return $query->documents();
            case 'audio':
                return $query->audio();
            default:
                return $query;
        }
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
    }

    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    public function scopeOptimized($query)
    {
        return $query->where('is_optimized', true);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByExtension($query, $extension)
    {
        return $query->where('extension', strtolower($extension));
    }

    public function scopeBySize($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('size', '>=', $min);
        }

        if ($max !== null) {
            $query->where('size', '<=', $max);
        }

        return $query;
    }

    public function scopeByDimensions($query, $minWidth = null, $maxWidth = null, $minHeight = null, $maxHeight = null)
    {
        if ($minWidth !== null) {
            $query->where('width', '>=', $minWidth);
        }

        if ($maxWidth !== null) {
            $query->where('width', '<=', $maxWidth);
        }

        if ($minHeight !== null) {
            $query->where('height', '>=', $minHeight);
        }

        if ($maxHeight !== null) {
            $query->where('height', '<=', $maxHeight);
        }

        return $query;
    }

    // =============================================================================
    // ACCESSORS
    // =============================================================================

    public function getTypeAttribute(): string
    {
        $mimeType = $this->mime_type;

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];

        if (in_array($mimeType, $documentTypes)) {
            return 'document';
        }

        return 'file';
    }

    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    public function getIsImageAttribute(): bool
    {
        return $this->type === 'image';
    }

    public function getIsVideoAttribute(): bool
    {
        return $this->type === 'video';
    }

    public function getIsDocumentAttribute(): bool
    {
        return $this->type === 'document';
    }

    public function getIsAudioAttribute(): bool
    {
        return $this->type === 'audio';
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        // Return CDN URL if available
        if ($this->cdn_url && $this->is_on_cdn) {
            $thumbnails = $this->thumbnails;
            if ($thumbnails && isset($thumbnails['thumb'])) {
                return $this->cdn_url . '/' . $thumbnails['thumb'];
            }
        }

        // Return thumbnail URL
        $thumbnails = $this->thumbnails;
        if ($thumbnails && isset($thumbnails['thumb'])) {
            return Storage::disk($this->disk)->url($thumbnails['thumb']);
        }

        // For non-images, return a default thumbnail based on type
        if (!$this->is_image) {
            return $this->getDefaultThumbnail();
        }

        // Fallback to original file URL
        return $this->url;
    }

    public function getDownloadUrlAttribute(): string
    {
        if ($this->cdn_url && $this->is_on_cdn) {
            return $this->cdn_url;
        }

        return $this->url;
    }

    public function getThumbnailsAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    public function getResponsiveUrlsAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    public function getMetadataAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    public function getTagsAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    public function getPermissionsAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    public function getProcessingStatusAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    // =============================================================================
    // METHODS
    // =============================================================================

    public function generateThumbnails(array $sizes = []): array
    {
        if (!$this->is_image) {
            return [];
        }

        $sizes = $sizes ?: config('cms-assets.thumbnails.sizes', [
            'thumb' => [150, 150],
            'small' => [300, 300],
            'medium' => [768, 768],
            'large' => [1024, 1024],
        ]);

        $imageProcessor = app(\Webook\LaravelCMS\Services\ImageProcessor::class);
        $thumbnails = [];

        foreach ($sizes as $size => $dimensions) {
            try {
                $thumbnailPath = $imageProcessor->createThumbnail(
                    Storage::disk($this->disk)->path($this->path),
                    $dimensions[0],
                    $dimensions[1],
                    $size
                );

                if ($thumbnailPath) {
                    $thumbnails[$size] = str_replace(Storage::disk($this->disk)->path(''), '', $thumbnailPath);
                }
            } catch (\Exception $e) {
                \Log::error("Failed to generate {$size} thumbnail for asset {$this->id}: " . $e->getMessage());
            }
        }

        $this->update(['thumbnails' => $thumbnails]);

        return $thumbnails;
    }

    public function generateResponsiveImages(array $sizes = []): array
    {
        if (!$this->is_image) {
            return [];
        }

        $sizes = $sizes ?: config('cms-assets.responsive_images.sizes', [320, 640, 768, 1024, 1366, 1920]);
        $imageProcessor = app(\Webook\LaravelCMS\Services\ImageProcessor::class);
        $responsiveUrls = [];

        foreach ($sizes as $width) {
            try {
                // Skip if original image is smaller than target width
                if ($this->width && $this->width < $width) {
                    continue;
                }

                $responsivePath = $imageProcessor->resize(
                    Storage::disk($this->disk)->path($this->path),
                    $width,
                    null,
                    "responsive_{$width}"
                );

                if ($responsivePath) {
                    $responsiveUrls[$width] = [
                        'url' => Storage::disk($this->disk)->url(str_replace(Storage::disk($this->disk)->path(''), '', $responsivePath)),
                        'width' => $width,
                    ];

                    // Generate WebP version if enabled
                    if (config('cms-assets.responsive_images.use_webp', true)) {
                        $webpPath = $imageProcessor->toWebP($responsivePath);
                        if ($webpPath) {
                            $responsiveUrls[$width]['webp'] = Storage::disk($this->disk)->url(str_replace(Storage::disk($this->disk)->path(''), '', $webpPath));
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to generate responsive image ({$width}px) for asset {$this->id}: " . $e->getMessage());
            }
        }

        $this->update(['responsive_urls' => $responsiveUrls]);

        return $responsiveUrls;
    }

    public function optimize(): bool
    {
        if (!$this->is_image || $this->is_optimized) {
            return false;
        }

        try {
            $imageProcessor = app(\Webook\LaravelCMS\Services\ImageProcessor::class);
            $originalSize = $this->size;

            $optimizedPath = $imageProcessor->optimize(
                Storage::disk($this->disk)->path($this->path)
            );

            if ($optimizedPath) {
                $newSize = filesize($optimizedPath);
                $compressionRatio = $originalSize > 0 ? ($originalSize - $newSize) / $originalSize : 0;

                $this->update([
                    'original_size' => $originalSize,
                    'size' => $newSize,
                    'compression_ratio' => $compressionRatio,
                    'is_optimized' => true,
                ]);

                return true;
            }
        } catch (\Exception $e) {
            \Log::error("Failed to optimize asset {$this->id}: " . $e->getMessage());
        }

        return false;
    }

    public function getDimensions(): array
    {
        if (!$this->is_image || !$this->exists()) {
            return ['width' => null, 'height' => null];
        }

        if ($this->width && $this->height) {
            return ['width' => $this->width, 'height' => $this->height];
        }

        try {
            $imagePath = Storage::disk($this->disk)->path($this->path);
            if (file_exists($imagePath)) {
                [$width, $height] = getimagesize($imagePath);

                $this->update([
                    'width' => $width,
                    'height' => $height,
                ]);

                return ['width' => $width, 'height' => $height];
            }
        } catch (\Exception $e) {
            \Log::error("Failed to get dimensions for asset {$this->id}: " . $e->getMessage());
        }

        return ['width' => null, 'height' => null];
    }

    public function getFileSize(bool $formatted = true): mixed
    {
        if ($formatted) {
            return $this->formatBytes($this->size);
        }

        return $this->size;
    }

    public function duplicate(array $options = []): Asset
    {
        $newAsset = $this->replicate();
        $newAsset->filename = $this->generateUniqueFilename($this->filename);
        $newAsset->hash = null; // Will be generated on save
        $newAsset->version = 1;
        $newAsset->parent_id = $this->id;
        $newAsset->download_count = 0;
        $newAsset->last_accessed_at = null;

        // Override with provided options
        foreach ($options as $key => $value) {
            $newAsset->$key = $value;
        }

        // Copy the actual file
        $newPath = $this->generateUniquePath($this->path);
        Storage::disk($this->disk)->copy($this->path, $newPath);

        $newAsset->path = $newPath;
        $newAsset->url = Storage::disk($this->disk)->url($newPath);
        $newAsset->hash = hash_file('sha256', Storage::disk($this->disk)->path($newPath));

        $newAsset->save();

        // Copy thumbnails if they exist
        if ($this->thumbnails) {
            $newThumbnails = [];
            foreach ($this->thumbnails as $size => $thumbnailPath) {
                $newThumbnailPath = $this->generateUniquePath($thumbnailPath);
                if (Storage::disk($this->disk)->copy($thumbnailPath, $newThumbnailPath)) {
                    $newThumbnails[$size] = $newThumbnailPath;
                }
            }
            $newAsset->update(['thumbnails' => $newThumbnails]);
        }

        return $newAsset;
    }

    public function move($folder): bool
    {
        try {
            if (is_string($folder)) {
                $folderModel = AssetFolder::where('path', $folder)->first();
                if (!$folderModel) {
                    $folderModel = AssetFolder::create([
                        'name' => basename($folder),
                        'path' => $folder,
                    ]);
                }
                $folder = $folderModel;
            }

            $this->update([
                'folder_id' => $folder ? $folder->id : null,
                'folder_path' => $folder ? $folder->path : null,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to move asset {$this->id} to folder: " . $e->getMessage());
            return false;
        }
    }

    public function addTag(string $tag): bool
    {
        $tags = $this->tags ?? [];

        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
            return true;
        }

        return false;
    }

    public function removeTag(string $tag): bool
    {
        $tags = $this->tags ?? [];
        $key = array_search($tag, $tags);

        if ($key !== false) {
            unset($tags[$key]);
            $this->update(['tags' => array_values($tags)]);
            return true;
        }

        return false;
    }

    public function updateUsage(string $usableType, int $usableId, string $fieldName = null): void
    {
        AssetUsage::updateOrCreate([
            'asset_id' => $this->id,
            'usable_type' => $usableType,
            'usable_id' => $usableId,
            'field_name' => $fieldName,
        ], [
            'used_at' => now(),
        ]);
    }

    public function trackDownload(): void
    {
        $this->increment('download_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function getEmbedCode(array $options = []): string
    {
        $class = $options['class'] ?? '';
        $style = $options['style'] ?? '';

        if ($this->is_image) {
            $alt = $this->alt_text ?: $this->title ?: $this->original_name;
            $src = $options['size'] ?? 'original';

            if ($src !== 'original' && isset($this->thumbnails[$src])) {
                $url = Storage::disk($this->disk)->url($this->thumbnails[$src]);
            } else {
                $url = $this->url;
            }

            return sprintf(
                '<img src="%s" alt="%s" class="%s" style="%s" />',
                $url,
                htmlspecialchars($alt),
                $class,
                $style
            );
        }

        if ($this->is_video) {
            return sprintf(
                '<video controls class="%s" style="%s"><source src="%s" type="%s">Your browser does not support the video tag.</video>',
                $class,
                $style,
                $this->url,
                $this->mime_type
            );
        }

        // Default link for other file types
        return sprintf(
            '<a href="%s" class="%s" style="%s" target="_blank">%s</a>',
            $this->url,
            $class,
            $style,
            $this->title ?: $this->original_name
        );
    }

    public function getSrcSet(): string
    {
        if (!$this->is_image || empty($this->responsive_urls)) {
            return '';
        }

        $srcSet = [];
        foreach ($this->responsive_urls as $width => $data) {
            $srcSet[] = $data['url'] . ' ' . $width . 'w';
        }

        return implode(', ', $srcSet);
    }

    public function canBeAccessedBy($user = null): bool
    {
        if ($this->is_public) {
            return true;
        }

        if (!$user) {
            $user = auth()->user();
        }

        if (!$user) {
            return false;
        }

        // Check if user is the uploader
        if ($this->uploaded_by === $user->id) {
            return true;
        }

        // Check custom permissions
        $permissions = $this->permissions ?? [];
        if (isset($permissions['users']) && in_array($user->id, $permissions['users'])) {
            return true;
        }

        if (isset($permissions['roles'])) {
            $userRoles = $user->roles()->pluck('name')->toArray();
            if (array_intersect($userRoles, $permissions['roles'])) {
                return true;
            }
        }

        return false;
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function delete(): bool
    {
        try {
            // Delete physical files
            if ($this->exists()) {
                Storage::disk($this->disk)->delete($this->path);
            }

            // Delete thumbnails
            if ($this->thumbnails) {
                foreach ($this->thumbnails as $thumbnailPath) {
                    if (Storage::disk($this->disk)->exists($thumbnailPath)) {
                        Storage::disk($this->disk)->delete($thumbnailPath);
                    }
                }
            }

            // Delete responsive images
            if ($this->responsive_urls) {
                foreach ($this->responsive_urls as $responsiveData) {
                    $path = str_replace(Storage::disk($this->disk)->url(''), '', $responsiveData['url']);
                    if (Storage::disk($this->disk)->exists($path)) {
                        Storage::disk($this->disk)->delete($path);
                    }

                    // Delete WebP version if exists
                    if (isset($responsiveData['webp'])) {
                        $webpPath = str_replace(Storage::disk($this->disk)->url(''), '', $responsiveData['webp']);
                        if (Storage::disk($this->disk)->exists($webpPath)) {
                            Storage::disk($this->disk)->delete($webpPath);
                        }
                    }
                }
            }

            // Delete from database
            return parent::delete();
        } catch (\Exception $e) {
            \Log::error("Failed to delete asset {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    protected function generateUniqueFilename(string $filename): string
    {
        $info = pathinfo($filename);
        $name = $info['filename'];
        $extension = $info['extension'] ?? '';

        $counter = 1;
        do {
            $newName = $name . '-' . $counter;
            $newFilename = $extension ? $newName . '.' . $extension : $newName;
            $counter++;
        } while (static::where('filename', $newFilename)->exists());

        return $newFilename;
    }

    protected function generateUniquePath(string $path): string
    {
        $info = pathinfo($path);
        $directory = $info['dirname'];
        $name = $info['filename'];
        $extension = $info['extension'] ?? '';

        $counter = 1;
        do {
            $newName = $name . '-' . $counter;
            $newPath = $directory . '/' . ($extension ? $newName . '.' . $extension : $newName);
            $counter++;
        } while (Storage::disk($this->disk)->exists($newPath));

        return $newPath;
    }

    protected function getDefaultThumbnail(): string
    {
        $iconMap = [
            'video' => 'video-icon.svg',
            'audio' => 'audio-icon.svg',
            'document' => 'document-icon.svg',
            'file' => 'file-icon.svg',
        ];

        $icon = $iconMap[$this->type] ?? 'file-icon.svg';

        return asset('vendor/laravel-cms/images/icons/' . $icon);
    }

    // =============================================================================
    // BOOT METHOD
    // =============================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($asset) {
            if (!$asset->hash && $asset->path) {
                $asset->hash = hash_file('sha256', Storage::disk($asset->disk)->path($asset->path));
            }

            if (!$asset->uploaded_at) {
                $asset->uploaded_at = now();
            }
        });

        static::deleting(function ($asset) {
            // Delete usage records
            $asset->usage()->delete();
        });
    }
}