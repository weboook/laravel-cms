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
            } elseif ($request->has('include_root')) {
                // Show all items when viewing root folder
                // Don't add any folder filter - show everything
            } else {
                // Default behavior - show items without folder
                $query->whereNull('folder_id');
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

            // Get all media items
            $media = $query->get();

            return response()->json([
                'success' => true,
                'media' => $media->toArray(),
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

    /**
     * Upload multiple images
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'images' => 'required|array',
                'images.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp,svg|max:10240',
                'folder_id' => 'nullable|integer'
            ]);

            $uploadedMedia = [];
            $folderId = $request->input('folder_id', null);

            foreach ($request->file('images') as $file) {
                $uploadData = $this->processImageUpload($file, $folderId);
                if ($uploadData) {
                    $uploadedMedia[] = $uploadData;
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($uploadedMedia) . ' files uploaded successfully',
                'media' => $uploadedMedia
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to upload multiple images', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process single image upload
     */
    protected function processImageUpload($file, $folderId = null): ?array
    {
        try {
            $disk = config('cms.media.disk', 'public');
            $storagePath = config('cms.media.path', 'cms/media');

            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $originalName = $file->getClientOriginalName();

            // Store file
            $path = $file->storeAs($storagePath, $filename, $disk);

            // Get URL
            $url = Storage::disk($disk)->url($path);

            // Save to database
            $mediaId = DB::table('cms_media')->insertGetId([
                'filename' => $filename,
                'original_name' => $originalName,
                'path' => $path,
                'url' => $url,
                'disk' => $disk,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'folder_id' => $folderId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $mediaId,
                'filename' => $filename,
                'original_name' => $originalName,
                'url' => $url,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to process image upload', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get folders list
     */
    public function getFolders(Request $request): JsonResponse
    {
        try {
            $folders = DB::table('cms_folders')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'folders' => $folders
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get folders', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get folders'
            ], 500);
        }
    }

    /**
     * Create folder
     */
    public function createFolder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'parent_id' => 'nullable|integer'
            ]);

            $parentId = $request->input('parent_id', null);

            // Check if parent folder exists
            if ($parentId) {
                $parentExists = DB::table('cms_folders')->where('id', $parentId)->exists();
                if (!$parentExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent folder not found'
                    ], 404);
                }
            }

            // Create folder
            $folderId = DB::table('cms_folders')->insertGetId([
                'name' => $request->input('name'),
                'parent_id' => $parentId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logger->info('Folder created', [
                'id' => $folderId,
                'name' => $request->input('name')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Folder created successfully',
                'folder' => [
                    'id' => $folderId,
                    'name' => $request->input('name'),
                    'parent_id' => $parentId
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create folder', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create folder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete folder
     */
    public function deleteFolder($id): JsonResponse
    {
        try {
            // Check if folder exists
            $folder = DB::table('cms_folders')->where('id', $id)->first();
            if (!$folder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder not found'
                ], 404);
            }

            // Check if folder has media
            $hasMedia = DB::table('cms_media')->where('folder_id', $id)->exists();
            if ($hasMedia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete folder with media items'
                ], 400);
            }

            // Check if folder has subfolders
            $hasSubfolders = DB::table('cms_folders')->where('parent_id', $id)->exists();
            if ($hasSubfolders) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete folder with subfolders'
                ], 400);
            }

            // Delete folder
            DB::table('cms_folders')->where('id', $id)->delete();

            $this->logger->info('Folder deleted', [
                'id' => $id,
                'name' => $folder->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Folder deleted successfully'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete folder', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete folder'
            ], 500);
        }
    }
}