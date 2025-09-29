<?php

namespace Webook\LaravelCMS\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Webook\LaravelCMS\Services\MediaAssetManager;
use Webook\LaravelCMS\Services\ImageProcessor;
use Webook\LaravelCMS\Models\Asset;
use Webook\LaravelCMS\Models\AssetFolder;

class AssetApiController extends Controller
{
    protected $assetManager;
    protected $imageProcessor;

    public function __construct(MediaAssetManager $assetManager, ImageProcessor $imageProcessor)
    {
        $this->assetManager = $assetManager;
        $this->imageProcessor = $imageProcessor;
    }

    public function index(Request $request): JsonResponse
    {
        $criteria = [
            'search' => $request->input('search'),
            'type' => $request->input('type'),
            'folder_id' => $request->input('folder_id'),
            'mime_type' => $request->input('mime_type'),
            'user_id' => $request->input('user_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'size_min' => $request->input('size_min'),
            'size_max' => $request->input('size_max'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_dir' => $request->input('sort_dir', 'desc'),
            'per_page' => $request->input('per_page', 20),
        ];

        $assets = $this->assetManager->search(array_filter($criteria));

        return response()->json([
            'success' => true,
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'from' => $assets->firstItem(),
                'to' => $assets->lastItem(),
            ],
        ]);
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['folder', 'user', 'usage']);

        return response()->json([
            'success' => true,
            'data' => $asset,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:' . (config('cms-assets.max_file_size', 10485760) / 1024),
            'folder_id' => 'nullable|exists:cms_asset_folders,id',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'filename' => 'nullable|string|max:255',
            'allow_duplicates' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $asset = $this->assetManager->upload($request->file('file'), [
                'folder_id' => $request->input('folder_id'),
                'alt_text' => $request->input('alt_text'),
                'caption' => $request->input('caption'),
                'description' => $request->input('description'),
                'filename' => $request->input('filename'),
                'allow_duplicates' => $request->boolean('allow_duplicates'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $asset,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function uploadFromUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'folder_id' => 'nullable|exists:cms_asset_folders,id',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'filename' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $asset = $this->assetManager->uploadFromUrl($request->input('url'), [
                'folder_id' => $request->input('folder_id'),
                'alt_text' => $request->input('alt_text'),
                'caption' => $request->input('caption'),
                'description' => $request->input('description'),
                'filename' => $request->input('filename'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded from URL successfully',
                'data' => $asset,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload from URL failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function uploadFromBase64(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|string',
            'filename' => 'required|string|max:255',
            'folder_id' => 'nullable|exists:cms_asset_folders,id',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $asset = $this->assetManager->uploadFromBase64(
                $request->input('data'),
                $request->input('filename'),
                [
                    'folder_id' => $request->input('folder_id'),
                    'alt_text' => $request->input('alt_text'),
                    'caption' => $request->input('caption'),
                    'description' => $request->input('description'),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'File uploaded from base64 successfully',
                'data' => $asset,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload from base64 failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function startChunkedUpload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1',
            'chunk_size' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
            'mime_type' => 'required|string',
            'folder_id' => 'nullable|exists:cms_asset_folders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uploadId = Str::uuid()->toString();

        return response()->json([
            'success' => true,
            'data' => [
                'upload_id' => $uploadId,
                'chunk_size' => $request->input('chunk_size'),
                'total_chunks' => $request->input('total_chunks'),
            ],
        ]);
    }

    public function uploadChunk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string',
            'chunk_number' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
            'chunk' => 'required|file',
            'original_filename' => 'required|string',
            'mime_type' => 'required|string',
            'total_size' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->assetManager->uploadChunk(
                $request->input('upload_id'),
                $request->file('chunk'),
                $request->input('chunk_number'),
                $request->input('total_chunks'),
                [
                    'original_filename' => $request->input('original_filename'),
                    'mime_type' => $request->input('mime_type'),
                    'total_size' => $request->input('total_size'),
                    'folder_id' => $request->input('folder_id'),
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chunk upload failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function completeChunkedUpload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $asset = $this->assetManager->assembleChunks($request->input('upload_id'));

            return response()->json([
                'success' => true,
                'message' => 'Chunked upload completed successfully',
                'data' => $asset,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete chunked upload: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'folder_id' => 'nullable|exists:cms_asset_folders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $asset->update($request->only(['alt_text', 'caption', 'description']));

            // Move to different folder if requested
            if ($request->has('folder_id')) {
                $folder = $request->input('folder_id') ? AssetFolder::find($request->input('folder_id')) : null;
                $this->assetManager->moveAsset($asset, $folder);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset updated successfully',
                'data' => $asset->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function duplicate(Request $request, Asset $asset): JsonResponse
    {
        try {
            $duplicate = $this->assetManager->duplicateAsset($asset, [
                'original_filename' => $request->input('filename', $asset->original_filename),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Asset duplicated successfully',
                'data' => $duplicate,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Duplication failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(Asset $asset): JsonResponse
    {
        try {
            $asset->delete();

            return response()->json([
                'success' => true,
                'message' => 'Asset deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deletion failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function batchMove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:cms_assets,id',
            'folder_id' => 'nullable|exists:cms_asset_folders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $folder = $request->input('folder_id') ? AssetFolder::find($request->input('folder_id')) : null;
            $results = $this->assetManager->batchMove($request->input('asset_ids'), $folder);

            return response()->json([
                'success' => true,
                'message' => 'Batch move completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch move failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:cms_assets,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $results = $this->assetManager->batchDelete($request->input('asset_ids'));

            return response()->json([
                'success' => true,
                'message' => 'Batch delete completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch delete failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    // Image processing endpoints

    public function resize(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->type !== 'image') {
            return response()->json([
                'success' => false,
                'message' => 'Asset must be an image',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'width' => 'required|integer|min:1|max:5000',
            'height' => 'nullable|integer|min:1|max:5000',
            'maintain_aspect_ratio' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $path = $this->imageProcessor->resize(
                $asset,
                $request->input('width'),
                $request->input('height'),
                $request->boolean('maintain_aspect_ratio', true)
            );

            return response()->json([
                'success' => true,
                'message' => 'Image resized successfully',
                'data' => [
                    'path' => $path,
                    'url' => asset($path),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resize failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function crop(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->type !== 'image') {
            return response()->json([
                'success' => false,
                'message' => 'Asset must be an image',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'width' => 'required|integer|min:1|max:5000',
            'height' => 'required|integer|min:1|max:5000',
            'x' => 'nullable|integer|min:0',
            'y' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $path = $this->imageProcessor->crop(
                $asset,
                $request->input('width'),
                $request->input('height'),
                $request->input('x'),
                $request->input('y')
            );

            return response()->json([
                'success' => true,
                'message' => 'Image cropped successfully',
                'data' => [
                    'path' => $path,
                    'url' => asset($path),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Crop failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function generateThumbnail(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->type !== 'image') {
            return response()->json([
                'success' => false,
                'message' => 'Asset must be an image',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'width' => 'required|integer|min:1|max:1000',
            'height' => 'nullable|integer|min:1|max:1000',
            'method' => 'in:fit,resize,crop',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $path = $this->imageProcessor->createThumbnail(
                $asset,
                $request->input('width'),
                $request->input('height'),
                $request->input('method', 'fit')
            );

            return response()->json([
                'success' => true,
                'message' => 'Thumbnail generated successfully',
                'data' => [
                    'path' => $path,
                    'url' => asset($path),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Thumbnail generation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function generateResponsiveImages(Asset $asset): JsonResponse
    {
        if ($asset->type !== 'image') {
            return response()->json([
                'success' => false,
                'message' => 'Asset must be an image',
            ], 400);
        }

        try {
            $responsiveImages = $this->imageProcessor->generateResponsiveImages($asset);

            return response()->json([
                'success' => true,
                'message' => 'Responsive images generated successfully',
                'data' => $responsiveImages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Responsive image generation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function convertFormat(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->type !== 'image') {
            return response()->json([
                'success' => false,
                'message' => 'Asset must be an image',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'format' => 'required|in:jpg,jpeg,png,webp,gif',
            'quality' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $path = $this->imageProcessor->convertFormat(
                $asset,
                $request->input('format'),
                $request->input('quality')
            );

            return response()->json([
                'success' => true,
                'message' => 'Format conversion completed successfully',
                'data' => [
                    'path' => $path,
                    'url' => asset($path),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format conversion failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function optimize(Asset $asset): JsonResponse
    {
        if ($asset->type !== 'image') {
            return response()->json([
                'success' => false,
                'message' => 'Asset must be an image',
            ], 400);
        }

        try {
            $result = $this->imageProcessor->optimize($asset);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Image optimized successfully',
                    'data' => $asset->fresh(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Image optimization failed',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Optimization failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    // Folder management endpoints

    public function getFolders(): JsonResponse
    {
        $folders = AssetFolder::with(['children', 'assets'])
                             ->roots()
                             ->ordered()
                             ->get();

        return response()->json([
            'success' => true,
            'data' => $folders->map->getTree(),
        ]);
    }

    public function createFolder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:cms_asset_folders,id',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $parent = $request->input('parent_id') ? AssetFolder::find($request->input('parent_id')) : null;

            $folder = $this->assetManager->createFolder(
                $request->input('name'),
                $parent,
                [
                    'description' => $request->input('description'),
                    'is_public' => $request->boolean('is_public', true),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Folder created successfully',
                'data' => $folder,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Folder creation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function deleteFolder(Request $request, AssetFolder $folder): JsonResponse
    {
        $moveAssets = $request->boolean('move_assets', true);

        try {
            $result = $this->assetManager->deleteFolder($folder, $moveAssets);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Folder deleted successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder deletion failed',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Folder deletion failed: ' . $e->getMessage(),
            ], 400);
        }
    }
}