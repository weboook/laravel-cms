<?php

namespace Webook\LaravelCMS\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Exception;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Webook\LaravelCMS\Contracts\IContentScanner;

/**
 * Sophisticated Content Scanner Service
 *
 * This service provides comprehensive HTML content analysis and parsing capabilities
 * for the Laravel CMS. It can identify editable elements, trace content back to source
 * files, detect various types of dynamic content, and optimize scanning performance
 * through intelligent caching and differential scanning.
 *
 * Key Features:
 * - Advanced HTML parsing with DOM manipulation
 * - Source file mapping and line number tracking
 * - Multi-framework component detection (Blade, Livewire, Vue, Alpine)
 * - Translation key extraction and management
 * - Smart caching with differential scanning
 * - Performance optimization for large pages
 * - Comprehensive error handling and logging
 *
 * Performance Considerations:
 * - Uses DOMDocument for efficient HTML parsing
 * - Implements result caching to avoid re-parsing unchanged content
 * - Supports differential scanning to process only changed elements
 * - Memory-efficient processing of large HTML documents
 * - Lazy loading of heavy operations until needed
 */
class ContentScanner implements IContentScanner
{
    use ContentScannerExtensions, ContentScannerHelpers;
    /**
     * @var Filesystem File system for source file access
     */
    protected Filesystem $files;

    /**
     * @var CacheRepository Cache repository for storing scan results
     */
    protected CacheRepository $cache;

    /**
     * @var HttpFactory HTTP client for fetching remote content
     */
    protected HttpFactory $http;

    /**
     * @var array Configuration settings for the scanner
     */
    protected array $config;

    /**
     * @var array Statistics collected during scanning process
     */
    protected array $statistics = [
        'scan_start_time' => null,
        'scan_end_time' => null,
        'elements_processed' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'translation_keys_found' => 0,
        'components_detected' => 0,
        'memory_peak' => 0,
    ];

    /**
     * @var array Custom element type plugins
     */
    protected array $plugins = [];

    /**
     * Create a new Content Scanner instance.
     *
     * @param Filesystem $files
     * @param CacheRepository $cache
     * @param HttpFactory $http
     * @param array $config
     */
    public function __construct(
        Filesystem $files,
        CacheRepository $cache,
        HttpFactory $http,
        array $config = []
    ) {
        $this->files = $files;
        $this->cache = $cache;
        $this->http = $http;
        $this->config = array_merge($this->getDefaultConfig(), $config);

        $this->initializeStatistics();
    }

    /**
     * Scan a complete page by URL to identify all editable content.
     *
     * This method handles both local routes and external URLs, with proper
     * error handling and performance optimization through caching.
     *
     * @param string $url The URL to scan
     * @param array $options Scanning options
     * @return array Comprehensive scan results
     * @throws InvalidArgumentException If URL is invalid
     * @throws Exception If page cannot be accessed
     */
    public function scanPage(string $url, array $options = []): array
    {
        $this->startScan();

        try {
            // Validate and normalize URL
            $normalizedUrl = $this->normalizeUrl($url);

            // Generate cache key for this specific scan
            $cacheKey = $this->generateCacheKey('page_scan', $normalizedUrl, $options);

            // Check cache first if enabled
            if ($this->isCacheEnabled() && !($options['force_refresh'] ?? false)) {
                $cached = $this->getCachedResults($cacheKey);
                if ($cached !== null) {
                    $this->statistics['cache_hits']++;
                    return $cached;
                }
            }

            $this->statistics['cache_misses']++;

            // Fetch page content
            $html = $this->fetchPageContent($normalizedUrl, $options);

            // Scan the HTML content
            $scanResults = $this->scanHtml($html, array_merge($options, [
                'source_url' => $normalizedUrl,
                'context' => 'page_scan'
            ]));

            // Add page-specific metadata
            $results = [
                'url' => $normalizedUrl,
                'scan_timestamp' => now()->toISOString(),
                'scan_duration' => $this->getScanDuration(),
                'content_hash' => md5($html),
                'elements' => $scanResults['elements'] ?? [],
                'metadata' => array_merge($scanResults['metadata'] ?? [], [
                    'page_title' => $this->extractPageTitle($html),
                    'meta_description' => $this->extractMetaDescription($html),
                    'content_length' => strlen($html),
                ]),
                'statistics' => $this->getStatisticsSnapshot(),
            ];

            // Cache results if enabled
            if ($this->isCacheEnabled()) {
                $this->cacheResults($results, $cacheKey);
            }

            return $results;

        } catch (Throwable $e) {
            Log::error('ContentScanner: Page scan failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception("Failed to scan page: {$e->getMessage()}", 0, $e);
        } finally {
            $this->endScan();
        }
    }

    /**
     * Scan HTML content to identify editable elements.
     *
     * This is the core scanning method that processes raw HTML and extracts
     * all editable elements with their comprehensive metadata.
     *
     * @param string $html The HTML content to scan
     * @param array $options Scanning options and filters
     * @return array Array of editable elements with metadata
     * @throws InvalidArgumentException If HTML is invalid
     */
    public function scanHtml(string $html, array $options = []): array
    {
        if (empty(trim($html))) {
            throw new InvalidArgumentException('HTML content cannot be empty');
        }

        try {
            // Create DOM document with proper error handling
            $doc = $this->createDomDocument($html);

            // Extract all potentially editable elements
            $editableElements = $this->extractEditableElements($doc, $options['filters'] ?? []);

            // Process each element to get comprehensive metadata
            $processedElements = [];
            foreach ($editableElements as $element) {
                try {
                    $elementData = $this->processElement($element, $options);
                    if ($elementData !== null) {
                        $processedElements[] = $elementData;
                        $this->statistics['elements_processed']++;
                    }
                } catch (Throwable $e) {
                    Log::warning('ContentScanner: Element processing failed', [
                        'element' => $element->nodeName,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            // Detect various types of content and components
            $translationKeys = $this->findTranslationKeys($html);
            $bladeComponents = $this->detectBladeComponents($html);
            $livewireComponents = $this->detectLivewireComponents($html);
            $jsComponents = $this->detectJavaScriptComponents($html);
            $assetReferences = $this->analyzeAssetReferences($html);

            $this->statistics['translation_keys_found'] += count($translationKeys);
            $this->statistics['components_detected'] += count($bladeComponents) + count($livewireComponents) + count($jsComponents);

            return [
                'elements' => $processedElements,
                'translation_keys' => $translationKeys,
                'components' => [
                    'blade' => $bladeComponents,
                    'livewire' => $livewireComponents,
                    'javascript' => $jsComponents,
                ],
                'assets' => $assetReferences,
                'metadata' => [
                    'total_elements' => count($processedElements),
                    'scan_options' => $options,
                    'content_hash' => md5($html),
                    'processing_time' => $this->getScanDuration(),
                ],
            ];

        } catch (Throwable $e) {
            Log::error('ContentScanner: HTML scan failed', [
                'html_length' => strlen($html),
                'error' => $e->getMessage()
            ]);

            throw new Exception("Failed to scan HTML: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Inject editable markers into HTML content.
     *
     * Modifies HTML to add CMS editing capabilities by injecting data attributes
     * and wrapper elements that enable inline editing functionality.
     *
     * @param string $html The original HTML content
     * @param array $options Injection options
     * @return string Modified HTML with editable markers
     */
    public function injectEditableMarkers(string $html, array $options = []): string
    {
        try {
            $doc = $this->createDomDocument($html);
            $xpath = new DOMXPath($doc);

            // Get scan results for this HTML
            $scanResults = $this->scanHtml($html, $options);

            foreach ($scanResults['elements'] as $elementData) {
                try {
                    $this->injectMarkersForElement($doc, $xpath, $elementData, $options);
                } catch (Throwable $e) {
                    Log::warning('ContentScanner: Marker injection failed for element', [
                        'element_id' => $elementData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add CMS initialization script if requested
            if ($options['include_scripts'] ?? true) {
                $this->addCmsInitializationScript($doc, $options);
            }

            return $this->getCleanHtml($doc);

        } catch (Throwable $e) {
            Log::error('ContentScanner: Marker injection failed', [
                'error' => $e->getMessage()
            ]);

            // Return original HTML if injection fails
            return $html;
        }
    }

    /**
     * Extract all editable elements from a DOM document.
     *
     * Identifies elements that can potentially be made editable, using
     * configurable filters and element type detection strategies.
     *
     * @param DOMDocument $doc The DOM document to analyze
     * @param array $filters Element type filters and exclusions
     * @return array Array of DOMElement objects with editable potential
     */
    public function extractEditableElements(DOMDocument $doc, array $filters = []): array
    {
        $xpath = new DOMXPath($doc);
        $editableElements = [];

        // Define selectors for different types of editable content
        $selectors = $this->getEditableSelectors($filters);

        foreach ($selectors as $selectorType => $selector) {
            try {
                $elements = $xpath->query($selector);

                foreach ($elements as $element) {
                    if ($this->isElementEditable($element, $filters)) {
                        $editableElements[] = [
                            'element' => $element,
                            'type' => $selectorType,
                            'selector' => $selector,
                        ];
                    }
                }
            } catch (Throwable $e) {
                Log::warning('ContentScanner: Selector query failed', [
                    'selector' => $selector,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $editableElements;
    }

    /**
     * Map a DOM element back to its source file and location.
     *
     * Uses various heuristics to trace elements back to their Blade templates,
     * components, or PHP source files where possible.
     *
     * @param DOMElement $element The element to trace
     * @return array Source mapping information
     */
    public function mapToSource(DOMElement $element): array
    {
        $mapping = [
            'file' => null,
            'line' => null,
            'component' => null,
            'method' => 'heuristic',
            'confidence' => 0,
            'context' => [],
        ];

        try {
            // Look for data attributes that might indicate source
            $sourceFile = $element->getAttribute('data-source-file');
            $sourceLine = $element->getAttribute('data-source-line');

            if ($sourceFile) {
                $mapping['file'] = $sourceFile;
                $mapping['line'] = $sourceLine ? (int)$sourceLine : null;
                $mapping['method'] = 'data_attribute';
                $mapping['confidence'] = 90;
                return $mapping;
            }

            // Try to map based on component structure
            $componentMapping = $this->mapElementToComponent($element);
            if ($componentMapping) {
                $mapping = array_merge($mapping, $componentMapping);
                $mapping['method'] = 'component_analysis';
                $mapping['confidence'] = 70;
                return $mapping;
            }

            // Try to map based on class names and IDs
            $heuristicMapping = $this->performHeuristicMapping($element);
            if ($heuristicMapping) {
                $mapping = array_merge($mapping, $heuristicMapping);
                $mapping['method'] = 'heuristic';
                $mapping['confidence'] = 30;
            }

        } catch (Throwable $e) {
            Log::debug('ContentScanner: Source mapping failed', [
                'element' => $element->nodeName,
                'error' => $e->getMessage()
            ]);
        }

        return $mapping;
    }

    /**
     * Find all translation keys within content.
     *
     * Scans for various Laravel translation patterns including function calls,
     * Blade directives, and JSON translation references.
     *
     * @param string $content The content to scan
     * @param array $locales Specific locales to check (optional)
     * @return array Translation keys with context and usage information
     */
    public function findTranslationKeys(string $content, array $locales = []): array
    {
        $translationKeys = [];

        // Define patterns for different translation methods
        $patterns = [
            // __('key') and __('key', ['param' => 'value'])
            'function_underscore' => [
                'pattern' => '/__\(\s*[\'"]([^\'\"]+)[\'"]\s*(?:,\s*\[[^\]]*\])?\s*\)/',
                'key_index' => 1,
            ],
            // trans('key') and trans('key', ['param' => 'value'])
            'function_trans' => [
                'pattern' => '/trans\(\s*[\'"]([^\'\"]+)[\'"]\s*(?:,\s*\[[^\]]*\])?\s*\)/',
                'key_index' => 1,
            ],
            // @lang('key') Blade directive
            'blade_lang' => [
                'pattern' => '/@lang\(\s*[\'"]([^\'\"]+)[\'"]\s*\)/',
                'key_index' => 1,
            ],
            // {{ __('key') }} in Blade
            'blade_echo' => [
                'pattern' => '/\{\{\s*__\(\s*[\'"]([^\'\"]+)[\'"]\s*\)\s*\}\}/',
                'key_index' => 1,
            ],
            // JSON translation keys in JavaScript
            'json_translation' => [
                'pattern' => '/window\._translations\[[\'"]+([^\'\"]+)[\'"]+\]/',
                'key_index' => 1,
            ],
        ];

        foreach ($patterns as $patternName => $config) {
            if (preg_match_all($config['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[$config['key_index']] as $index => $match) {
                    $key = $match[0];
                    $offset = $match[1];

                    // Calculate line number
                    $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;

                    $translationKeys[] = [
                        'key' => $key,
                        'pattern_type' => $patternName,
                        'line_number' => $lineNumber,
                        'offset' => $offset,
                        'context' => $this->extractTranslationContext($content, $offset, 50),
                        'namespace' => $this->extractTranslationNamespace($key),
                        'parameters' => $this->extractTranslationParameters($matches[0][$index][0]),
                        'locales_available' => $this->checkTranslationLocales($key, $locales),
                    ];
                }
            }
        }

        return $translationKeys;
    }

    /**
     * Detect the content type of a DOM element.
     *
     * Analyzes element structure, attributes, and content to determine
     * the most appropriate content type for CMS editing.
     *
     * @param DOMElement $element The element to analyze
     * @return string Content type identifier
     */
    public function detectContentType(DOMElement $element): string
    {
        $tagName = strtolower($element->tagName);

        // Direct tag mapping
        $directMappings = [
            'img' => 'image',
            'a' => 'link',
            'video' => 'video',
            'audio' => 'audio',
            'iframe' => 'embed',
        ];

        if (isset($directMappings[$tagName])) {
            return $directMappings[$tagName];
        }

        // Check for specific attributes or patterns
        if ($element->hasAttribute('data-livewire-component')) {
            return 'livewire_component';
        }

        if ($element->hasAttribute('x-data') || $element->hasAttribute('x-show')) {
            return 'alpine_component';
        }

        if ($element->hasAttribute('v-model') || $element->hasAttribute('v-if')) {
            return 'vue_component';
        }

        // Check class names for framework indicators
        $className = $element->getAttribute('class');
        if (Str::contains($className, ['livewire', 'wire:'])) {
            return 'livewire_component';
        }

        // Analyze content type
        $textContent = trim($element->textContent);

        if (empty($textContent)) {
            return 'container';
        }

        // Check for translation patterns
        if (preg_match('/__\(|@lang\(|trans\(/', $element->ownerDocument->saveHTML($element))) {
            return 'translation';
        }

        // Check for Blade variables
        if (preg_match('/\{\{.*\}\}/', $element->ownerDocument->saveHTML($element))) {
            return 'dynamic_content';
        }

        // Check for rich text indicators
        if (in_array($tagName, ['p', 'div', 'span', 'article', 'section'])) {
            $childElements = $element->getElementsByTagName('*');
            $hasFormattingElements = false;

            foreach ($childElements as $child) {
                if (in_array($child->tagName, ['strong', 'em', 'b', 'i', 'u', 'br'])) {
                    $hasFormattingElements = true;
                    break;
                }
            }

            return $hasFormattingElements ? 'rich_text' : 'plain_text';
        }

        // Default text content type
        return 'text';
    }

    /**
     * Get comprehensive metadata for a DOM element.
     *
     * Extracts all relevant information needed for CMS editing capabilities
     * including permissions, content type, source mapping, and contextual data.
     *
     * @param DOMElement $element The element to analyze
     * @param array $context Additional context information
     * @return array Comprehensive element metadata
     */
    public function getElementMetadata(DOMElement $element, array $context = []): array
    {
        $metadata = [
            'id' => $this->generateElementId($element),
            'tag_name' => $element->tagName,
            'content_type' => $this->detectContentType($element),
            'text_content' => trim($element->textContent),
            'html_content' => $this->getElementInnerHtml($element),
            'attributes' => $this->getElementAttributes($element),
            'position' => $this->getElementPosition($element),
            'dimensions' => $this->estimateElementDimensions($element),
            'source_mapping' => $this->mapToSource($element),
            'edit_permissions' => $this->determineEditPermissions($element, $context),
            'cache_key' => $this->generateElementCacheKey($element),
            'parent_info' => $this->getParentElementInfo($element),
            'children_count' => $element->childElementCount,
            'xpath' => $this->getElementXPath($element),
            'css_selector' => $this->generateCssSelector($element),
            'validation_rules' => $this->getValidationRules($element),
            'created_at' => now()->toISOString(),
        ];

        // Add custom plugin metadata
        foreach ($this->plugins as $plugin) {
            try {
                $pluginMetadata = $plugin->getElementMetadata($element, $metadata, $context);
                $metadata['plugins'][$plugin->getName()] = $pluginMetadata;
            } catch (Throwable $e) {
                Log::warning('ContentScanner: Plugin metadata extraction failed', [
                    'plugin' => get_class($plugin),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $metadata;
    }

    // ... [Continuing with remaining methods in next part due to length]
    // This includes detectBladeComponents, detectLivewireComponents,
    // detectJavaScriptComponents, analyzeAssetReferences, classifyLink,
    // performDifferentialScan, getScanStatistics, cacheResults,
    // validateContentSafety, and all helper methods.

    /**
     * Get default configuration for the scanner.
     *
     * @return array Default configuration settings
     */
    protected function getDefaultConfig(): array
    {
        return [
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'max_scan_depth' => 10,
            'timeout' => 30,
            'user_agent' => 'Laravel CMS Content Scanner',
            'follow_redirects' => true,
            'max_content_length' => 10 * 1024 * 1024, // 10MB
            'excluded_elements' => ['script', 'style', 'meta', 'link'],
            'included_attributes' => ['id', 'class', 'data-*', 'aria-*'],
            'translation_namespaces' => ['web', 'cms', 'admin'],
            'enable_source_mapping' => true,
            'enable_component_detection' => true,
            'enable_performance_logging' => false,
        ];
    }

    /**
     * Initialize scanning statistics.
     */
    protected function initializeStatistics(): void
    {
        $this->statistics = array_merge($this->statistics, [
            'scan_start_time' => null,
            'scan_end_time' => null,
            'elements_processed' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'translation_keys_found' => 0,
            'components_detected' => 0,
            'memory_peak' => 0,
        ]);
    }

    /**
     * Start scan timing and memory tracking.
     */
    protected function startScan(): void
    {
        $this->statistics['scan_start_time'] = microtime(true);
        $this->statistics['memory_start'] = memory_get_usage(true);
    }

    /**
     * End scan and record final statistics.
     */
    protected function endScan(): void
    {
        $this->statistics['scan_end_time'] = microtime(true);
        $this->statistics['memory_peak'] = memory_get_peak_usage(true);
    }

    /**
     * Get current scan duration in seconds.
     *
     * @return float|null
     */
    protected function getScanDuration(): ?float
    {
        if ($this->statistics['scan_start_time'] === null) {
            return null;
        }

        $endTime = $this->statistics['scan_end_time'] ?? microtime(true);
        return $endTime - $this->statistics['scan_start_time'];
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool
     */
    protected function isCacheEnabled(): bool
    {
        return $this->config['cache_enabled'] ?? true;
    }

    /**
     * Generate cache key for scan results.
     *
     * @param string $type
     * @param string $content
     * @param array $options
     * @return string
     */
    protected function generateCacheKey(string $type, string $content, array $options = []): string
    {
        $key = sprintf(
            'cms_scanner_%s_%s_%s',
            $type,
            md5($content),
            md5(serialize($options))
        );

        return $key;
    }

    /**
     * Get scan statistics snapshot.
     *
     * @return array
     */
    public function getScanStatistics(): array
    {
        return array_merge($this->statistics, [
            'current_memory_usage' => memory_get_usage(true),
            'duration' => $this->getScanDuration(),
        ]);
    }

    /**
     * Cache scan results.
     *
     * @param array $results
     * @param string $contentHash
     * @param array $metadata
     * @return string
     */
    public function cacheResults(array $results, string $contentHash, array $metadata = []): string
    {
        if (!$this->isCacheEnabled()) {
            return '';
        }

        $cacheKey = $this->generateCacheKey('results', $contentHash, $metadata);

        try {
            $this->cache->put($cacheKey, $results, $this->config['cache_ttl'] ?? 3600);
            return $cacheKey;
        } catch (Throwable $e) {
            Log::warning('ContentScanner: Failed to cache results', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Validate content safety for CMS editing.
     *
     * @param string $content
     * @param array $permissions
     * @return array
     */
    public function validateContentSafety(string $content, array $permissions = []): array
    {
        $validation = [
            'is_safe' => true,
            'issues' => [],
            'permissions_required' => [],
            'recommendations' => [],
        ];

        // Check for dangerous patterns
        $dangerousPatterns = [
            'script_tags' => '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            'javascript_events' => '/on\w+\s*=/i',
            'iframe_embeds' => '/<iframe\b/i',
            'form_elements' => '/<form\b/i',
        ];

        foreach ($dangerousPatterns as $type => $pattern) {
            if (preg_match($pattern, $content)) {
                $validation['is_safe'] = false;
                $validation['issues'][] = [
                    'type' => $type,
                    'severity' => 'high',
                    'message' => "Potentially dangerous {$type} detected",
                ];
                $validation['permissions_required'][] = 'cms.edit.unsafe_content';
            }
        }

        return $validation;
    }

    // Additional helper methods would continue here...
    // Due to length constraints, I'm showing the core structure and key methods.
    // The complete implementation would include all remaining helper methods.
}