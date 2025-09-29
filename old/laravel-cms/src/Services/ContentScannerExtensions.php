<?php

namespace Webook\LaravelCMS\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use Throwable;

/**
 * Content Scanner Extensions
 *
 * This trait contains all the advanced content detection methods and helper functions
 * for the ContentScanner service. Separated for better organization and maintainability.
 */
trait ContentScannerExtensions
{
    /**
     * Detect Blade components within content.
     *
     * @param string $content
     * @return array
     */
    public function detectBladeComponents(string $content): array
    {
        $components = [];

        // Class-based components: <x-component-name :prop="value" />
        $classBasedPattern = '/<x-([a-zA-Z0-9\-\.]+)([^>]*?)(?:\/>|>(.*?)<\/x-\1>)/s';
        if (preg_match_all($classBasedPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $components[] = [
                    'type' => 'class_based',
                    'name' => $match[1][0],
                    'attributes' => $this->parseBladeAttributes($match[2][0]),
                    'slot_content' => $match[3][0] ?? null,
                    'offset' => $match[0][1],
                    'full_match' => $match[0][0],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                ];
            }
        }

        // Anonymous components: @component('component.name')
        $anonymousPattern = '/@component\([\'"]([^\'\"]+)[\'"]\)(.*?)@endcomponent/s';
        if (preg_match_all($anonymousPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $components[] = [
                    'type' => 'anonymous',
                    'name' => $match[1][0],
                    'content' => $match[2][0],
                    'offset' => $match[0][1],
                    'full_match' => $match[0][0],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                ];
            }
        }

        // Include components: @include('partial.name', ['data' => 'value'])
        $includePattern = '/@include\([\'"]([^\'\"]+)[\'"](?:,\s*(\[.*?\]))?\)/';
        if (preg_match_all($includePattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $components[] = [
                    'type' => 'include',
                    'name' => $match[1][0],
                    'data' => $match[2][0] ?? null,
                    'offset' => $match[0][1],
                    'full_match' => $match[0][0],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                ];
            }
        }

        return $components;
    }

    /**
     * Detect Livewire components and their properties.
     *
     * @param string $content
     * @return array
     */
    public function detectLivewireComponents(string $content): array
    {
        $components = [];

        // Livewire component tags: <livewire:component-name :prop="value" />
        $tagPattern = '/<livewire:([a-zA-Z0-9\-\.]+)([^>]*?)(?:\/>|>(.*?)<\/livewire:\1>)/s';
        if (preg_match_all($tagPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $components[] = [
                    'type' => 'tag',
                    'name' => $match[1][0],
                    'attributes' => $this->parseBladeAttributes($match[2][0]),
                    'slot_content' => $match[3][0] ?? null,
                    'offset' => $match[0][1],
                    'full_match' => $match[0][0],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    'wire_methods' => $this->extractWireMethods($match[0][0]),
                ];
            }
        }

        // Livewire directive: @livewire('component-name', ['prop' => 'value'])
        $directivePattern = '/@livewire\([\'"]([^\'\"]+)[\'"](?:,\s*(\[.*?\]))?\)/';
        if (preg_match_all($directivePattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $components[] = [
                    'type' => 'directive',
                    'name' => $match[1][0],
                    'parameters' => $match[2][0] ?? null,
                    'offset' => $match[0][1],
                    'full_match' => $match[0][0],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                ];
            }
        }

        // Wire methods in existing elements: wire:click="method"
        $wireMethods = $this->extractWireMethodsFromContent($content);
        foreach ($wireMethods as $method) {
            $components[] = [
                'type' => 'wire_method',
                'method' => $method['method'],
                'event' => $method['event'],
                'offset' => $method['offset'],
                'line_number' => $method['line_number'],
            ];
        }

        return $components;
    }

    /**
     * Detect Vue.js and Alpine.js components.
     *
     * @param string $content
     * @return array
     */
    public function detectJavaScriptComponents(string $content): array
    {
        $components = [];

        // Alpine.js components: x-data, x-show, x-if, etc.
        $alpineAttributes = [
            'x-data', 'x-show', 'x-if', 'x-for', 'x-on', 'x-bind', 'x-model',
            'x-text', 'x-html', 'x-init', 'x-cloak', 'x-transition'
        ];

        foreach ($alpineAttributes as $attribute) {
            $pattern = '/' . preg_quote($attribute, '/') . '=[\'"]([^\'"]*)[\'"]([^>]*>)/';
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $components[] = [
                        'type' => 'alpine',
                        'framework' => 'Alpine.js',
                        'attribute' => $attribute,
                        'value' => $match[1][0],
                        'context' => $match[2][0],
                        'offset' => $match[0][1],
                        'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    ];
                }
            }
        }

        // Vue.js components: v-model, v-if, v-for, etc.
        $vueAttributes = [
            'v-model', 'v-if', 'v-else', 'v-for', 'v-show', 'v-bind', 'v-on',
            'v-text', 'v-html', 'v-once', 'v-pre', 'v-cloak'
        ];

        foreach ($vueAttributes as $attribute) {
            $pattern = '/' . preg_quote($attribute, '/') . '=[\'"]([^\'"]*)[\'"]([^>]*>)/';
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $components[] = [
                        'type' => 'vue',
                        'framework' => 'Vue.js',
                        'attribute' => $attribute,
                        'value' => $match[1][0],
                        'context' => $match[2][0],
                        'offset' => $match[0][1],
                        'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    ];
                }
            }
        }

        // Vue component tags: <my-component :prop="value"></my-component>
        $vueComponentPattern = '/<([a-z][a-z0-9]*(?:-[a-z0-9]+)*)([^>]*?)(?:\/>|>(.*?)<\/\1>)/s';
        if (preg_match_all($vueComponentPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Only consider components with Vue-like attributes
                if (Str::contains($match[2][0], ['::', '@', 'v-'])) {
                    $components[] = [
                        'type' => 'vue_component',
                        'framework' => 'Vue.js',
                        'name' => $match[1][0],
                        'attributes' => $this->parseVueAttributes($match[2][0]),
                        'slot_content' => $match[3][0] ?? null,
                        'offset' => $match[0][1],
                        'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    ];
                }
            }
        }

        return $components;
    }

    /**
     * Analyze asset references in content.
     *
     * @param string $content
     * @return array
     */
    public function analyzeAssetReferences(string $content): array
    {
        $assets = [];

        // Laravel asset() helper
        $assetPattern = '/asset\([\'"]([^\'"]+)[\'"]\)/';
        if (preg_match_all($assetPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $assets[] = [
                    'type' => 'asset_helper',
                    'path' => $match[1][0],
                    'full_match' => $match[0][0],
                    'offset' => $match[0][1],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    'asset_type' => $this->determineAssetType($match[1][0]),
                ];
            }
        }

        // Storage facade
        $storagePattern = '/Storage::url\([\'"]([^\'"]+)[\'"]\)/';
        if (preg_match_all($storagePattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $assets[] = [
                    'type' => 'storage_url',
                    'path' => $match[1][0],
                    'full_match' => $match[0][0],
                    'offset' => $match[0][1],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    'asset_type' => $this->determineAssetType($match[1][0]),
                ];
            }
        }

        // Image src attributes
        $imgPattern = '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i';
        if (preg_match_all($imgPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $assets[] = [
                    'type' => 'image_src',
                    'url' => $match[1][0],
                    'full_match' => $match[0][0],
                    'offset' => $match[0][1],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    'asset_type' => 'image',
                    'is_external' => $this->isExternalUrl($match[1][0]),
                ];
            }
        }

        // CSS link tags
        $cssPattern = '/<link[^>]+href=[\'"]([^\'"]+\.css[^\'"]*)[\'"][^>]*>/i';
        if (preg_match_all($cssPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $assets[] = [
                    'type' => 'css_link',
                    'url' => $match[1][0],
                    'full_match' => $match[0][0],
                    'offset' => $match[0][1],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    'asset_type' => 'css',
                    'is_external' => $this->isExternalUrl($match[1][0]),
                ];
            }
        }

        // JavaScript script tags
        $jsPattern = '/<script[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i';
        if (preg_match_all($jsPattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $assets[] = [
                    'type' => 'js_script',
                    'url' => $match[1][0],
                    'full_match' => $match[0][0],
                    'offset' => $match[0][1],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                    'asset_type' => 'javascript',
                    'is_external' => $this->isExternalUrl($match[1][0]),
                ];
            }
        }

        return $assets;
    }

    /**
     * Classify a link element.
     *
     * @param DOMElement $linkElement
     * @return array
     */
    public function classifyLink(DOMElement $linkElement): array
    {
        $href = $linkElement->getAttribute('href');
        $text = trim($linkElement->textContent);

        $classification = [
            'href' => $href,
            'text' => $text,
            'type' => 'unknown',
            'is_external' => false,
            'is_secure' => false,
            'metadata' => [],
        ];

        // Classify link type
        if (Str::startsWith($href, 'mailto:')) {
            $classification['type'] = 'email';
            $classification['metadata']['email'] = substr($href, 7);
        } elseif (Str::startsWith($href, 'tel:')) {
            $classification['type'] = 'phone';
            $classification['metadata']['phone'] = substr($href, 4);
        } elseif (Str::startsWith($href, 'sms:')) {
            $classification['type'] = 'sms';
            $classification['metadata']['phone'] = substr($href, 4);
        } elseif (Str::startsWith($href, ['http://', 'https://'])) {
            $classification['type'] = 'external';
            $classification['is_external'] = true;
            $classification['is_secure'] = Str::startsWith($href, 'https://');
            $classification['metadata']['domain'] = parse_url($href, PHP_URL_HOST);
        } elseif (Str::startsWith($href, '/')) {
            $classification['type'] = 'internal_absolute';
        } elseif (Str::startsWith($href, '#')) {
            $classification['type'] = 'anchor';
            $classification['metadata']['anchor'] = substr($href, 1);
        } elseif (empty($href) || $href === '#') {
            $classification['type'] = 'placeholder';
        } else {
            $classification['type'] = 'internal_relative';
        }

        // Check for route helper patterns
        if (Str::contains($href, ['route(', 'url('])) {
            $classification['uses_laravel_helper'] = true;
        }

        // Additional attributes
        $target = $linkElement->getAttribute('target');
        if ($target) {
            $classification['metadata']['target'] = $target;
        }

        $rel = $linkElement->getAttribute('rel');
        if ($rel) {
            $classification['metadata']['rel'] = $rel;
        }

        return $classification;
    }

    /**
     * Perform differential scanning against previous results.
     *
     * @param array $currentResults
     * @param string $cacheKey
     * @return array
     */
    public function performDifferentialScan(array $currentResults, string $cacheKey): array
    {
        try {
            $previousResults = $this->cache->get($cacheKey);

            if (!$previousResults) {
                return [
                    'type' => 'full_scan',
                    'changes' => $currentResults,
                    'added' => $currentResults['elements'] ?? [],
                    'modified' => [],
                    'removed' => [],
                ];
            }

            $diff = [
                'type' => 'differential_scan',
                'added' => [],
                'modified' => [],
                'removed' => [],
                'unchanged' => [],
            ];

            // Create lookup maps for efficient comparison
            $currentMap = $this->createElementMap($currentResults['elements'] ?? []);
            $previousMap = $this->createElementMap($previousResults['elements'] ?? []);

            // Find added and modified elements
            foreach ($currentMap as $id => $element) {
                if (!isset($previousMap[$id])) {
                    $diff['added'][] = $element;
                } elseif ($this->hasElementChanged($element, $previousMap[$id])) {
                    $diff['modified'][] = [
                        'current' => $element,
                        'previous' => $previousMap[$id],
                        'changes' => $this->getElementChanges($element, $previousMap[$id]),
                    ];
                } else {
                    $diff['unchanged'][] = $element;
                }
            }

            // Find removed elements
            foreach ($previousMap as $id => $element) {
                if (!isset($currentMap[$id])) {
                    $diff['removed'][] = $element;
                }
            }

            return $diff;

        } catch (Throwable $e) {
            Log::warning('ContentScanner: Differential scan failed', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);

            return [
                'type' => 'full_scan_fallback',
                'changes' => $currentResults,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Additional helper methods for the scanner...

    /**
     * Parse Blade component attributes.
     *
     * @param string $attributeString
     * @return array
     */
    protected function parseBladeAttributes(string $attributeString): array
    {
        $attributes = [];
        $pattern = '/([a-zA-Z0-9\-_:]+)=([\'"])([^\2]*?)\2/';

        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[3];
            }
        }

        return $attributes;
    }

    /**
     * Parse Vue.js component attributes.
     *
     * @param string $attributeString
     * @return array
     */
    protected function parseVueAttributes(string $attributeString): array
    {
        $attributes = [];
        // Vue attributes can include :, @, and v- prefixes
        $pattern = '/([:@]?[a-zA-Z0-9\-_:.]+)=([\'"])([^\2]*?)\2/';

        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[3];
            }
        }

        return $attributes;
    }

    /**
     * Extract wire methods from Livewire component content.
     *
     * @param string $content
     * @return array
     */
    protected function extractWireMethods(string $content): array
    {
        $methods = [];
        $wirePattern = '/wire:([a-zA-Z]+)=[\'"]([^\'"]*)[\'"]/';;

        if (preg_match_all($wirePattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $methods[] = [
                    'event' => $match[1],
                    'method' => $match[2],
                ];
            }
        }

        return $methods;
    }

    /**
     * Extract wire methods from general content.
     *
     * @param string $content
     * @return array
     */
    protected function extractWireMethodsFromContent(string $content): array
    {
        $methods = [];
        $wirePattern = '/wire:([a-zA-Z]+)=[\'"]([^\'"]*)[\'"]/';;

        if (preg_match_all($wirePattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $methods[] = [
                    'event' => $match[1][0],
                    'method' => $match[2][0],
                    'offset' => $match[0][1],
                    'line_number' => substr_count(substr($content, 0, $match[0][1]), "\n") + 1,
                ];
            }
        }

        return $methods;
    }

    /**
     * Determine asset type from file path.
     *
     * @param string $path
     * @return string
     */
    protected function determineAssetType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $typeMap = [
            'css' => 'stylesheet',
            'js' => 'javascript',
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'svg' => 'image',
            'webp' => 'image',
            'mp4' => 'video',
            'webm' => 'video',
            'mp3' => 'audio',
            'wav' => 'audio',
            'pdf' => 'document',
            'doc' => 'document',
            'docx' => 'document',
            'woff' => 'font',
            'woff2' => 'font',
            'ttf' => 'font',
            'eot' => 'font',
        ];

        return $typeMap[$extension] ?? 'unknown';
    }

    /**
     * Check if URL is external.
     *
     * @param string $url
     * @return bool
     */
    protected function isExternalUrl(string $url): bool
    {
        return Str::startsWith($url, ['http://', 'https://', '//']);
    }

    /**
     * Create element map for differential scanning.
     *
     * @param array $elements
     * @return array
     */
    protected function createElementMap(array $elements): array
    {
        $map = [];

        foreach ($elements as $element) {
            $id = $element['id'] ?? $this->generateElementId($element);
            $map[$id] = $element;
        }

        return $map;
    }

    /**
     * Check if element has changed between scans.
     *
     * @param array $current
     * @param array $previous
     * @return bool
     */
    protected function hasElementChanged(array $current, array $previous): bool
    {
        // Compare key fields that indicate changes
        $compareFields = ['text_content', 'html_content', 'attributes', 'content_type'];

        foreach ($compareFields as $field) {
            if (($current[$field] ?? null) !== ($previous[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get specific changes between element versions.
     *
     * @param array $current
     * @param array $previous
     * @return array
     */
    protected function getElementChanges(array $current, array $previous): array
    {
        $changes = [];
        $compareFields = ['text_content', 'html_content', 'attributes', 'content_type'];

        foreach ($compareFields as $field) {
            $currentValue = $current[$field] ?? null;
            $previousValue = $previous[$field] ?? null;

            if ($currentValue !== $previousValue) {
                $changes[$field] = [
                    'from' => $previousValue,
                    'to' => $currentValue,
                ];
            }
        }

        return $changes;
    }
}