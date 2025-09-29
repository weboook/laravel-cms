<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webook\LaravelCMS\Services\CMSLogger;

class MediaController
{
    protected $logger;

    public function __construct(CMSLogger $logger)
    {
        $this->logger = $logger;
    }

    public function upload(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'image' => 'required|image|mimes:jpeg,jpg,png,gif,svg,webp|max:10240', // 10MB
            ]);

            // Get the file
            $file = $request->file('image');

            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $originalName = $file->getClientOriginalName();

            // Define storage path
            $storagePath = config('cms.media.path', 'cms/media');
            $disk = config('cms.media.disk', 'public');

            // Store the file
            $path = $file->storeAs($storagePath, $filename, $disk);

            // Get file info
            $size = $file->getSize();
            $mimeType = $file->getMimeType();

            // Get image dimensions if it's an image
            $width = null;
            $height = null;
            if (strpos($mimeType, 'image/') === 0) {
                $imageInfo = getimagesize($file->getRealPath());
                if ($imageInfo) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                }
            }

            // Generate public URL
            $url = Storage::disk($disk)->url($path);

            // Save to database if table exists
            try {
                if (DB::getSchemaBuilder()->hasTable('cms_media')) {
                    DB::table('cms_media')->insert([
                        'filename' => $filename,
                        'original_name' => $originalName,
                        'path' => $path,
                        'url' => $url,
                        'mime_type' => $mimeType,
                        'extension' => $file->getClientOriginalExtension(),
                        'size' => $size,
                        'width' => $width,
                        'height' => $height,
                        'folder_id' => null, // Default to no folder
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $dbException) {
                // Log but don't fail if database save fails
                $this->logger->warning('Failed to save media to database', [
                    'error' => $dbException->getMessage(),
                    'file' => $filename
                ]);
            }

            // Log the upload
            $this->logger->info('Media uploaded', [
                'filename' => $filename,
                'original_name' => $originalName,
                'size' => $size,
                'mime_type' => $mimeType,
            ]);

            return response()->json([
                'success' => true,
                'url' => $url,
                'filename' => $filename,
                'size' => $size,
                'mime_type' => $mimeType,
                'width' => $width,
                'height' => $height,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Media upload failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function list(Request $request)
    {
        try {
            // Check if table exists
            if (!DB::getSchemaBuilder()->hasTable('cms_media')) {
                return response()->json([
                    'success' => true,
                    'media' => [],
                    'message' => 'Media library not initialized',
                ]);
            }

            // Get media from database
            $query = DB::table('cms_media');

            // Filter by folder if specified
            if ($request->has('folder_id')) {
                $query->where('folder_id', $request->folder_id);
            }

            // Filter by type if specified
            if ($request->has('type')) {
                $type = $request->type;
                if ($type === 'image') {
                    $query->where('mime_type', 'like', 'image/%');
                }
            }

            // Order by newest first
            $query->orderBy('created_at', 'desc');

            // Paginate
            $perPage = $request->get('per_page', 24);
            $media = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'media' => $media,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to list media', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load media library',
            ], 500);
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            // Check if table exists
            if (!DB::getSchemaBuilder()->hasTable('cms_media')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Media library not initialized',
                ], 404);
            }

            // Find the media
            $media = DB::table('cms_media')->where('id', $id)->first();

            if (!$media) {
                return response()->json([
                    'success' => false,
                    'message' => 'Media not found',
                ], 404);
            }

            // Delete from storage
            $disk = config('cms.media.disk', 'public');
            Storage::disk($disk)->delete($media->path);

            // Delete from database
            DB::table('cms_media')->where('id', $id)->delete();

            // Log the deletion
            $this->logger->info('Media deleted', [
                'id' => $id,
                'filename' => $media->filename,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete media', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete media',
            ], 500);
        }
    }
}