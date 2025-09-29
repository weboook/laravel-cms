<?php

namespace Webook\LaravelCMS\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Webook\LaravelCMS\Contracts\IContentScanner;

/**
 * Content Scanner Usage Examples
 *
 * This file demonstrates various ways to use the ContentScanner service
 * in your Laravel controllers and services.
 */
class ContentScannerUsage
{
    protected IContentScanner $contentScanner;

    public function __construct(IContentScanner $contentScanner)
    {
        $this->contentScanner = $contentScanner;
    }

    /**
     * Example 1: Basic page scanning
     *
     * Scan a page URL to identify all editable content elements.
     */
    public function scanPageExample(Request $request): JsonResponse
    {
        try {
            $url = $request->input('url', '/');

            // Basic page scan
            $results = $this->contentScanner->scanPage($url);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $results['url'],
                    'total_elements' => count($results['elements']),
                    'scan_duration' => $results['scan_duration'],
                    'elements' => array_slice($results['elements'], 0, 10), // First 10 elements
                ],
                'statistics' => $results['statistics'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 2: Advanced page scanning with options
     *
     * Scan with custom filters and configuration options.
     */
    public function advancedPageScanExample(Request $request): JsonResponse
    {
        $url = $request->input('url');

        $options = [
            'force_refresh' => $request->boolean('force_refresh', false),
            'filters' => [
                'include_only' => ['text_elements', 'image_elements'], // Only scan text and images
                'exclude' => ['custom_elements'], // Exclude custom CMS elements
                'min_content_length' => 5, // Minimum text length to consider
            ],
            'context' => 'admin_scan', // Context for permission checking
        ];

        try {
            $results = $this->contentScanner->scanPage($url, $options);

            // Group elements by type for easier processing
            $elementsByType = [];
            foreach ($results['elements'] as $element) {
                $type = $element['content_type'];
                $elementsByType[$type][] = $element;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_elements' => count($results['elements']),
                        'by_type' => array_map('count', $elementsByType),
                        'translation_keys' => count($results['translation_keys']),
                        'components' => [
                            'blade' => count($results['components']['blade']),
                            'livewire' => count($results['components']['livewire']),
                            'javascript' => count($results['components']['javascript']),
                        ],
                    ],
                    'elements_by_type' => $elementsByType,
                    'translation_keys' => $results['translation_keys'],
                    'components' => $results['components'],
                ],
                'metadata' => $results['metadata'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 3: HTML content scanning
     *
     * Scan raw HTML content instead of a URL.
     */
    public function scanHtmlExample(Request $request): JsonResponse
    {
        $html = $request->input('html');

        if (empty($html)) {
            return response()->json([
                'success' => false,
                'error' => 'HTML content is required',
            ], 400);
        }

        try {
            $results = $this->contentScanner->scanHtml($html, [
                'context' => 'html_editor',
                'source_url' => $request->input('source_url'),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 4: Translation key extraction
     *
     * Extract and analyze translation keys from content.
     */
    public function extractTranslationKeysExample(Request $request): JsonResponse
    {
        $content = $request->input('content');
        $locales = $request->input('locales', []);

        try {
            $translationKeys = $this->contentScanner->findTranslationKeys($content, $locales);

            // Organize by namespace
            $keysByNamespace = [];
            foreach ($translationKeys as $keyData) {
                $namespace = $keyData['namespace'] ?? 'default';
                $keysByNamespace[$namespace][] = $keyData;
            }

            // Find missing translations
            $missingTranslations = [];
            foreach ($translationKeys as $keyData) {
                foreach ($locales as $locale) {
                    if (!($keyData['locales_available'][$locale] ?? false)) {
                        $missingTranslations[] = [
                            'key' => $keyData['key'],
                            'locale' => $locale,
                            'line_number' => $keyData['line_number'],
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_keys' => count($translationKeys),
                    'by_namespace' => array_map('count', $keysByNamespace),
                    'missing_translations' => $missingTranslations,
                    'keys_by_namespace' => $keysByNamespace,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 5: Inject editable markers
     *
     * Modify HTML to add CMS editing capabilities.
     */
    public function injectEditableMarkersExample(Request $request): JsonResponse
    {
        $html = $request->input('html');
        $userPermissions = $request->input('permissions', []);

        try {
            $options = [
                'include_scripts' => true,
                'user_permissions' => $userPermissions,
                'editor_theme' => $request->input('theme', 'auto'),
                'toolbar_position' => $request->input('toolbar_position', 'floating'),
            ];

            $editableHtml = $this->contentScanner->injectEditableMarkers($html, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'original_html' => $html,
                    'editable_html' => $editableHtml,
                    'options_used' => $options,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 6: Component detection
     *
     * Identify and analyze various types of components.
     */
    public function detectComponentsExample(Request $request): JsonResponse
    {
        $content = $request->input('content');

        try {
            $bladeComponents = $this->contentScanner->detectBladeComponents($content);
            $livewireComponents = $this->contentScanner->detectLivewireComponents($content);
            $jsComponents = $this->contentScanner->detectJavaScriptComponents($content);

            // Analyze component complexity
            $componentAnalysis = [
                'blade' => [
                    'total' => count($bladeComponents),
                    'by_type' => array_count_values(array_column($bladeComponents, 'type')),
                    'complex_components' => array_filter($bladeComponents, function($comp) {
                        return !empty($comp['attributes']) || !empty($comp['slot_content']);
                    }),
                ],
                'livewire' => [
                    'total' => count($livewireComponents),
                    'by_type' => array_count_values(array_column($livewireComponents, 'type')),
                    'interactive_components' => array_filter($livewireComponents, function($comp) {
                        return !empty($comp['wire_methods']);
                    }),
                ],
                'javascript' => [
                    'total' => count($jsComponents),
                    'by_framework' => array_count_values(array_column($jsComponents, 'framework')),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'analysis' => $componentAnalysis,
                    'components' => [
                        'blade' => $bladeComponents,
                        'livewire' => $livewireComponents,
                        'javascript' => $jsComponents,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 7: Differential scanning
     *
     * Compare current scan with previous results to identify changes.
     */
    public function differentialScanExample(Request $request): JsonResponse
    {
        $url = $request->input('url');
        $previousScanId = $request->input('previous_scan_id');

        try {
            // Perform current scan
            $currentResults = $this->contentScanner->scanPage($url);

            // Generate cache key for previous scan
            $cacheKey = "cms_scan_{$previousScanId}";

            // Perform differential analysis
            $diff = $this->contentScanner->performDifferentialScan($currentResults, $cacheKey);

            // Generate change summary
            $changeSummary = [
                'scan_type' => $diff['type'],
                'changes_detected' => $diff['type'] !== 'full_scan',
                'counts' => [
                    'added' => count($diff['added'] ?? []),
                    'modified' => count($diff['modified'] ?? []),
                    'removed' => count($diff['removed'] ?? []),
                    'unchanged' => count($diff['unchanged'] ?? []),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $changeSummary,
                    'changes' => $diff,
                    'current_scan_id' => md5($url . time()),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 8: Content safety validation
     *
     * Validate content for security before allowing edits.
     */
    public function validateContentSafetyExample(Request $request): JsonResponse
    {
        $content = $request->input('content');
        $userPermissions = $request->input('permissions', []);

        try {
            $validation = $this->contentScanner->validateContentSafety($content, $userPermissions);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_safe' => $validation['is_safe'],
                    'can_edit' => $validation['is_safe'] ||
                                 in_array('cms.edit.unsafe_content', $userPermissions),
                    'issues' => $validation['issues'],
                    'permissions_required' => $validation['permissions_required'],
                    'recommendations' => $validation['recommendations'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 9: Performance monitoring
     *
     * Monitor scanner performance and statistics.
     */
    public function getPerformanceStatsExample(): JsonResponse
    {
        $statistics = $this->contentScanner->getScanStatistics();

        // Calculate performance metrics
        $metrics = [
            'memory_usage' => [
                'current' => $statistics['current_memory_usage'],
                'peak' => $statistics['memory_peak'],
                'formatted' => [
                    'current' => $this->formatBytes($statistics['current_memory_usage']),
                    'peak' => $this->formatBytes($statistics['memory_peak']),
                ],
            ],
            'cache_performance' => [
                'hit_ratio' => $statistics['cache_hits'] > 0 ?
                    $statistics['cache_hits'] / ($statistics['cache_hits'] + $statistics['cache_misses']) : 0,
                'hits' => $statistics['cache_hits'],
                'misses' => $statistics['cache_misses'],
            ],
            'processing' => [
                'elements_processed' => $statistics['elements_processed'],
                'translation_keys_found' => $statistics['translation_keys_found'],
                'components_detected' => $statistics['components_detected'],
                'duration' => $statistics['duration'],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'raw_statistics' => $statistics,
            ],
        ]);
    }

    /**
     * Helper method to format bytes.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}

/**
 * Service Integration Example
 *
 * How to integrate ContentScanner with other services.
 */
class ContentScannerServiceIntegration
{
    protected IContentScanner $contentScanner;

    public function __construct(IContentScanner $contentScanner)
    {
        $this->contentScanner = $contentScanner;
    }

    /**
     * Integration with content management.
     */
    public function scanAndSaveContent(string $url): array
    {
        // Scan the page
        $scanResults = $this->contentScanner->scanPage($url);

        // Process elements for storage
        $processedElements = [];
        foreach ($scanResults['elements'] as $element) {
            $processedElements[] = [
                'cms_id' => $element['id'],
                'content_type' => $element['content_type'],
                'original_content' => $element['text_content'],
                'html_content' => $element['html_content'],
                'source_mapping' => $element['source_mapping'],
                'edit_permissions' => $element['edit_permissions'],
                'cache_key' => $element['cache_key'],
                'created_at' => now(),
            ];
        }

        // Here you would save to database
        // Content::insert($processedElements);

        return [
            'processed_elements' => count($processedElements),
            'translation_keys' => count($scanResults['translation_keys']),
            'components' => array_sum(array_map('count', $scanResults['components'])),
        ];
    }

    /**
     * Integration with caching system.
     */
    public function getCachedOrScanPage(string $url, bool $forceRefresh = false): array
    {
        $cacheKey = "page_scan_" . md5($url);

        if (!$forceRefresh) {
            $cached = cache()->get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $results = $this->contentScanner->scanPage($url);

        // Cache results for 1 hour
        cache()->put($cacheKey, $results, 3600);

        return $results;
    }
}