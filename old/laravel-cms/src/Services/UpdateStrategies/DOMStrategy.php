<?php

namespace Webook\LaravelCMS\Services\UpdateStrategies;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DOMNode;

/**
 * DOM Update Strategy
 *
 * Handles HTML/XML content updates using DOM manipulation.
 * Supports CSS selectors, XPath queries, and attribute updates.
 */
class DOMStrategy extends AbstractUpdateStrategy
{
    protected DOMDocument $dom;
    protected DOMXPath $xpath;

    /**
     * Check if this strategy can handle the content.
     *
     * @param string $content Content to check
     * @param array $context Additional context
     * @return bool True if can handle
     */
    public function canHandle(string $content, array $context = []): bool
    {
        // Check if content looks like HTML/XML
        if (preg_match('/<[^>]+>/', $content)) {
            return true;
        }

        // Check if explicitly requested
        if (($context['force_dom'] ?? false) === true) {
            return true;
        }

        return false;
    }

    /**
     * Update content using DOM manipulation.
     *
     * @param string $content Original content
     * @param string $old Old value to replace
     * @param string $new New value
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateContent(string $content, string $old, string $new, array $context = []): string
    {
        $this->logOperation('updateContent', [
            'old_length' => strlen($old),
            'new_length' => strlen($new),
            'method' => $context['method'] ?? 'text_content',
        ]);

        $this->initializeDOM($content);

        $method = $context['method'] ?? 'text_content';

        switch ($method) {
            case 'outer_html':
                return $this->updateOuterHTML($old, $new);
            case 'inner_html':
                return $this->updateInnerHTML($old, $new);
            case 'text_content':
            default:
                return $this->updateTextContent($old, $new);
        }
    }

    /**
     * Update by CSS selector or XPath.
     *
     * @param string $content Original content
     * @param string $selector CSS selector or XPath
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateBySelector(string $content, string $selector, string $new, array $context = []): string
    {
        $this->logOperation('updateBySelector', [
            'selector' => $selector,
            'new_length' => strlen($new),
            'update_mode' => $context['update_mode'] ?? 'text',
        ]);

        $this->initializeDOM($content);

        $elements = $this->findElementsBySelector($selector);

        if (empty($elements)) {
            throw new \InvalidArgumentException("No elements found for selector: {$selector}");
        }

        $updateMode = $context['update_mode'] ?? 'text';

        foreach ($elements as $element) {
            switch ($updateMode) {
                case 'html':
                    $this->setElementHTML($element, $new);
                    break;
                case 'replace':
                    $this->replaceElement($element, $new);
                    break;
                case 'text':
                default:
                    $element->textContent = $new;
                    break;
            }
        }

        return $this->getUpdatedContent();
    }

    /**
     * Update element attribute.
     *
     * @param string $content Original content
     * @param string $selector CSS selector or XPath
     * @param string $attribute Attribute name
     * @param string $value New attribute value
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateAttribute(string $content, string $selector, string $attribute, string $value, array $context = []): string
    {
        $this->logOperation('updateAttribute', [
            'selector' => $selector,
            'attribute' => $attribute,
            'value_length' => strlen($value),
        ]);

        $this->initializeDOM($content);

        $elements = $this->findElementsBySelector($selector);

        if (empty($elements)) {
            throw new \InvalidArgumentException("No elements found for selector: {$selector}");
        }

        foreach ($elements as $element) {
            if ($element instanceof DOMElement) {
                if (empty($value)) {
                    $element->removeAttribute($attribute);
                } else {
                    $element->setAttribute($attribute, $value);
                }
            }
        }

        return $this->getUpdatedContent();
    }

    /**
     * Update by line number (not optimal for DOM but supported).
     *
     * @param string $content Original content
     * @param int $line Line number
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateByLineNumber(string $content, int $line, string $new, array $context = []): string
    {
        $this->logOperation('updateByLineNumber', [
            'line' => $line,
            'new_length' => strlen($new),
        ]);

        // For DOM content, fall back to text-based line update
        return parent::updateByLineNumber($content, $line, $new, $context);
    }

    /**
     * Initialize DOM document.
     *
     * @param string $content HTML/XML content
     */
    protected function initializeDOM(string $content): void
    {
        $this->dom = new DOMDocument();
        $this->dom->preserveWhiteSpace = $this->config['preserve_whitespace'] ?? true;
        $this->dom->formatOutput = $this->config['format_output'] ?? false;

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);

        // Handle different content types
        if (preg_match('/^\s*<(!DOCTYPE|\?xml)/i', $content)) {
            // Full document
            $this->dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } else {
            // Fragment - wrap in body
            $wrapped = '<html><body>' . $content . '</body></html>';
            $this->dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        }

        $this->xpath = new DOMXPath($this->dom);

        libxml_clear_errors();
    }

    /**
     * Find elements by selector (CSS or XPath).
     *
     * @param string $selector Selector string
     * @return array Array of DOMNode elements
     */
    protected function findElementsBySelector(string $selector): array
    {
        // Detect if selector is XPath
        if (str_starts_with($selector, '/') || str_starts_with($selector, '//')) {
            return $this->findByXPath($selector);
        }

        // Convert CSS selector to XPath
        $xpath = $this->cssToXPath($selector);
        return $this->findByXPath($xpath);
    }

    /**
     * Find elements by XPath.
     *
     * @param string $xpath XPath query
     * @return array Array of DOMNode elements
     */
    protected function findByXPath(string $xpath): array
    {
        $nodeList = $this->xpath->query($xpath);

        if ($nodeList === false) {
            throw new \InvalidArgumentException("Invalid XPath query: {$xpath}");
        }

        $elements = [];
        foreach ($nodeList as $node) {
            $elements[] = $node;
        }

        return $elements;
    }

    /**
     * Convert CSS selector to XPath (basic implementation).
     *
     * @param string $cssSelector CSS selector
     * @return string XPath expression
     */
    protected function cssToXPath(string $cssSelector): string
    {
        $xpath = $cssSelector;

        // Basic CSS to XPath conversions
        $conversions = [
            // Tag selectors
            '/^([a-zA-Z][a-zA-Z0-9]*)$/' => '//$1',
            // ID selectors
            '/#([a-zA-Z][a-zA-Z0-9_-]*)/' => '[@id="$1"]',
            // Class selectors
            '/\.([a-zA-Z][a-zA-Z0-9_-]*)/' => '[contains(@class,"$1")]',
            // Attribute selectors
            '/\[([a-zA-Z][a-zA-Z0-9_-]*)\]/' => '[@$1]',
            '/\[([a-zA-Z][a-zA-Z0-9_-]*)="([^"]+)"\]/' => '[@$1="$2"]',
            // Descendant selectors
            '/\s+/' => '//',
            // Direct child selectors
            '/\s*>\s*/' => '/',
        ];

        foreach ($conversions as $pattern => $replacement) {
            $xpath = preg_replace($pattern, $replacement, $xpath);
        }

        // Ensure it starts with //
        if (!str_starts_with($xpath, '/')) {
            $xpath = '//' . $xpath;
        }

        return $xpath;
    }

    /**
     * Update text content in DOM.
     *
     * @param string $old Old text
     * @param string $new New text
     * @return string Updated content
     */
    protected function updateTextContent(string $old, string $new): string
    {
        $walker = function (DOMNode $node) use ($old, $new, &$walker) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $node->textContent = str_replace($old, $new, $node->textContent);
            }

            foreach ($node->childNodes as $child) {
                $walker($child);
            }
        };

        $walker($this->dom->documentElement ?: $this->dom);

        return $this->getUpdatedContent();
    }

    /**
     * Update outer HTML.
     *
     * @param string $old Old HTML
     * @param string $new New HTML
     * @return string Updated content
     */
    protected function updateOuterHTML(string $old, string $new): string
    {
        $html = $this->dom->saveHTML();
        return str_replace($old, $new, $html);
    }

    /**
     * Update inner HTML.
     *
     * @param string $old Old HTML
     * @param string $new New HTML
     * @return string Updated content
     */
    protected function updateInnerHTML(string $old, string $new): string
    {
        // This is a simplified implementation
        return $this->updateOuterHTML($old, $new);
    }

    /**
     * Set element's HTML content.
     *
     * @param DOMNode $element Element to update
     * @param string $html New HTML content
     */
    protected function setElementHTML(DOMNode $element, string $html): void
    {
        if (!($element instanceof DOMElement)) {
            return;
        }

        // Clear existing content
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }

        // Create a temporary document to parse the HTML
        $tempDoc = new DOMDocument();
        $wrapped = '<div>' . $html . '</div>';

        libxml_use_internal_errors(true);
        $tempDoc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $tempBody = $tempDoc->getElementsByTagName('div')->item(0);

        if ($tempBody) {
            foreach ($tempBody->childNodes as $child) {
                $importedNode = $this->dom->importNode($child, true);
                $element->appendChild($importedNode);
            }
        }
    }

    /**
     * Replace element with new content.
     *
     * @param DOMNode $element Element to replace
     * @param string $html New HTML content
     */
    protected function replaceElement(DOMNode $element, string $html): void
    {
        $parent = $element->parentNode;
        if (!$parent) {
            return;
        }

        // Create temporary document for new content
        $tempDoc = new DOMDocument();
        $wrapped = '<div>' . $html . '</div>';

        libxml_use_internal_errors(true);
        $tempDoc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $tempBody = $tempDoc->getElementsByTagName('div')->item(0);

        if ($tempBody) {
            // Insert new nodes before the element to replace
            foreach ($tempBody->childNodes as $child) {
                $importedNode = $this->dom->importNode($child, true);
                $parent->insertBefore($importedNode, $element);
            }
        }

        // Remove the original element
        $parent->removeChild($element);
    }

    /**
     * Get updated content from DOM.
     *
     * @return string Updated HTML content
     */
    protected function getUpdatedContent(): string
    {
        if ($this->config['output_fragment'] ?? false) {
            // Return only body content for fragments
            $body = $this->dom->getElementsByTagName('body')->item(0);
            if ($body) {
                $html = '';
                foreach ($body->childNodes as $child) {
                    $html .= $this->dom->saveHTML($child);
                }
                return $html;
            }
        }

        return $this->dom->saveHTML();
    }

    /**
     * Validate DOM content.
     *
     * @param string $content Content to validate
     * @param array $context Additional context
     * @return array Validation results
     */
    public function validate(string $content, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        // Try to parse as HTML
        $dom = new DOMDocument();
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $libxmlErrors = libxml_get_errors();

        foreach ($libxmlErrors as $error) {
            $message = trim($error->message);

            if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                $errors[] = "Line {$error->line}: {$message}";
            } else {
                $warnings[] = "Line {$error->line}: {$message}";
            }
        }

        libxml_clear_errors();

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get strategy priority.
     *
     * @return int Priority level
     */
    public function getPriority(): int
    {
        return 70; // High priority for HTML content
    }

    /**
     * Get strategy name.
     *
     * @return string Strategy name
     */
    public function getName(): string
    {
        return 'DOMStrategy';
    }

    /**
     * Get default configuration.
     *
     * @return array Default configuration
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'preserve_whitespace' => true,
            'format_output' => false,
            'output_fragment' => false,
            'validate_html' => true,
        ]);
    }
}