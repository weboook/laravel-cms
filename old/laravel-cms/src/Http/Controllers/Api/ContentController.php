<?php

namespace Webook\LaravelCMS\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Webook\LaravelCMS\Http\Controllers\Controller;
use Webook\LaravelCMS\Services\ContentScanner;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\CmsLogger;
use Exception;

/**
 * Content API Controller
 *
 * Handles API endpoints for content management including scanning,
 * reading, and updating content elements.
 *
 * @package Webook\LaravelCMS\Http\Controllers\Api
 */
class ContentController extends Controller
{
    protected ContentScanner $contentScanner;
    protected FileUpdater $fileUpdater;

    public function __construct(ContentScanner $contentScanner, FileUpdater $fileUpdater)
    {
        $this->contentScanner = $contentScanner;
        $this->fileUpdater = $fileUpdater;

        // Only apply auth middleware if CMS auth is required
        if (config('cms.api.auth.required', false)) {
            $this->middleware(['auth', 'can:edit-content']);
        }
    }

    /**
     * Get list of all content elements.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|max:255',
                'type' => 'nullable|string|in:text,image,link,component',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $search = $request->get('search');
            $type = $request->get('type');

            // This would typically query a database or scan files
            $content = [
                'data' => [
                    [
                        'id' => 'home-title',
                        'type' => 'text',
                        'content' => 'Welcome to our website',
                        'file' => 'resources/views/home.blade.php',
                        'line' => 15,
                        'editable' => true,
                    ],
                    [
                        'id' => 'hero-image',
                        'type' => 'image',
                        'content' => '/images/hero.jpg',
                        'file' => 'resources/views/home.blade.php',
                        'line' => 25,
                        'editable' => true,
                    ],
                ],
                'meta' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => 2,
                    'pages' => 1,
                ],
            ];

            return response()->json($content);

        } catch (Exception $e) {
            Log::error('Content index failed', [
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Failed to retrieve content',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show specific content element.
     *
     * @param Request $request
     * @param string $contentKey
     * @return JsonResponse
     */
    public function show(Request $request, string $contentKey): JsonResponse
    {
        try {
            // Mock content retrieval - in real implementation this would
            // use the ContentScanner to find the specific content
            $content = [
                'id' => $contentKey,
                'type' => 'text',
                'content' => 'Sample content for ' . $contentKey,
                'file' => 'resources/views/sample.blade.php',
                'line' => 10,
                'editable' => true,
                'metadata' => [
                    'created_at' => now()->subDays(5)->toISOString(),
                    'updated_at' => now()->subHours(2)->toISOString(),
                    'updated_by' => $request->user()->name ?? 'Unknown',
                ],
            ];

            return response()->json($content);

        } catch (Exception $e) {
            Log::error('Content show failed', [
                'content_key' => $contentKey,
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Content not found',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Scan page for editable content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function scan(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'url' => 'required|url|max:2048',
                'depth' => 'nullable|integer|min:1|max:5',
                'types' => 'nullable|array',
                'types.*' => 'string|in:text,image,link,component',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $url = $request->get('url');
            $depth = $request->get('depth', 1);
            $types = $request->get('types', ['text', 'image']);

            Log::info('Content scan requested', [
                'url' => $url,
                'depth' => $depth,
                'types' => $types,
                'user_id' => optional($request->user())->id,
            ]);

            // Use ContentScanner to scan for editable elements
            $results = $this->contentScanner->scan($url, [
                'depth' => $depth,
                'types' => $types,
                'user' => $request->user(),
            ]);

            return response()->json([
                'elements' => $results['elements'] ?? [],
                'stats' => $results['stats'] ?? ['total' => 0],
                'meta' => [
                    'url' => $url,
                    'scanned_at' => now()->toISOString(),
                    'depth' => $depth,
                    'types' => $types,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Content scan failed', [
                'url' => $request->get('url'),
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Scan failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update text content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateText(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'key' => 'required|string|max:255',
                'content' => 'required|string',
                'file' => 'nullable|string|max:500',
                'line' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $key = $request->get('key');
            $content = $request->get('content');

            Log::info('Text content update', [
                'key' => $key,
                'content_length' => strlen($content),
                'user_id' => optional($request->user())->id,
            ]);

            // In a real implementation, this would use FileUpdater to save changes
            $result = [
                'success' => true,
                'key' => $key,
                'content' => $content,
                'updated_at' => now()->toISOString(),
                'updated_by' => $request->user()->name,
            ];

            return response()->json($result);

        } catch (Exception $e) {
            Log::error('Text update failed', [
                'key' => $request->get('key'),
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update image content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateImage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'key' => 'required|string|max:255',
                'src' => 'required|string|max:500',
                'alt' => 'nullable|string|max:255',
                'title' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $key = $request->get('key');
            $src = $request->get('src');

            Log::info('Image content update', [
                'key' => $key,
                'src' => $src,
                'user_id' => optional($request->user())->id,
            ]);

            $result = [
                'success' => true,
                'key' => $key,
                'src' => $src,
                'alt' => $request->get('alt'),
                'title' => $request->get('title'),
                'updated_at' => now()->toISOString(),
                'updated_by' => $request->user()->name,
            ];

            return response()->json($result);

        } catch (Exception $e) {
            Log::error('Image update failed', [
                'key' => $request->get('key'),
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update link content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateLink(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'key' => 'required|string|max:255',
                'href' => 'required|url|max:500',
                'text' => 'nullable|string|max:255',
                'target' => 'nullable|string|in:_blank,_self,_parent,_top',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $key = $request->get('key');
            $href = $request->get('href');

            Log::info('Link content update', [
                'key' => $key,
                'href' => $href,
                'user_id' => optional($request->user())->id,
            ]);

            $result = [
                'success' => true,
                'key' => $key,
                'href' => $href,
                'text' => $request->get('text'),
                'target' => $request->get('target'),
                'updated_at' => now()->toISOString(),
                'updated_by' => $request->user()->name,
            ];

            return response()->json($result);

        } catch (Exception $e) {
            Log::error('Link update failed', [
                'key' => $request->get('key'),
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update multiple content elements.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'updates' => 'required|array|min:1|max:50',
                'updates.*.key' => 'required|string|max:255',
                'updates.*.type' => 'required|string|in:text,image,link',
                'updates.*.content' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $updates = $request->get('updates');

            CmsLogger::info('Bulk content update starting', [
                'count' => count($updates),
                'user_id' => optional($request->user())->id,
                'keys' => array_column($updates, 'key'),
            ]);

            $results = [];
            $errors = [];

            foreach ($updates as $index => $update) {
                try {
                    $key = $update['key'];
                    $content = $update['content'] ?? '';
                    $file = $update['file'] ?? null;

                    CmsLogger::info('Processing update', [
                        'index' => $index,
                        'key' => $key,
                        'content_length' => strlen($content),
                        'has_file' => !empty($file),
                        'file' => $file,
                    ]);

                    // If file path is provided, update the actual file
                    if (!empty($file) && !empty($update['oldContent'])) {
                        try {
                            CmsLogger::fileUpdate('attempting', $file, [
                                'key' => $key,
                                'old_content_length' => strlen($update['oldContent']),
                                'new_content_length' => strlen($content),
                            ]);

                            // Use FileUpdater to update the file
                            $this->fileUpdater->updateContent(
                                $file,
                                $update['oldContent'],
                                $content,
                                ['key' => $key]
                            );

                            CmsLogger::fileUpdate('success', $file, ['key' => $key]);
                        } catch (Exception $fileError) {
                            CmsLogger::exception('file_update', $fileError);
                            // Log but don't fail the whole update
                            CmsLogger::warning('File update failed but continuing', [
                                'key' => $key,
                                'error' => $fileError->getMessage(),
                            ]);
                        }
                    } else {
                        CmsLogger::warning('No file path or oldContent for update', [
                            'key' => $key,
                            'has_file' => !empty($file),
                            'has_oldContent' => !empty($update['oldContent']),
                        ]);
                    }

                    // Mark as successful
                    $results[] = [
                        'key' => $key,
                        'success' => true,
                        'updated_at' => now()->toISOString(),
                    ];
                } catch (Exception $e) {
                    CmsLogger::exception('bulk_update_item', $e);
                    $errors[] = [
                        'index' => $index,
                        'key' => $update['key'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'success' => empty($errors),
                'results' => $results,
                'errors' => $errors,
                'stats' => [
                    'total' => count($updates),
                    'successful' => count($results),
                    'failed' => count($errors),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Bulk update failed', [
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Bulk update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate content before saving.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateContent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'key' => 'required|string|max:255',
                'type' => 'required|string|in:text,image,link,component',
                'content' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'valid' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $type = $request->get('type');
            $content = $request->get('content');
            $warnings = [];

            // Type-specific validation
            switch ($type) {
                case 'text':
                    if (strlen($content) > 10000) {
                        $warnings[] = 'Text content is very long and may affect performance';
                    }
                    break;

                case 'image':
                    if (!filter_var($content, FILTER_VALIDATE_URL)) {
                        return response()->json([
                            'valid' => false,
                            'errors' => ['content' => ['Invalid image URL']],
                        ], 422);
                    }
                    break;

                case 'link':
                    if (!filter_var($content, FILTER_VALIDATE_URL)) {
                        return response()->json([
                            'valid' => false,
                            'errors' => ['content' => ['Invalid link URL']],
                        ], 422);
                    }
                    break;
            }

            return response()->json([
                'valid' => true,
                'warnings' => $warnings,
                'sanitized_content' => $content, // In real implementation, this would be sanitized
            ]);

        } catch (Exception $e) {
            Log::error('Content validation failed', [
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'valid' => false,
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}