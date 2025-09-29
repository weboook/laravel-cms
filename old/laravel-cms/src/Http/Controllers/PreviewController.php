<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Webook\LaravelCMS\Services\PreviewService;
use Exception;

/**
 * Preview Controller
 *
 * Handles content preview functionality with CMS editing capabilities.
 * Provides URL-based content fetching with injected editing markers.
 *
 * @package Webook\LaravelCMS\Http\Controllers
 */
class PreviewController extends Controller
{
    protected PreviewService $previewService;

    public function __construct(PreviewService $previewService)
    {
        $this->previewService = $previewService;
        $this->middleware(['auth', 'can:preview-content']);
    }

    /**
     * Show preview of a specific URL with CMS editing capabilities.
     *
     * @param Request $request
     * @param string $url The URL to preview (base64 encoded or raw)
     * @return Response
     */
    public function show(Request $request, string $url)
    {
        try {
            // Decode URL if it appears to be base64 encoded
            if (!str_contains($url, '/') && !str_contains($url, '?')) {
                $decodedUrl = base64_decode($url);
                if (filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
                    $url = $decodedUrl;
                }
            }

            // Validate the URL
            $validator = Validator::make(['url' => $url], [
                'url' => 'required|url|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->view('cms::errors.invalid-url', [
                    'url' => $url,
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Check permissions
            if (!Gate::allows('preview-content')) {
                return response()->view('cms::errors.forbidden', [
                    'message' => 'Insufficient permissions to preview content',
                ], 403);
            }

            // Get request parameters
            $locale = $request->get('locale', 'en');
            $editMode = $request->boolean('edit', true);
            $injectAssets = $request->boolean('inject_assets', true);

            Log::info('Preview request', [
                'url' => $url,
                'locale' => $locale,
                'edit_mode' => $editMode,
                'user_id' => $request->user()->id,
            ]);

            // Check cache first
            $cacheKey = md5($url . $locale . ($editMode ? 'edit' : 'view') . $request->user()->id);

            if (!$request->get('nocache') && $cached = Cache::get("preview:{$cacheKey}")) {
                Log::info('Serving cached preview', ['cache_key' => $cacheKey]);
                return $this->createResponse($cached);
            }

            // Fetch content using the preview service
            $content = $this->previewService->fetchContent($url, [
                'locale' => $locale,
                'user' => $request->user(),
                'headers' => $this->buildPreviewHeaders($request),
            ]);

            // Inject editing markers if in edit mode
            if ($editMode) {
                $content = $this->previewService->injectEditingMarkers($content, [
                    'url' => $url,
                    'locale' => $locale,
                    'edit_mode' => true,
                    'inject_assets' => $injectAssets,
                ]);
            }

            // Cache the result for 5 minutes
            Cache::put("preview:{$cacheKey}", $content, now()->addMinutes(5));

            Log::info('Preview served successfully', [
                'url' => $url,
                'content_length' => strlen($content),
                'cached' => false,
            ]);

            return $this->createResponse($content);

        } catch (Exception $e) {
            Log::error('Preview failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => optional($request->user())->id,
            ]);

            return $this->handleError($e, $url);
        }
    }

    /**
     * Build headers for preview requests.
     *
     * @param Request $request
     * @return array
     */
    protected function buildPreviewHeaders(Request $request): array
    {
        return [
            'User-Agent' => 'CMS-Preview/1.0 Laravel',
            'X-CMS-Preview' => 'true',
            'X-CMS-User' => $request->user()->id,
            'X-Forwarded-For' => $request->ip(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => $request->getPreferredLanguage(['en', 'es', 'fr', 'de']),
        ];
    }

    /**
     * Create HTTP response for preview content.
     *
     * @param string $content
     * @return Response
     */
    protected function createResponse(string $content): Response
    {
        return response($content, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'X-CMS-Preview' => 'true',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Handle preview errors gracefully.
     *
     * @param Exception $e
     * @param string $url
     * @return Response
     */
    protected function handleError(Exception $e, string $url): Response
    {
        $statusCode = 500;
        $errorType = 'general';

        // Determine specific error type and status code
        if (str_contains($e->getMessage(), 'HTTP 404') || str_contains($e->getMessage(), 'not found')) {
            $statusCode = 404;
            $errorType = 'not-found';
        } elseif (str_contains($e->getMessage(), 'HTTP 403') || str_contains($e->getMessage(), 'forbidden')) {
            $statusCode = 403;
            $errorType = 'forbidden';
        } elseif (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'connection')) {
            $statusCode = 408;
            $errorType = 'timeout';
        } elseif (str_contains($e->getMessage(), 'SSL') || str_contains($e->getMessage(), 'certificate')) {
            $statusCode = 502;
            $errorType = 'ssl';
        }

        // Try to render a nice error page
        try {
            return response()->view("cms::errors.preview-{$errorType}", [
                'url' => $url,
                'error' => $e->getMessage(),
                'status' => $statusCode,
            ], $statusCode);
        } catch (Exception $viewException) {
            // Fallback to basic error response if view doesn't exist
            return response()->view('cms::errors.preview', [
                'url' => $url,
                'error' => $e->getMessage(),
                'status' => $statusCode,
            ], $statusCode);
        }
    }

    /**
     * Clear preview cache for a specific URL or all previews.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache(Request $request)
    {
        try {
            if (!Gate::allows('clear-preview-cache')) {
                return response()->json(['error' => 'Insufficient permissions'], 403);
            }

            $url = $request->get('url');

            if ($url) {
                // Clear cache for specific URL
                $patterns = [
                    "preview:" . md5($url . '*'),
                ];

                foreach ($patterns as $pattern) {
                    Cache::forget($pattern);
                }

                Log::info('Preview cache cleared for URL', ['url' => $url]);

                return response()->json([
                    'success' => true,
                    'message' => 'Preview cache cleared for URL',
                    'url' => $url,
                ]);
            } else {
                // Clear all preview cache
                Cache::flush(); // In production, you'd want to be more selective

                Log::info('All preview cache cleared');

                return response()->json([
                    'success' => true,
                    'message' => 'All preview cache cleared',
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to clear preview cache', [
                'error' => $e->getMessage(),
                'url' => $request->get('url'),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Failed to clear cache',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}