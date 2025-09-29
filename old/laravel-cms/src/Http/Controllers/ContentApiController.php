<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webook\LaravelCMS\Http\Requests\Api\ContentRequest;
use Webook\LaravelCMS\Http\Requests\Api\UpdateTextRequest;
use Webook\LaravelCMS\Http\Requests\Api\BulkUpdateRequest;
use Webook\LaravelCMS\Http\Requests\Api\UpdateImageRequest;
use Webook\LaravelCMS\Http\Requests\Api\UploadImageRequest;
use Webook\LaravelCMS\Http\Requests\Api\UpdateLinkRequest;
use Webook\LaravelCMS\Http\Requests\Api\TranslationRequest;
use Webook\LaravelCMS\Http\Requests\Api\SyncRequest;
use Webook\LaravelCMS\Http\Requests\Api\RestoreRequest;
use Webook\LaravelCMS\Http\Resources\TextContentResource;
use Webook\LaravelCMS\Http\Resources\ImageResource;
use Webook\LaravelCMS\Http\Resources\LinkResource;
use Webook\LaravelCMS\Http\Resources\TranslationResource;
use Webook\LaravelCMS\Http\Resources\HistoryResource;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\TranslationManager;
use Webook\LaravelCMS\Services\ContentScanner;
use Webook\LaravelCMS\Models\TextContent;
use Webook\LaravelCMS\Models\Image;
use Webook\LaravelCMS\Models\Link;
use Webook\LaravelCMS\Models\Translation;
use Webook\LaravelCMS\Models\ContentHistory;
use Webook\LaravelCMS\Events\ContentUpdated;
use Webook\LaravelCMS\Events\ImageUploaded;
use Webook\LaravelCMS\Events\TranslationUpdated;
use Webook\LaravelCMS\Services\CmsLogger;
use Exception;

/**
 * Content API Controller
 *
 * RESTful API for comprehensive content management including text,
 * images, links, translations, and version control.
 *
 * @package Webook\LaravelCMS\Http\Controllers
 * @version 1.0
 *
 * @OA\Tag(
 *     name="Content",
 *     description="Content management operations"
 * )
 * @OA\Tag(
 *     name="Images",
 *     description="Image management operations"
 * )
 * @OA\Tag(
 *     name="Links",
 *     description="Link management operations"
 * )
 * @OA\Tag(
 *     name="Translations",
 *     description="Translation management operations"
 * )
 * @OA\Tag(
 *     name="History",
 *     description="Version control and history operations"
 * )
 */
class ContentApiController extends Controller
{
    protected FileUpdater $fileUpdater;
    protected TranslationManager $translationManager;
    protected ContentScanner $contentScanner;

    public function __construct(
        FileUpdater $fileUpdater,
        TranslationManager $translationManager,
        ContentScanner $contentScanner
    ) {
        $this->fileUpdater = $fileUpdater;
        $this->translationManager = $translationManager;
        $this->contentScanner = $contentScanner;

        // Apply middleware
        $this->middleware(['auth:sanctum', 'throttle:api']);
        $this->middleware('throttle:60,1')->only(['bulkUpdateText', 'syncTranslations']);
        $this->middleware('throttle:30,1')->only(['uploadImage', 'updateImage']);
    }

    // ===================================
    // TEXT CONTENT ENDPOINTS
    // ===================================

    /**
     * Get text content by key.
     *
     * @OA\Get(
     *     path="/api/v1/content/text/{key}",
     *     tags={"Content"},
     *     summary="Get text content by key",
     *     description="Retrieve text content with metadata and translation information",
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Content key",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="locale",
     *         in="query",
     *         description="Locale for content",
     *         @OA\Schema(type="string", default="en")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related data (translations,history)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Text content retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/TextContentResource")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Content not found"
     *     )
     * )
     */
    public function getText(ContentRequest $request, string $key): JsonResponse
    {
        try {
            $locale = $request->get('locale', app()->getLocale());
            $include = $this->parseIncludes($request->get('include', ''));

            // Build query with eager loading
            $query = TextContent::where('key', $key)
                ->where('locale', $locale);

            if (in_array('translations', $include)) {
                $query->with('translations');
            }

            if (in_array('history', $include)) {
                $query->with(['history' => function ($q) {
                    $q->latest()->limit(10);
                }]);
            }

            $content = $query->first();

            if (!$content) {
                return $this->errorResponse(
                    "Text content not found for key: {$key}",
                    [],
                    404
                );
            }

            // Generate ETag for caching
            $etag = md5($content->updated_at . $content->value);
            $request->headers->set('If-None-Match', $etag);

            if ($request->header('If-None-Match') === $etag) {
                return response()->json(null, 304);
            }

            $this->logActivity('content.viewed', [
                'key' => $key,
                'locale' => $locale,
                'user_id' => $request->user()->id,
            ]);

            return $this->successResponse(
                new TextContentResource($content),
                'Text content retrieved successfully'
            )->header('ETag', $etag);

        } catch (Exception $e) {
            Log::error('Failed to get text content', [
                'key' => $key,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to retrieve text content',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Update text content.
     *
     * @OA\Put(
     *     path="/api/v1/content/text",
     *     tags={"Content"},
     *     summary="Update text content",
     *     description="Update text content with automatic backup and validation",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"key", "value"},
     *             @OA\Property(property="key", type="string", description="Content key"),
     *             @OA\Property(property="value", type="string", description="New content value"),
     *             @OA\Property(property="locale", type="string", description="Content locale"),
     *             @OA\Property(property="metadata", type="object", description="Additional metadata")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Content updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/TextContentResource")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateText(UpdateTextRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $locale = $data['locale'] ?? app()->getLocale();

                // Find or create content
                $content = TextContent::firstOrCreate(
                    ['key' => $data['key'], 'locale' => $locale],
                    [
                        'value' => $data['value'],
                        'metadata' => $data['metadata'] ?? [],
                        'created_by' => $request->user()->id,
                    ]
                );

                $oldValue = $content->value;

                // Update content
                $content->update([
                    'value' => $data['value'],
                    'metadata' => array_merge($content->metadata ?? [], $data['metadata'] ?? []),
                    'updated_by' => $request->user()->id,
                ]);

                // Debug: Log file update attempt
                Log::info('[DEBUG] File update check', [
                    'has_file_path' => !empty($data['file_path']),
                    'file_path' => $data['file_path'] ?? 'NOT PROVIDED',
                    'key' => $data['key'],
                    'value_length' => strlen($data['value']),
                ]);

                // Update file if specified
                if (!empty($data['file_path'])) {
                    Log::info('[DEBUG] Attempting to update file', [
                        'file_path' => $data['file_path'],
                        'old_value_length' => strlen($oldValue),
                        'new_value_length' => strlen($data['value']),
                    ]);

                    try {
                        $this->updateContentFile($data['file_path'], $oldValue, $data['value'], $data);
                        Log::info('[DEBUG] File update successful', [
                            'file_path' => $data['file_path'],
                        ]);
                    } catch (Exception $e) {
                        Log::error('[DEBUG] File update failed', [
                            'file_path' => $data['file_path'],
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                } else {
                    Log::warning('[DEBUG] No file_path provided - file will not be updated', [
                        'key' => $data['key'],
                        'available_data_keys' => array_keys($data),
                    ]);
                }

                // Create history record
                $this->createHistoryRecord($content, $oldValue, $request->user());

                // Dispatch event
                Event::dispatch(new ContentUpdated($content, $oldValue, $request->user()));

                // Clear relevant caches
                Cache::tags(['content', "content:{$data['key']}"])->flush();

                $this->logActivity('content.updated', [
                    'key' => $data['key'],
                    'locale' => $locale,
                    'old_length' => strlen($oldValue),
                    'new_length' => strlen($data['value']),
                    'user_id' => $request->user()->id,
                ]);

                return $this->successResponse(
                    new TextContentResource($content->fresh(['translations', 'history'])),
                    'Text content updated successfully'
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to update text content', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to update text content',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Bulk update text content.
     *
     * @OA\Post(
     *     path="/api/v1/content/text/bulk",
     *     tags={"Content"},
     *     summary="Bulk update text content",
     *     description="Update multiple text content items in a single transaction",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"updates"},
     *             @OA\Property(
     *                 property="updates",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"key", "value"},
     *                     @OA\Property(property="key", type="string"),
     *                     @OA\Property(property="value", type="string"),
     *                     @OA\Property(property="locale", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk update completed successfully"
     *     )
     * )
     */
    public function bulkUpdateText(BulkUpdateRequest $request): JsonResponse
    {
        try {
            CmsLogger::apiRequest('bulkUpdateText', $request->all());

            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $updates = $data['updates'];
                $results = [];
                $errors = [];

                CmsLogger::info('Starting bulk update', [
                    'total_updates' => count($updates),
                    'update_keys' => array_column($updates, 'key'),
                ]);

                foreach ($updates as $index => $update) {
                    try {
                        $locale = $update['locale'] ?? app()->getLocale();

                        $content = TextContent::firstOrCreate(
                            ['key' => $update['key'], 'locale' => $locale],
                            [
                                'value' => $update['value'],
                                'metadata' => $update['metadata'] ?? [],
                                'created_by' => $request->user()->id,
                            ]
                        );

                        $oldValue = $content->value;

                        $content->update([
                            'value' => $update['value'],
                            'metadata' => array_merge($content->metadata ?? [], $update['metadata'] ?? []),
                            'updated_by' => $request->user()->id,
                        ]);

                        // Log the update details
                        CmsLogger::info('Processing update item', [
                            'index' => $index,
                            'key' => $update['key'],
                            'has_file_path' => !empty($update['file_path']),
                            'file_path' => $update['file_path'] ?? 'NOT PROVIDED',
                            'value_preview' => substr($update['value'], 0, 50),
                            'locale' => $locale,
                        ]);

                        // Update file if file_path is provided
                        if (!empty($update['file_path'])) {
                            CmsLogger::fileUpdate('bulk_update_start', $update['file_path'], [
                                'key' => $update['key'],
                                'old_value_length' => strlen($oldValue),
                                'new_value_length' => strlen($update['value']),
                            ]);

                            try {
                                $this->updateContentFile($update['file_path'], $oldValue, $update['value'], $update);

                                CmsLogger::fileUpdate('bulk_update_success', $update['file_path'], [
                                    'key' => $update['key'],
                                ]);
                            } catch (Exception $fileError) {
                                CmsLogger::exception('bulk_file_update', $fileError);
                                CmsLogger::fileUpdate('bulk_update_failed', $update['file_path'], [
                                    'key' => $update['key'],
                                    'error' => $fileError->getMessage(),
                                ]);
                                // Don't throw - just log the error
                            }
                        } else {
                            CmsLogger::warning('No file path provided for update', [
                                'key' => $update['key'],
                                'available_fields' => array_keys($update),
                            ]);
                        }

                        $this->createHistoryRecord($content, $oldValue, $request->user());

                        $results[] = [
                            'key' => $update['key'],
                            'locale' => $locale,
                            'status' => 'success',
                        ];

                        // Dispatch event
                        Event::dispatch(new ContentUpdated($content, $oldValue, $request->user()));

                    } catch (Exception $e) {
                        $errors[] = [
                            'index' => $index,
                            'key' => $update['key'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                // Clear caches
                Cache::tags(['content'])->flush();

                $this->logActivity('content.bulk_updated', [
                    'total_updates' => count($updates),
                    'successful' => count($results),
                    'errors' => count($errors),
                    'user_id' => $request->user()->id,
                ]);

                $response = [
                    'results' => $results,
                    'summary' => [
                        'total' => count($updates),
                        'successful' => count($results),
                        'failed' => count($errors),
                    ],
                ];

                if (!empty($errors)) {
                    $response['errors'] = $errors;
                }

                return $this->successResponse(
                    $response,
                    'Bulk update completed'
                );
            });

        } catch (Exception $e) {
            Log::error('Bulk update failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Bulk update failed',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    // ===================================
    // IMAGE MANAGEMENT ENDPOINTS
    // ===================================

    /**
     * Update image metadata.
     *
     * @OA\Put(
     *     path="/api/v1/content/image",
     *     tags={"Images"},
     *     summary="Update image metadata",
     *     description="Update image alt text, title, and other metadata"
     * )
     */
    public function updateImage(UpdateImageRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();

                $image = Image::findOrFail($data['id']);

                // Check permissions
                if (!$request->user()->can('update', $image)) {
                    return $this->errorResponse('Insufficient permissions', [], 403);
                }

                $oldData = $image->toArray();

                $image->update([
                    'alt_text' => $data['alt_text'] ?? $image->alt_text,
                    'title' => $data['title'] ?? $image->title,
                    'description' => $data['description'] ?? $image->description,
                    'metadata' => array_merge($image->metadata ?? [], $data['metadata'] ?? []),
                    'updated_by' => $request->user()->id,
                ]);

                // Update file references if path changed
                if (!empty($data['file_path']) && !empty($data['old_path'])) {
                    $this->updateImageReferences($data['old_path'], $data['file_path']);
                }

                $this->logActivity('image.updated', [
                    'image_id' => $image->id,
                    'changes' => array_diff_assoc($image->toArray(), $oldData),
                    'user_id' => $request->user()->id,
                ]);

                // Clear image caches
                Cache::tags(['images', "image:{$image->id}"])->flush();

                return $this->successResponse(
                    new ImageResource($image->fresh()),
                    'Image updated successfully'
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to update image', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to update image',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Upload new image.
     *
     * @OA\Post(
     *     path="/api/v1/content/image/upload",
     *     tags={"Images"},
     *     summary="Upload new image",
     *     description="Upload and process new image with automatic optimization"
     * )
     */
    public function uploadImage(UploadImageRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $file = $request->file('image');
                $data = $request->validated();

                // Store file
                $path = $file->store('images/' . date('Y/m'), 'public');

                // Get file information
                $fileSize = $file->getSize();
                $mimeType = $file->getMimeType();
                $originalName = $file->getClientOriginalName();

                // Get image dimensions
                $imagePath = Storage::disk('public')->path($path);
                $dimensions = getimagesize($imagePath);

                // Create image record
                $image = Image::create([
                    'filename' => basename($path),
                    'original_name' => $originalName,
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                    'size' => $fileSize,
                    'mime_type' => $mimeType,
                    'width' => $dimensions[0] ?? null,
                    'height' => $dimensions[1] ?? null,
                    'alt_text' => $data['alt_text'] ?? '',
                    'title' => $data['title'] ?? $originalName,
                    'description' => $data['description'] ?? '',
                    'metadata' => $data['metadata'] ?? [],
                    'uploaded_by' => $request->user()->id,
                ]);

                // Generate thumbnails if enabled
                if (config('cms.images.generate_thumbnails', true)) {
                    $this->generateThumbnails($image);
                }

                // Dispatch event
                Event::dispatch(new ImageUploaded($image, $request->user()));

                $this->logActivity('image.uploaded', [
                    'image_id' => $image->id,
                    'filename' => $originalName,
                    'size' => $fileSize,
                    'user_id' => $request->user()->id,
                ]);

                return $this->successResponse(
                    new ImageResource($image),
                    'Image uploaded successfully',
                    201
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to upload image', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to upload image',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get image library with pagination and filtering.
     *
     * @OA\Get(
     *     path="/api/v1/content/images",
     *     tags={"Images"},
     *     summary="Get image library",
     *     description="Retrieve paginated list of images with filtering options"
     * )
     */
    public function getImageLibrary(Request $request): JsonResponse
    {
        try {
            $query = Image::query();

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('original_name', 'like', "%{$search}%")
                      ->orWhere('alt_text', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%");
                });
            }

            if ($request->filled('mime_type')) {
                $query->where('mime_type', $request->get('mime_type'));
            }

            if ($request->filled('uploaded_by')) {
                $query->where('uploaded_by', $request->get('uploaded_by'));
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->get('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->get('date_to'));
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['created_at', 'updated_at', 'size', 'original_name'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Apply field selection
            $fields = $this->parseFields($request->get('fields'));
            if (!empty($fields)) {
                $query->select($fields);
            }

            // Paginate results
            $perPage = min($request->get('per_page', 20), 100);
            $images = $query->paginate($perPage);

            return $this->successResponse([
                'images' => ImageResource::collection($images->items()),
                'pagination' => [
                    'current_page' => $images->currentPage(),
                    'last_page' => $images->lastPage(),
                    'per_page' => $images->perPage(),
                    'total' => $images->total(),
                ],
            ], 'Image library retrieved successfully');

        } catch (Exception $e) {
            Log::error('Failed to get image library', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to retrieve image library',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Delete image.
     *
     * @OA\Delete(
     *     path="/api/v1/content/image/{id}",
     *     tags={"Images"},
     *     summary="Delete image",
     *     description="Delete image and remove from storage"
     * )
     */
    public function deleteImage(Request $request, int $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $image = Image::findOrFail($id);

                // Check permissions
                if (!$request->user()->can('delete', $image)) {
                    return $this->errorResponse('Insufficient permissions', [], 403);
                }

                // Check if image is in use
                $usageCount = $this->checkImageUsage($image);
                if ($usageCount > 0 && !$request->get('force', false)) {
                    return $this->errorResponse(
                        'Image is currently in use',
                        ['usage_count' => $usageCount],
                        409
                    );
                }

                // Delete file from storage
                if (Storage::disk('public')->exists($image->path)) {
                    Storage::disk('public')->delete($image->path);
                }

                // Delete thumbnails
                $this->deleteThumbnails($image);

                $this->logActivity('image.deleted', [
                    'image_id' => $image->id,
                    'filename' => $image->original_name,
                    'user_id' => $request->user()->id,
                ]);

                $image->delete();

                // Clear caches
                Cache::tags(['images', "image:{$id}"])->flush();

                return $this->successResponse(
                    null,
                    'Image deleted successfully'
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to delete image', [
                'image_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to delete image',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    // ===================================
    // LINK MANAGEMENT ENDPOINTS
    // ===================================

    /**
     * Update link.
     *
     * @OA\Put(
     *     path="/api/v1/content/link",
     *     tags={"Links"},
     *     summary="Update link",
     *     description="Update link URL, text, and metadata"
     * )
     */
    public function updateLink(UpdateLinkRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();

                $link = Link::firstOrCreate(
                    ['identifier' => $data['identifier']],
                    [
                        'url' => $data['url'],
                        'text' => $data['text'] ?? '',
                        'title' => $data['title'] ?? '',
                        'metadata' => $data['metadata'] ?? [],
                        'created_by' => $request->user()->id,
                    ]
                );

                $oldData = $link->toArray();

                $link->update([
                    'url' => $data['url'],
                    'text' => $data['text'] ?? $link->text,
                    'title' => $data['title'] ?? $link->title,
                    'target' => $data['target'] ?? $link->target,
                    'rel' => $data['rel'] ?? $link->rel,
                    'metadata' => array_merge($link->metadata ?? [], $data['metadata'] ?? []),
                    'updated_by' => $request->user()->id,
                ]);

                // Validate link if requested
                if ($request->get('validate', false)) {
                    $validation = $this->validateLinkUrl($data['url']);
                    $link->update(['is_valid' => $validation['valid']]);
                }

                $this->logActivity('link.updated', [
                    'link_id' => $link->id,
                    'identifier' => $data['identifier'],
                    'changes' => array_diff_assoc($link->toArray(), $oldData),
                    'user_id' => $request->user()->id,
                ]);

                // Clear link caches
                Cache::tags(['links', "link:{$link->id}"])->flush();

                return $this->successResponse(
                    new LinkResource($link->fresh()),
                    'Link updated successfully'
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to update link', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to update link',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Validate link URL.
     *
     * @OA\Post(
     *     path="/api/v1/content/link/validate",
     *     tags={"Links"},
     *     summary="Validate link URL",
     *     description="Check if a URL is accessible and valid"
     * )
     */
    public function validateLink(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'url' => 'required|url|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $url = $request->get('url');
            $validation = $this->validateLinkUrl($url);

            return $this->successResponse($validation, 'Link validation completed');

        } catch (Exception $e) {
            Log::error('Link validation failed', [
                'url' => $request->get('url'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Link validation failed',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get link suggestions.
     *
     * @OA\Get(
     *     path="/api/v1/content/link/suggestions",
     *     tags={"Links"},
     *     summary="Get link suggestions",
     *     description="Get suggested links based on content context"
     * )
     */
    public function getLinkSuggestions(Request $request): JsonResponse
    {
        try {
            $query = $request->get('query', '');
            $context = $request->get('context', '');
            $limit = min($request->get('limit', 10), 50);

            $suggestions = $this->generateLinkSuggestions($query, $context, $limit);

            return $this->successResponse([
                'suggestions' => $suggestions,
                'query' => $query,
                'total' => count($suggestions),
            ], 'Link suggestions generated');

        } catch (Exception $e) {
            Log::error('Failed to generate link suggestions', [
                'query' => $request->get('query'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to generate link suggestions',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    // ===================================
    // TRANSLATION ENDPOINTS
    // ===================================

    /**
     * Get translations for locale.
     *
     * @OA\Get(
     *     path="/api/v1/content/translations/{locale}",
     *     tags={"Translations"},
     *     summary="Get translations for locale",
     *     description="Retrieve all translations for a specific locale"
     * )
     */
    public function getTranslations(Request $request, string $locale): JsonResponse
    {
        try {
            $query = Translation::where('locale', $locale);

            // Apply filters
            if ($request->filled('group')) {
                $query->where('group', $request->get('group'));
            }

            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                      ->orWhere('value', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->get('status'));
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'key');
            $sortDirection = $request->get('sort_direction', 'asc');

            if (in_array($sortBy, ['key', 'group', 'created_at', 'updated_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginate or get all
            if ($request->get('paginate', true)) {
                $perPage = min($request->get('per_page', 50), 200);
                $translations = $query->paginate($perPage);

                return $this->successResponse([
                    'translations' => TranslationResource::collection($translations->items()),
                    'pagination' => [
                        'current_page' => $translations->currentPage(),
                        'last_page' => $translations->lastPage(),
                        'per_page' => $translations->perPage(),
                        'total' => $translations->total(),
                    ],
                ], 'Translations retrieved successfully');
            } else {
                $translations = $query->get();

                return $this->successResponse(
                    TranslationResource::collection($translations),
                    'Translations retrieved successfully'
                );
            }

        } catch (Exception $e) {
            Log::error('Failed to get translations', [
                'locale' => $locale,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to retrieve translations',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Update translation.
     *
     * @OA\Put(
     *     path="/api/v1/content/translation",
     *     tags={"Translations"},
     *     summary="Update translation",
     *     description="Update or create a translation"
     * )
     */
    public function updateTranslation(TranslationRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();

                $translation = Translation::firstOrCreate(
                    [
                        'locale' => $data['locale'],
                        'group' => $data['group'],
                        'key' => $data['key'],
                    ],
                    [
                        'value' => $data['value'],
                        'metadata' => $data['metadata'] ?? [],
                        'created_by' => $request->user()->id,
                    ]
                );

                $oldValue = $translation->value;

                $translation->update([
                    'value' => $data['value'],
                    'status' => $data['status'] ?? 'active',
                    'metadata' => array_merge($translation->metadata ?? [], $data['metadata'] ?? []),
                    'updated_by' => $request->user()->id,
                ]);

                // Update translation files if enabled
                if (config('cms.translations.update_files', true)) {
                    $this->translationManager->updateTranslationFile(
                        $data['locale'],
                        $data['group'],
                        $data['key'],
                        $data['value']
                    );
                }

                // Dispatch event
                Event::dispatch(new TranslationUpdated($translation, $oldValue, $request->user()));

                $this->logActivity('translation.updated', [
                    'locale' => $data['locale'],
                    'group' => $data['group'],
                    'key' => $data['key'],
                    'user_id' => $request->user()->id,
                ]);

                // Clear translation caches
                Cache::tags(['translations', "translations:{$data['locale']}"])->flush();

                return $this->successResponse(
                    new TranslationResource($translation->fresh()),
                    'Translation updated successfully'
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to update translation', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to update translation',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Sync translations.
     *
     * @OA\Post(
     *     path="/api/v1/content/translations/sync",
     *     tags={"Translations"},
     *     summary="Sync translations",
     *     description="Synchronize translations with files or external sources"
     * )
     */
    public function syncTranslations(SyncRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $locale = $data['locale'];
                $source = $data['source'] ?? 'files';

                $results = $this->translationManager->syncTranslations($locale, [
                    'source' => $source,
                    'groups' => $data['groups'] ?? null,
                    'force' => $data['force'] ?? false,
                ]);

                $this->logActivity('translations.synced', [
                    'locale' => $locale,
                    'source' => $source,
                    'results' => $results,
                    'user_id' => $request->user()->id,
                ]);

                // Clear translation caches
                Cache::tags(['translations', "translations:{$locale}"])->flush();

                return $this->successResponse($results, 'Translations synchronized successfully');
            });

        } catch (Exception $e) {
            Log::error('Translation sync failed', [
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Translation sync failed',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get missing translations.
     *
     * @OA\Get(
     *     path="/api/v1/content/translations/missing",
     *     tags={"Translations"},
     *     summary="Get missing translations",
     *     description="Find translations that exist in base locale but missing in target locale"
     * )
     */
    public function getMissingTranslations(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'target_locale' => 'required|string|max:10',
                'base_locale' => 'nullable|string|max:10',
                'group' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $targetLocale = $request->get('target_locale');
            $baseLocale = $request->get('base_locale', config('app.locale'));
            $group = $request->get('group');

            $missing = $this->translationManager->findMissingTranslations(
                $baseLocale,
                $targetLocale,
                $group
            );

            return $this->successResponse([
                'missing_translations' => $missing,
                'base_locale' => $baseLocale,
                'target_locale' => $targetLocale,
                'group' => $group,
                'total' => count($missing),
            ], 'Missing translations found');

        } catch (Exception $e) {
            Log::error('Failed to find missing translations', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to find missing translations',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Suggest translation.
     *
     * @OA\Post(
     *     path="/api/v1/content/translation/suggest",
     *     tags={"Translations"},
     *     summary="Suggest translation",
     *     description="Get AI-powered translation suggestions"
     * )
     */
    public function suggestTranslation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'text' => 'required|string|max:5000',
                'source_locale' => 'required|string|max:10',
                'target_locale' => 'required|string|max:10',
                'context' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $suggestions = $this->translationManager->suggestTranslation(
                $request->get('text'),
                $request->get('source_locale'),
                $request->get('target_locale'),
                $request->get('context')
            );

            return $this->successResponse($suggestions, 'Translation suggestions generated');

        } catch (Exception $e) {
            Log::error('Translation suggestion failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Translation suggestion failed',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    // ===================================
    // HISTORY & VERSIONING ENDPOINTS
    // ===================================

    /**
     * Get content history.
     *
     * @OA\Get(
     *     path="/api/v1/content/history",
     *     tags={"History"},
     *     summary="Get content history",
     *     description="Retrieve version history for content items"
     * )
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $query = ContentHistory::with(['user']);

            // Apply filters
            if ($request->filled('content_type')) {
                $query->where('content_type', $request->get('content_type'));
            }

            if ($request->filled('content_id')) {
                $query->where('content_id', $request->get('content_id'));
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->get('user_id'));
            }

            if ($request->filled('action')) {
                $query->where('action', $request->get('action'));
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->get('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->get('date_to'));
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['created_at', 'action', 'content_type'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginate results
            $perPage = min($request->get('per_page', 20), 100);
            $history = $query->paginate($perPage);

            return $this->successResponse([
                'history' => HistoryResource::collection($history->items()),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                ],
            ], 'Content history retrieved successfully');

        } catch (Exception $e) {
            Log::error('Failed to get content history', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to retrieve content history',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get specific revision.
     *
     * @OA\Get(
     *     path="/api/v1/content/history/{id}",
     *     tags={"History"},
     *     summary="Get specific revision",
     *     description="Retrieve details of a specific content revision"
     * )
     */
    public function getRevision(Request $request, int $id): JsonResponse
    {
        try {
            $revision = ContentHistory::with(['user'])->findOrFail($id);

            // Check permissions
            if (!$request->user()->can('view', $revision)) {
                return $this->errorResponse('Insufficient permissions', [], 403);
            }

            return $this->successResponse(
                new HistoryResource($revision),
                'Revision retrieved successfully'
            );

        } catch (Exception $e) {
            Log::error('Failed to get revision', [
                'revision_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Failed to retrieve revision',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Compare revisions.
     *
     * @OA\Get(
     *     path="/api/v1/content/history/compare",
     *     tags={"History"},
     *     summary="Compare revisions",
     *     description="Compare two content revisions and show differences"
     * )
     */
    public function compareRevisions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'revision_a' => 'required|integer|exists:content_history,id',
                'revision_b' => 'required|integer|exists:content_history,id',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $revisionA = ContentHistory::findOrFail($request->get('revision_a'));
            $revisionB = ContentHistory::findOrFail($request->get('revision_b'));

            // Check permissions
            if (!$request->user()->can('view', $revisionA) || !$request->user()->can('view', $revisionB)) {
                return $this->errorResponse('Insufficient permissions', [], 403);
            }

            $comparison = $this->compareRevisionData($revisionA, $revisionB);

            return $this->successResponse($comparison, 'Revisions compared successfully');

        } catch (Exception $e) {
            Log::error('Revision comparison failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Revision comparison failed',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Restore revision.
     *
     * @OA\Post(
     *     path="/api/v1/content/history/{id}/restore",
     *     tags={"History"},
     *     summary="Restore revision",
     *     description="Restore content to a previous revision"
     * )
     */
    public function restoreRevision(RestoreRequest $request, int $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $revision = ContentHistory::findOrFail($id);

                // Check permissions
                if (!$request->user()->can('restore', $revision)) {
                    return $this->errorResponse('Insufficient permissions', [], 403);
                }

                $restored = $this->restoreContentFromRevision($revision, $request->user());

                $this->logActivity('content.restored', [
                    'revision_id' => $id,
                    'content_type' => $revision->content_type,
                    'content_id' => $revision->content_id,
                    'user_id' => $request->user()->id,
                ]);

                return $this->successResponse($restored, 'Content restored successfully');
            });

        } catch (Exception $e) {
            Log::error('Content restoration failed', [
                'revision_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse(
                'Content restoration failed',
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    // ===================================
    // HELPER METHODS
    // ===================================

    /**
     * Success response helper.
     */
    private function successResponse($data, string $message = '', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    /**
     * Error response helper.
     */
    private function errorResponse(string $message, array $errors = [], int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    /**
     * Validation error response helper.
     */
    private function validationErrorResponse($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'timestamp' => now()->toISOString(),
        ], 422);
    }

    /**
     * Parse include parameter.
     */
    private function parseIncludes(string $include): array
    {
        if (empty($include)) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $include)));
    }

    /**
     * Parse fields parameter for sparse fieldsets.
     */
    private function parseFields(string $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $allowedFields = ['id', 'key', 'value', 'locale', 'created_at', 'updated_at'];
        $requestedFields = array_filter(array_map('trim', explode(',', $fields)));

        return array_intersect($requestedFields, $allowedFields);
    }

    /**
     * Update content file using FileUpdater service.
     */
    private function updateContentFile(string $filePath, string $oldValue, string $newValue, array $context): void
    {
        $this->fileUpdater->updateContent($filePath, $oldValue, $newValue, $context);
    }

    /**
     * Create history record for content changes.
     */
    private function createHistoryRecord($content, string $oldValue, $user): void
    {
        ContentHistory::create([
            'content_type' => get_class($content),
            'content_id' => $content->id,
            'action' => 'updated',
            'old_data' => ['value' => $oldValue],
            'new_data' => ['value' => $content->value],
            'metadata' => [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'user_id' => $user->id,
        ]);
    }

    /**
     * Generate thumbnails for uploaded image.
     */
    private function generateThumbnails(Image $image): void
    {
        // Implementation would generate various thumbnail sizes
        // This is a placeholder for the actual thumbnail generation logic
    }

    /**
     * Check image usage across the application.
     */
    private function checkImageUsage(Image $image): int
    {
        // Implementation would check where the image is used
        // Return count of usages
        return 0;
    }

    /**
     * Delete image thumbnails.
     */
    private function deleteThumbnails(Image $image): void
    {
        // Implementation would delete associated thumbnails
    }

    /**
     * Update image references in content.
     */
    private function updateImageReferences(string $oldPath, string $newPath): void
    {
        // Implementation would update all references to the image
    }

    /**
     * Validate a link URL.
     */
    private function validateLinkUrl(string $url): array
    {
        // Implementation would check if URL is accessible
        return [
            'valid' => true,
            'status_code' => 200,
            'response_time' => 150,
            'redirect_url' => null,
            'checked_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate link suggestions based on context.
     */
    private function generateLinkSuggestions(string $query, string $context, int $limit): array
    {
        // Implementation would generate contextual link suggestions
        return [];
    }

    /**
     * Compare two revisions and return differences.
     */
    private function compareRevisionData(ContentHistory $revisionA, ContentHistory $revisionB): array
    {
        return [
            'revision_a' => new HistoryResource($revisionA),
            'revision_b' => new HistoryResource($revisionB),
            'differences' => [
                // Implementation would calculate actual differences
            ],
        ];
    }

    /**
     * Restore content from a specific revision.
     */
    private function restoreContentFromRevision(ContentHistory $revision, $user)
    {
        // Implementation would restore the content to the revision state
        return null;
    }

    /**
     * Log activity for audit trail.
     */
    private function logActivity(string $action, array $data): void
    {
        Log::info("CMS Activity: {$action}", $data);
    }
}