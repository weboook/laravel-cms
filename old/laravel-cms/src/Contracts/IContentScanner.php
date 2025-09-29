<?php

namespace Webook\LaravelCMS\Contracts;

use DOMDocument;
use DOMElement;

/**
 * Content Scanner Interface
 *
 * Defines the contract for scanning and analyzing HTML content to identify
 * editable elements, translation keys, and dynamic content within Laravel applications.
 *
 * This interface supports advanced content detection including:
 * - Static text and dynamic variables
 * - Translation keys and localization
 * - Blade components and Livewire components
 * - Vue.js and Alpine.js components
 * - Asset references and links
 */
interface IContentScanner
{
    /**
     * Scan a complete page by URL to identify all editable content.
     *
     * This method fetches the page content, parses it, and identifies all
     * potentially editable elements including text, images, and links.
     *
     * @param string $url The URL to scan (can be relative or absolute)
     * @param array $options Scanning options (depth, filters, etc.)
     * @return array Comprehensive scan results with metadata
     * @throws \InvalidArgumentException If URL is invalid
     * @throws \Exception If page cannot be accessed or parsed
     */
    public function scanPage(string $url, array $options = []): array;

    /**
     * Scan HTML content to identify editable elements.
     *
     * Parses raw HTML and extracts all elements that could be made editable
     * through the CMS, including their metadata and context information.
     *
     * @param string $html The HTML content to scan
     * @param array $options Scanning options and filters
     * @return array Array of editable elements with their metadata
     * @throws \InvalidArgumentException If HTML is invalid
     */
    public function scanHtml(string $html, array $options = []): array;

    /**
     * Inject editable markers into HTML content.
     *
     * Modifies HTML to add CMS editing capabilities by injecting markers
     * and data attributes that enable inline editing functionality.
     *
     * @param string $html The original HTML content
     * @param array $options Injection options (permissions, element types, etc.)
     * @return string Modified HTML with editable markers
     */
    public function injectEditableMarkers(string $html, array $options = []): string;

    /**
     * Extract all editable elements from a DOM document.
     *
     * Analyzes a DOM document to identify elements that can be made editable,
     * including text nodes, images, links, and dynamic content areas.
     *
     * @param DOMDocument $doc The DOM document to analyze
     * @param array $filters Element type filters and exclusions
     * @return array Array of DOMElement objects with editable potential
     */
    public function extractEditableElements(DOMDocument $doc, array $filters = []): array;

    /**
     * Map a DOM element back to its source file and location.
     *
     * Attempts to trace a DOM element back to its original source file,
     * line number, and context within the Laravel application structure.
     *
     * @param DOMElement $element The element to trace
     * @return array Source mapping information (file, line, context)
     */
    public function mapToSource(DOMElement $element): array;

    /**
     * Find all translation keys within content.
     *
     * Scans content for Laravel translation functions and keys including
     * __(), trans(), @lang directives, and JSON translation references.
     *
     * @param string $content The content to scan for translation keys
     * @param array $locales Specific locales to check (optional)
     * @return array Translation keys with their context and usage
     */
    public function findTranslationKeys(string $content, array $locales = []): array;

    /**
     * Detect the content type of a DOM element.
     *
     * Analyzes element structure, attributes, and content to determine
     * the most appropriate content type for CMS editing purposes.
     *
     * @param DOMElement $element The element to analyze
     * @return string Content type (text, image, link, component, etc.)
     */
    public function detectContentType(DOMElement $element): string;

    /**
     * Get comprehensive metadata for a DOM element.
     *
     * Extracts all relevant metadata including edit permissions, content type,
     * source information, and contextual data needed for CMS operations.
     *
     * @param DOMElement $element The element to analyze
     * @param array $context Additional context information
     * @return array Comprehensive element metadata
     */
    public function getElementMetadata(DOMElement $element, array $context = []): array;

    /**
     * Detect Blade components within content.
     *
     * Identifies both class-based and anonymous Blade components,
     * extracts their properties and slot content for editing.
     *
     * @param string $content The content to scan
     * @return array Blade component information
     */
    public function detectBladeComponents(string $content): array;

    /**
     * Detect Livewire components and their editable properties.
     *
     * Identifies Livewire components and analyzes their public properties
     * and methods that could be exposed for inline editing.
     *
     * @param string $content The content to scan
     * @return array Livewire component information
     */
    public function detectLivewireComponents(string $content): array;

    /**
     * Detect Vue.js and Alpine.js components.
     *
     * Identifies JavaScript framework components and their reactive
     * data properties that could be made editable through the CMS.
     *
     * @param string $content The content to scan
     * @return array JavaScript component information
     */
    public function detectJavaScriptComponents(string $content): array;

    /**
     * Analyze asset references (images, CSS, JS).
     *
     * Identifies and categorizes asset references, determining if they
     * use Laravel asset helpers, Storage facade, or external URLs.
     *
     * @param string $content The content to scan
     * @return array Asset reference information
     */
    public function analyzeAssetReferences(string $content): array;

    /**
     * Classify and analyze link elements.
     *
     * Categorizes links by type (route, URL, mailto, tel) and extracts
     * metadata for potential CMS editing and management.
     *
     * @param DOMElement $linkElement The link element to analyze
     * @return array Link classification and metadata
     */
    public function classifyLink(DOMElement $linkElement): array;

    /**
     * Perform differential scanning against previous results.
     *
     * Compares current scan results with cached previous results to
     * identify only changed content, improving performance for large pages.
     *
     * @param array $currentResults Current scan results
     * @param string $cacheKey Cache key for previous results
     * @return array Differential results showing only changes
     */
    public function performDifferentialScan(array $currentResults, string $cacheKey): array;

    /**
     * Get scan statistics and performance metrics.
     *
     * Returns detailed statistics about the scanning process including
     * element counts, processing time, and cache hit ratios.
     *
     * @return array Scan statistics and metrics
     */
    public function getScanStatistics(): array;

    /**
     * Cache scan results with intelligent key generation.
     *
     * Stores scan results with optimized cache keys based on content hash,
     * permissions, and configuration for maximum cache efficiency.
     *
     * @param array $results Scan results to cache
     * @param string $contentHash Hash of scanned content
     * @param array $metadata Additional cache metadata
     * @return string Generated cache key
     */
    public function cacheResults(array $results, string $contentHash, array $metadata = []): string;

    /**
     * Validate that content is safe for CMS editing.
     *
     * Checks content against security policies, allowed tags, and
     * edit permissions to ensure safe inline editing capabilities.
     *
     * @param string $content The content to validate
     * @param array $permissions User permissions context
     * @return array Validation results with safety assessment
     */
    public function validateContentSafety(string $content, array $permissions = []): array;
}