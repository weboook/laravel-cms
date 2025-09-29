<?php

namespace Webook\LaravelCMS\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Content Scanner Helper Methods
 *
 * This trait contains utility methods and helper functions used by the ContentScanner.
 */
trait ContentScannerHelpers
{
    /**
     * Create a DOMDocument from HTML string with proper error handling.
     *
     * @param string $html
     * @return DOMDocument
     * @throws \Exception
     */
    protected function createDomDocument(string $html): DOMDocument
    {
        // Suppress DOM errors and handle them manually
        libxml_use_internal_errors(true);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        // Add HTML5 doctype and wrap in proper structure if needed
        $wrappedHtml = $this->wrapHtmlIfNeeded($html);

        if (!$doc->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            $errors = libxml_get_errors();
            $errorMessages = array_map(fn($error) => trim($error->message), $errors);
            libxml_clear_errors();

            throw new \Exception('Failed to parse HTML: ' . implode(', ', $errorMessages));
        }

        libxml_clear_errors();
        return $doc;
    }

    /**
     * Wrap HTML with proper structure if needed.
     *
     * @param string $html
     * @return string
     */
    protected function wrapHtmlIfNeeded(string $html): string
    {
        // Check if HTML is a complete document
        if (Str::contains(strtolower($html), ['<!doctype', '<html', '<body'])) {
            return $html;
        }

        // Wrap fragment in minimal HTML structure
        return "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"></head><body>{$html}</body></html>";
    }

    /**
     * Get editable selectors based on filters.
     *
     * @param array $filters
     * @return array
     */
    protected function getEditableSelectors(array $filters = []): array
    {
        $selectors = [
            'text_elements' => '//p | //span | //div | //h1 | //h2 | //h3 | //h4 | //h5 | //h6',
            'image_elements' => '//img',
            'link_elements' => '//a[@href]',
            'list_elements' => '//ul | //ol | //li',
            'table_elements' => '//table | //td | //th',
            'media_elements' => '//video | //audio',
            'custom_elements' => '//*[@data-cms-editable]',
            'component_elements' => '//*[@data-livewire-component] | //*[@x-data] | //*[@v-model]',
        ];

        // Apply filters
        if (!empty($filters['include_only'])) {
            $filtered = [];
            foreach ($filters['include_only'] as $type) {
                if (isset($selectors[$type])) {
                    $filtered[$type] = $selectors[$type];
                }
            }
            $selectors = $filtered;
        }

        if (!empty($filters['exclude'])) {
            foreach ($filters['exclude'] as $type) {
                unset($selectors[$type]);
            }
        }

        return $selectors;
    }

    /**
     * Check if an element is editable based on filters and rules.
     *
     * @param DOMElement $element
     * @param array $filters
     * @return bool
     */
    protected function isElementEditable(DOMElement $element, array $filters = []): bool
    {
        $tagName = strtolower($element->tagName);

        // Check excluded elements
        $excludedElements = array_merge(
            $this->config['excluded_elements'] ?? [],
            $filters['excluded_elements'] ?? []
        );

        if (in_array($tagName, $excludedElements)) {
            return false;
        }

        // Check for explicit exclusion
        if ($element->hasAttribute('data-cms-exclude')) {
            return false;
        }

        // Check minimum content length
        $minLength = $filters['min_content_length'] ?? 3;
        if (strlen(trim($element->textContent)) < $minLength && !in_array($tagName, ['img', 'video', 'audio'])) {
            return false;
        }

        // Check if element is inside excluded parent
        if ($this->isInsideExcludedParent($element)) {
            return false;
        }

        return true;
    }

    /**
     * Check if element is inside an excluded parent.
     *
     * @param DOMElement $element
     * @return bool
     */
    protected function isInsideExcludedParent(DOMElement $element): bool
    {
        $parent = $element->parentNode;
        $excludedParents = ['script', 'style', 'noscript', 'template'];

        while ($parent && $parent instanceof DOMElement) {
            if (in_array(strtolower($parent->tagName), $excludedParents)) {
                return true;
            }

            if ($parent->hasAttribute('data-cms-exclude')) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    /**
     * Process a single element and return its data.
     *
     * @param array $elementData
     * @param array $options
     * @return array|null
     */
    protected function processElement(array $elementData, array $options = []): ?array
    {
        $element = $elementData['element'];

        if (!($element instanceof DOMElement)) {
            return null;
        }

        return $this->getElementMetadata($element, $options);
    }

    /**
     * Generate unique ID for an element.
     *
     * @param DOMElement $element
     * @return string
     */
    protected function generateElementId(DOMElement $element): string
    {
        // Try to use existing ID
        $existingId = $element->getAttribute('id');
        if ($existingId) {
            return 'cms_' . $existingId;
        }

        // Generate based on element characteristics
        $tagName = $element->tagName;
        $className = $element->getAttribute('class');
        $textContent = substr(trim($element->textContent), 0, 50);
        $xpath = $this->getElementXPath($element);

        $seed = $tagName . $className . $textContent . $xpath;
        return 'cms_' . substr(md5($seed), 0, 12);
    }

    /**
     * Get element's XPath.
     *
     * @param DOMElement $element
     * @return string
     */
    protected function getElementXPath(DOMElement $element): string
    {
        $path = '';
        $node = $element;

        while ($node && $node->nodeType === XML_ELEMENT_NODE) {
            $name = $node->nodeName;
            $parent = $node->parentNode;

            if ($parent) {
                $siblings = [];
                foreach ($parent->childNodes as $sibling) {
                    if ($sibling->nodeType === XML_ELEMENT_NODE && $sibling->nodeName === $name) {
                        $siblings[] = $sibling;
                    }
                }

                if (count($siblings) > 1) {
                    $index = array_search($node, $siblings, true) + 1;
                    $name .= "[{$index}]";
                }
            }

            $path = $name . ($path ? '/' . $path : '');
            $node = $parent;
        }

        return '/' . $path;
    }

    /**
     * Generate CSS selector for element.
     *
     * @param DOMElement $element
     * @return string
     */
    protected function generateCssSelector(DOMElement $element): string
    {
        $selector = strtolower($element->tagName);

        // Add ID if present
        $id = $element->getAttribute('id');
        if ($id) {
            $selector .= '#' . $id;
        }

        // Add classes if present
        $classes = $element->getAttribute('class');
        if ($classes) {
            $classArray = explode(' ', trim($classes));
            foreach ($classArray as $class) {
                if (!empty($class)) {
                    $selector .= '.' . $class;
                }
            }
        }

        return $selector;
    }

    /**
     * Get element attributes as array.
     *
     * @param DOMElement $element
     * @return array
     */
    protected function getElementAttributes(DOMElement $element): array
    {
        $attributes = [];

        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }

        return $attributes;
    }

    /**
     * Get element's inner HTML.
     *
     * @param DOMElement $element
     * @return string
     */
    protected function getElementInnerHtml(DOMElement $element): string
    {
        $innerHTML = '';

        foreach ($element->childNodes as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }

        return $innerHTML;
    }

    /**
     * Get clean HTML from DOM document.
     *
     * @param DOMDocument $doc
     * @return string
     */
    protected function getCleanHtml(DOMDocument $doc): string
    {
        $html = $doc->saveHTML();

        // Remove the wrapper if we added it
        if (Str::contains($html, '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>')) {
            $html = preg_replace('/^.*<body[^>]*>/', '', $html);
            $html = preg_replace('/<\/body>.*$/', '', $html);
        }

        return $html;
    }

    /**
     * Normalize URL for consistent processing.
     *
     * @param string $url
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function normalizeUrl(string $url): string
    {
        // Handle relative URLs
        if (Str::startsWith($url, '/')) {
            $url = request()->getSchemeAndHttpHost() . $url;
        }

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }

        return $url;
    }

    /**
     * Fetch page content from URL.
     *
     * @param string $url
     * @param array $options
     * @return string
     * @throws \Exception
     */
    protected function fetchPageContent(string $url, array $options = []): string
    {
        try {
            $response = Http::timeout($this->config['timeout'] ?? 30)
                ->withUserAgent($this->config['user_agent'] ?? 'Laravel CMS Scanner')
                ->get($url);

            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()} when fetching {$url}");
            }

            $content = $response->body();

            // Check content length
            $maxLength = $this->config['max_content_length'] ?? (10 * 1024 * 1024);
            if (strlen($content) > $maxLength) {
                throw new \Exception("Content too large: " . strlen($content) . " bytes");
            }

            return $content;

        } catch (Throwable $e) {
            throw new \Exception("Failed to fetch content from {$url}: {$e->getMessage()}");
        }
    }

    /**
     * Extract page title from HTML.
     *
     * @param string $html
     * @return string|null
     */
    protected function extractPageTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extract meta description from HTML.
     *
     * @param string $html
     * @return string|null
     */
    protected function extractMetaDescription(string $html): ?string
    {
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]));
        }

        return null;
    }

    /**
     * Get cached results for a cache key.
     *
     * @param string $cacheKey
     * @return array|null
     */
    protected function getCachedResults(string $cacheKey): ?array
    {
        try {
            return $this->cache->get($cacheKey);
        } catch (Throwable $e) {
            Log::warning('ContentScanner: Cache retrieval failed', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get current statistics snapshot.
     *
     * @return array
     */
    protected function getStatisticsSnapshot(): array
    {
        return $this->statistics;
    }

    /**
     * Extract translation context around a position.
     *
     * @param string $content
     * @param int $offset
     * @param int $contextLength
     * @return string
     */
    protected function extractTranslationContext(string $content, int $offset, int $contextLength = 50): string
    {
        $start = max(0, $offset - $contextLength);
        $length = min(strlen($content) - $start, $contextLength * 2);

        return substr($content, $start, $length);
    }

    /**
     * Extract translation namespace from key.
     *
     * @param string $key
     * @return string|null
     */
    protected function extractTranslationNamespace(string $key): ?string
    {
        if (Str::contains($key, '.')) {
            return explode('.', $key)[0];
        }

        return null;
    }

    /**
     * Extract translation parameters from function call.
     *
     * @param string $functionCall
     * @return array
     */
    protected function extractTranslationParameters(string $functionCall): array
    {
        // This is a simplified parser - in production you might want a more robust solution
        if (preg_match('/,\s*(\[.*?\])\s*\)$/', $functionCall, $matches)) {
            try {
                // Convert PHP array syntax to JSON for parsing
                $arrayString = $matches[1];
                $arrayString = preg_replace('/([a-zA-Z_][a-zA-Z0-9_]*)\s*=>/', '"$1":', $arrayString);
                $arrayString = preg_replace('/\'\s*([^\']*)\s*\'/', '"$1"', $arrayString);

                return json_decode($arrayString, true) ?? [];
            } catch (Throwable $e) {
                return [];
            }
        }

        return [];
    }

    /**
     * Check translation locales availability.
     *
     * @param string $key
     * @param array $locales
     * @return array
     */
    protected function checkTranslationLocales(string $key, array $locales = []): array
    {
        $available = [];

        if (empty($locales)) {
            $locales = config('cms.locale.available', ['en']);
        }

        foreach ($locales as $locale) {
            // This would need integration with Laravel's translation system
            // For now, we'll just return the locale list
            $available[$locale] = true; // Placeholder
        }

        return $available;
    }

    /**
     * Additional helper methods for element metadata...
     */

    protected function getElementPosition(DOMElement $element): array
    {
        // This would require JavaScript execution or CSS parsing
        // For now, return placeholder data
        return [
            'top' => null,
            'left' => null,
            'width' => null,
            'height' => null,
        ];
    }

    protected function estimateElementDimensions(DOMElement $element): array
    {
        // Basic estimation based on content
        $textLength = strlen(trim($element->textContent));

        return [
            'estimated_width' => min(800, max(100, $textLength * 8)),
            'estimated_height' => max(20, ($textLength / 80) * 20),
            'content_length' => $textLength,
        ];
    }

    protected function determineEditPermissions(DOMElement $element, array $context = []): array
    {
        $contentType = $this->detectContentType($element);

        $permissions = [
            'can_edit' => true,
            'required_permissions' => ['cms.edit'],
            'restrictions' => [],
        ];

        // Add specific permissions based on content type
        switch ($contentType) {
            case 'image':
                $permissions['required_permissions'][] = 'cms.edit.images';
                break;
            case 'link':
                $permissions['required_permissions'][] = 'cms.edit.links';
                break;
            case 'livewire_component':
                $permissions['required_permissions'][] = 'cms.edit.components';
                break;
        }

        return $permissions;
    }

    protected function generateElementCacheKey(DOMElement $element): string
    {
        $data = [
            'tag' => $element->tagName,
            'content' => md5($element->textContent),
            'attributes' => md5(serialize($this->getElementAttributes($element))),
        ];

        return 'cms_element_' . md5(serialize($data));
    }

    protected function getParentElementInfo(DOMElement $element): array
    {
        $parent = $element->parentNode;

        if ($parent && $parent instanceof DOMElement) {
            return [
                'tag_name' => $parent->tagName,
                'id' => $parent->getAttribute('id') ?: null,
                'class' => $parent->getAttribute('class') ?: null,
            ];
        }

        return [];
    }

    protected function getValidationRules(DOMElement $element): array
    {
        $contentType = $this->detectContentType($element);

        $rules = [
            'required' => false,
            'max_length' => null,
            'allowed_html' => false,
        ];

        switch ($contentType) {
            case 'plain_text':
                $rules['max_length'] = 1000;
                break;
            case 'rich_text':
                $rules['allowed_html'] = true;
                $rules['max_length'] = 5000;
                break;
            case 'image':
                $rules['file_types'] = ['jpg', 'png', 'gif', 'svg'];
                $rules['max_size'] = '2MB';
                break;
        }

        return $rules;
    }

    // Additional component mapping methods
    protected function mapElementToComponent(DOMElement $element): ?array
    {
        // This would implement sophisticated component detection
        // based on class names, data attributes, and DOM structure
        return null; // Placeholder
    }

    protected function performHeuristicMapping(DOMElement $element): ?array
    {
        // This would implement heuristic source file mapping
        // based on element characteristics and project structure
        return null; // Placeholder
    }

    protected function injectMarkersForElement(DOMDocument $doc, DOMXPath $xpath, array $elementData, array $options): void
    {
        // This would implement marker injection logic
        // Adding data attributes and wrapper elements for editing
    }

    protected function addCmsInitializationScript(DOMDocument $doc, array $options): void
    {
        // This would add the CMS JavaScript initialization
        // to enable inline editing functionality
    }
}