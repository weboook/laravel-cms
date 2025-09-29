<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Webook\LaravelCMS\Contracts\MediaAssetManagerInterface;
use Webook\LaravelCMS\Models\Asset;
use Webook\LaravelCMS\Models\AssetFolder;
use Webook\LaravelCMS\Models\AssetChunk;
use Intervention\Image\ImageManagerStatic as Image;

class MediaAssetManager implements MediaAssetManagerInterface
{
    protected $config;
    protected $allowedTypes;
    protected $maxFileSize;
    protected $thumbnailSizes;

    public function __construct()
    {
        $this->config = config('cms-assets', []);
        $this->allowedTypes = $this->config['allowed_types'] ?? ['image', 'document', 'video', 'audio'];
        $this->maxFileSize = $this->config['max_file_size'] ?? 10485760; // 10MB
        $this->thumbnailSizes = $this->config['thumbnail_sizes'] ?? [
            'thumbnail' => [150, 150],
            'medium' => [300, 300],
            'large' => [1024, 1024],
        ];
    }

    public function upload(UploadedFile $file, array $options = []): Asset
    {
        $this->validateFile($file);

        $folder = isset($options['folder_id'])
            ? AssetFolder::find($options['folder_id'])
            : null;

        // Check for duplicates
        $fileHash = $this->calculateFileHash($file);
        if ($existing = $this->findDuplicate($fileHash)) {
            if ($options['allow_duplicates'] ?? false) {
                return $this->duplicateAsset($existing, $options);
            }
            return $existing;
        }

        $disk = $options['disk'] ?? $this->config['default_disk'] ?? 'public';
        $filename = $this->generateFilename($file, $options);
        $path = $this->generatePath($filename, $folder);

        // Store the file
        $storedPath = Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        // Create asset record
        $asset = Asset::create([
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $storedPath,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'file_hash' => $fileHash,
            'folder_id' => $folder?->id,
            'folder_path' => $folder?->path ?? '',
            'type' => $this->determineAssetType($file->getMimeType()),
            'user_id' => auth()->id(),
            'alt_text' => $options['alt_text'] ?? '',
            'caption' => $options['caption'] ?? '',
            'description' => $options['description'] ?? '',
            'metadata' => $this->extractMetadata($file),
            'status' => 'uploaded',
        ]);

        // Process the asset
        $this->processAsset($asset);

        Event::dispatch('cms.asset.uploaded', $asset);

        return $asset;
    }

    public function uploadFromUrl(string $url, array $options = []): Asset
    {
        $this->validateUrl($url);

        $tempFile = tempnam(sys_get_temp_dir(), 'cms_upload_');
        $fileContents = file_get_contents($url);

        if ($fileContents === false) {
            throw new \Exception("Unable to fetch file from URL: {$url}");
        }

        file_put_contents($tempFile, $fileContents);

        $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'downloaded_file';
        $mimeType = mime_content_type($tempFile);

        // Create a temporary UploadedFile instance
        $uploadedFile = new UploadedFile(
            $tempFile,
            $filename,
            $mimeType,
            null,
            true
        );

        try {
            $asset = $this->upload($uploadedFile, array_merge($options, [
                'source_url' => $url,
            ]));

            return $asset;
        } finally {
            unlink($tempFile);
        }
    }

    public function uploadFromBase64(string $base64, string $filename, array $options = []): Asset
    {
        if (!preg_match('/^data:([^;]+);base64,(.+)$/', $base64, $matches)) {
            throw new \InvalidArgumentException('Invalid base64 format');
        }

        $mimeType = $matches[1];
        $data = base64_decode($matches[2]);

        if ($data === false) {
            throw new \InvalidArgumentException('Invalid base64 data');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'cms_base64_');
        file_put_contents($tempFile, $data);

        $uploadedFile = new UploadedFile(
            $tempFile,
            $filename,
            $mimeType,
            null,
            true
        );

        try {
            return $this->upload($uploadedFile, $options);
        } finally {
            unlink($tempFile);
        }
    }

    public function uploadChunk(string $uploadId, UploadedFile $chunk, int $chunkNumber, int $totalChunks, array $metadata = []): array
    {
        $chunkHash = $this->calculateFileHash($chunk);
        $disk = $metadata['disk'] ?? $this->config['default_disk'] ?? 'public';
        $chunkPath = "chunks/{$uploadId}/chunk_{$chunkNumber}";

        // Store chunk
        Storage::disk($disk)->putFileAs(
            dirname($chunkPath),
            $chunk,
            basename($chunkPath)
        );

        // Create chunk record
        $chunkRecord = AssetChunk::create([
            'upload_id' => $uploadId,
            'chunk_hash' => $chunkHash,
            'chunk_number' => $chunkNumber,
            'total_chunks' => $totalChunks,
            'original_filename' => $metadata['original_filename'] ?? 'unknown',
            'mime_type' => $metadata['mime_type'] ?? 'application/octet-stream',
            'total_size' => $metadata['total_size'] ?? 0,
            'chunk_size' => $chunk->getSize(),
            'disk' => $disk,
            'chunk_path' => $chunkPath,
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'metadata' => $metadata,
            'status' => 'uploaded',
        ]);

        // Check if upload is complete
        $isComplete = AssetChunk::isUploadComplete($uploadId);

        return [
            'chunk_number' => $chunkNumber,
            'total_chunks' => $totalChunks,
            'is_complete' => $isComplete,
            'upload_id' => $uploadId,
        ];
    }

    public function assembleChunks(string $uploadId): Asset
    {
        if (!AssetChunk::isUploadComplete($uploadId)) {
            throw new \Exception('Upload is not complete yet');
        }

        $chunks = AssetChunk::getAllChunksForUpload($uploadId);
        $firstChunk = $chunks->first();

        if (!$firstChunk) {
            throw new \Exception('No chunks found for upload ID: ' . $uploadId);
        }

        // Assemble the file
        $assembledPath = AssetChunk::assembleFile($uploadId);

        // Create UploadedFile from assembled data
        $uploadedFile = new UploadedFile(
            $assembledPath,
            $firstChunk->original_filename,
            $firstChunk->mime_type,
            null,
            true
        );

        try {
            $asset = $this->upload($uploadedFile, array_merge(
                $firstChunk->metadata ?? [],
                ['chunked_upload' => true]
            ));

            // Clean up chunks
            AssetChunk::cleanupUpload($uploadId);

            return $asset;
        } finally {
            if (file_exists($assembledPath)) {
                unlink($assembledPath);
            }
        }
    }

    public function processAsset(Asset $asset): bool
    {
        try {
            DB::beginTransaction();

            $asset->update(['status' => 'processing']);

            // Generate thumbnails for images
            if ($asset->type === 'image') {
                $this->generateThumbnails($asset);
                $this->optimizeImage($asset);
            }

            // Extract additional metadata
            $this->extractAdvancedMetadata($asset);

            // Update CDN URLs if enabled
            if ($this->config['cdn']['enabled'] ?? false) {
                $this->updateCdnUrls($asset);
            }

            $asset->update(['status' => 'completed', 'processed_at' => now()]);

            DB::commit();

            Event::dispatch('cms.asset.processed', $asset);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            $asset->update(['status' => 'failed']);

            Event::dispatch('cms.asset.failed', [$asset, $e]);

            throw $e;
        }
    }

    public function generateThumbnails(Asset $asset, array $sizes = []): array
    {
        if ($asset->type !== 'image') {
            return [];
        }

        $sizes = $sizes ?: $this->thumbnailSizes;
        $thumbnails = [];

        foreach ($sizes as $name => $dimensions) {
            try {
                $thumbnail = $this->createThumbnail($asset, $name, $dimensions);
                $thumbnails[$name] = $thumbnail;
            } catch (\Exception $e) {
                // Log error but continue with other thumbnails
                logger()->error("Failed to create thumbnail {$name} for asset {$asset->id}: " . $e->getMessage());
            }
        }

        $asset->update(['thumbnails' => $thumbnails]);

        return $thumbnails;
    }

    public function optimizeImage(Asset $asset): bool
    {
        if ($asset->type !== 'image') {
            return false;
        }

        try {
            $image = Image::make(Storage::disk($asset->disk)->path($asset->path));

            // Apply optimization based on format
            switch (strtolower($asset->extension)) {
                case 'jpg':
                case 'jpeg':
                    $image->encode('jpg', 85);
                    break;
                case 'png':
                    $image->encode('png');
                    break;
                case 'webp':
                    $image->encode('webp', 85);
                    break;
            }

            // Save optimized version
            Storage::disk($asset->disk)->put($asset->path, $image->encoded);

            // Update file size
            $newSize = Storage::disk($asset->disk)->size($asset->path);
            $asset->update(['size' => $newSize]);

            return true;
        } catch (\Exception $e) {
            logger()->error("Failed to optimize image for asset {$asset->id}: " . $e->getMessage());
            return false;
        }
    }

    public function moveAsset(Asset $asset, AssetFolder $folder = null): bool
    {
        $oldFolder = $asset->folder;

        $asset->update([
            'folder_id' => $folder?->id,
            'folder_path' => $folder?->path ?? '',
        ]);

        // Update folder statistics
        if ($oldFolder) {
            $oldFolder->updateStatistics();
        }

        if ($folder) {
            $folder->updateStatistics();
        }

        Event::dispatch('cms.asset.moved', [$asset, $oldFolder, $folder]);

        return true;
    }

    public function duplicateAsset(Asset $asset, array $options = []): Asset
    {
        $originalPath = $asset->path;
        $newFilename = $this->generateUniqueFilename($asset->filename);
        $newPath = dirname($originalPath) . '/' . $newFilename;

        // Copy the file
        Storage::disk($asset->disk)->copy($originalPath, $newPath);

        // Create new asset record
        $duplicate = $asset->replicate();
        $duplicate->filename = $newFilename;
        $duplicate->path = $newPath;
        $duplicate->original_filename = $options['original_filename'] ?? $asset->original_filename;
        $duplicate->created_at = now();
        $duplicate->updated_at = now();
        $duplicate->save();

        // Copy thumbnails if they exist
        if ($asset->thumbnails) {
            $newThumbnails = [];
            foreach ($asset->thumbnails as $size => $thumbnailPath) {
                $newThumbnailPath = dirname($thumbnailPath) . '/' . pathinfo($newFilename, PATHINFO_FILENAME) . '_' . $size . '.' . pathinfo($thumbnailPath, PATHINFO_EXTENSION);
                Storage::disk($asset->disk)->copy($thumbnailPath, $newThumbnailPath);
                $newThumbnails[$size] = $newThumbnailPath;
            }
            $duplicate->update(['thumbnails' => $newThumbnails]);
        }

        return $duplicate;
    }

    public function search(array $criteria): LengthAwarePaginator
    {
        $query = Asset::query();

        // Apply search criteria
        if (!empty($criteria['search'])) {
            $query->search($criteria['search']);
        }

        if (!empty($criteria['type'])) {
            $query->ofType($criteria['type']);
        }

        if (!empty($criteria['folder_id'])) {
            $query->inFolder($criteria['folder_id']);
        }

        if (!empty($criteria['mime_type'])) {
            $query->where('mime_type', $criteria['mime_type']);
        }

        if (!empty($criteria['user_id'])) {
            $query->where('user_id', $criteria['user_id']);
        }

        if (!empty($criteria['date_from'])) {
            $query->where('created_at', '>=', $criteria['date_from']);
        }

        if (!empty($criteria['date_to'])) {
            $query->where('created_at', '<=', $criteria['date_to']);
        }

        if (!empty($criteria['size_min'])) {
            $query->where('size', '>=', $criteria['size_min']);
        }

        if (!empty($criteria['size_max'])) {
            $query->where('size', '<=', $criteria['size_max']);
        }

        // Apply sorting
        $sortBy = $criteria['sort_by'] ?? 'created_at';
        $sortDir = $criteria['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Paginate results
        $perPage = $criteria['per_page'] ?? 20;

        return $query->with(['folder', 'user'])->paginate($perPage);
    }

    public function getFolder(int $folderId = null): ?AssetFolder
    {
        return $folderId ? AssetFolder::find($folderId) : null;
    }

    public function createFolder(string $name, AssetFolder $parent = null, array $options = []): AssetFolder
    {
        $data = array_merge([
            'name' => $name,
            'parent_id' => $parent?->id,
            'is_public' => $parent?->is_public ?? ($options['is_public'] ?? true),
            'owner_id' => auth()->id(),
        ], $options);

        $folder = AssetFolder::create($data);

        Event::dispatch('cms.folder.created', $folder);

        return $folder;
    }

    public function deleteFolder(AssetFolder $folder, bool $moveAssets = true): bool
    {
        if ($moveAssets) {
            // Move assets to parent folder
            $targetFolder = $folder->parent;
            foreach ($folder->assets as $asset) {
                $this->moveAsset($asset, $targetFolder);
            }

            // Move child folders to parent
            foreach ($folder->children as $child) {
                if ($targetFolder) {
                    $child->moveTo($targetFolder);
                } else {
                    $child->moveTo(null); // Move to root
                }
            }
        } else {
            // Delete all assets in folder
            foreach ($folder->assets as $asset) {
                $asset->delete();
            }

            // Delete all child folders recursively
            foreach ($folder->children as $child) {
                $this->deleteFolder($child, false);
            }
        }

        $result = $folder->delete();

        if ($result) {
            Event::dispatch('cms.folder.deleted', $folder);
        }

        return $result;
    }

    public function batchMove(array $assetIds, AssetFolder $folder = null): array
    {
        $results = [];
        $assets = Asset::whereIn('id', $assetIds)->get();

        foreach ($assets as $asset) {
            try {
                $this->moveAsset($asset, $folder);
                $results[] = ['id' => $asset->id, 'success' => true];
            } catch (\Exception $e) {
                $results[] = ['id' => $asset->id, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function batchDelete(array $assetIds): array
    {
        $results = [];
        $assets = Asset::whereIn('id', $assetIds)->get();

        foreach ($assets as $asset) {
            try {
                $asset->delete();
                $results[] = ['id' => $asset->id, 'success' => true];
            } catch (\Exception $e) {
                $results[] = ['id' => $asset->id, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function cleanup(): int
    {
        $deletedCount = 0;

        // Clean up expired chunks
        $deletedCount += AssetChunk::cleanupExpired();

        // Clean up orphaned files
        $deletedCount += $this->cleanupOrphanedFiles();

        // Clean up failed uploads older than 24 hours
        $failedAssets = Asset::where('status', 'failed')
                           ->where('created_at', '<', now()->subDay())
                           ->get();

        foreach ($failedAssets as $asset) {
            if ($asset->delete()) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    // Protected helper methods

    protected function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Invalid file upload');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed size');
        }

        $mimeType = $file->getMimeType();
        $type = $this->determineAssetType($mimeType);

        if (!in_array($type, $this->allowedTypes)) {
            throw new \InvalidArgumentException('File type not allowed');
        }
    }

    protected function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format');
        }

        $headers = get_headers($url, 1);
        if (!$headers || strpos($headers[0], '200') === false) {
            throw new \InvalidArgumentException('URL is not accessible');
        }
    }

    protected function calculateFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getPathname());
    }

    protected function findDuplicate(string $fileHash): ?Asset
    {
        return Asset::where('file_hash', $fileHash)->first();
    }

    protected function generateFilename(UploadedFile $file, array $options = []): string
    {
        if (isset($options['filename'])) {
            return $options['filename'];
        }

        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = Str::slug($basename);

        return $basename . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    protected function generatePath(string $filename, AssetFolder $folder = null): string
    {
        $basePath = $this->config['base_path'] ?? 'assets';
        $datePath = date('Y/m');

        if ($folder) {
            return "{$basePath}/{$folder->path}/{$filename}";
        }

        return "{$basePath}/{$datePath}/{$filename}";
    }

    protected function generateUniqueFilename(string $originalFilename): string
    {
        $pathInfo = pathinfo($originalFilename);
        $basename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        $counter = 1;
        do {
            $newFilename = $basename . '_copy_' . $counter . ($extension ? '.' . $extension : '');
            $counter++;
        } while (Asset::where('filename', $newFilename)->exists());

        return $newFilename;
    }

    protected function determineAssetType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }

    protected function extractMetadata(UploadedFile $file): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];

        // Extract image metadata
        if (str_starts_with($file->getMimeType(), 'image/')) {
            try {
                $imageSize = getimagesize($file->getPathname());
                if ($imageSize) {
                    $metadata['width'] = $imageSize[0];
                    $metadata['height'] = $imageSize[1];
                }

                // Extract EXIF data for JPEG images
                if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($file->getPathname());
                    if ($exif) {
                        $metadata['exif'] = $exif;
                    }
                }
            } catch (\Exception $e) {
                // Ignore metadata extraction errors
            }
        }

        return $metadata;
    }

    protected function extractAdvancedMetadata(Asset $asset): void
    {
        $metadata = $asset->metadata ?? [];

        // Extract additional metadata based on file type
        try {
            $filePath = Storage::disk($asset->disk)->path($asset->path);

            if ($asset->type === 'image') {
                $image = Image::make($filePath);
                $metadata['width'] = $image->width();
                $metadata['height'] = $image->height();
                $metadata['aspect_ratio'] = round($image->width() / $image->height(), 2);
            }

            $asset->update(['metadata' => $metadata]);
        } catch (\Exception $e) {
            // Ignore metadata extraction errors
        }
    }

    protected function createThumbnail(Asset $asset, string $name, array $dimensions): string
    {
        $image = Image::make(Storage::disk($asset->disk)->path($asset->path));

        list($width, $height) = $dimensions;

        // Resize maintaining aspect ratio
        $image->fit($width, $height, function ($constraint) {
            $constraint->upsize();
        });

        // Generate thumbnail path
        $pathInfo = pathinfo($asset->path);
        $thumbnailPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $name . '.' . $pathInfo['extension'];

        // Save thumbnail
        Storage::disk($asset->disk)->put($thumbnailPath, $image->encoded);

        return $thumbnailPath;
    }

    protected function updateCdnUrls(Asset $asset): void
    {
        $cdnBase = $this->config['cdn']['url'] ?? '';

        if ($cdnBase) {
            $urls = ['url' => $cdnBase . '/' . $asset->path];

            if ($asset->thumbnails) {
                foreach ($asset->thumbnails as $size => $path) {
                    $urls['thumbnails'][$size] = $cdnBase . '/' . $path;
                }
            }

            $asset->update(['cdn_urls' => $urls]);
        }
    }

    protected function cleanupOrphanedFiles(): int
    {
        // This is a placeholder for orphaned file cleanup logic
        // In a real implementation, you would scan the storage directory
        // and compare with database records to find orphaned files
        return 0;
    }
}