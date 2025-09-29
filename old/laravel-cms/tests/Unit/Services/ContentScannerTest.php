<?php

namespace Webook\LaravelCMS\Tests\Unit\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Mockery;
use PHPUnit\Framework\TestCase;
use Webook\LaravelCMS\Services\ContentScanner;

/**
 * Content Scanner Unit Tests
 *
 * Comprehensive test suite for the ContentScanner service covering:
 * - HTML parsing and DOM manipulation
 * - Content detection strategies
 * - Translation key extraction
 * - Component detection (Blade, Livewire, Vue, Alpine)
 * - Caching and performance optimization
 * - Error handling and edge cases
 */
class ContentScannerTest extends TestCase
{
    protected ContentScanner $scanner;
    protected Filesystem $files;
    protected CacheRepository $cache;
    protected HttpFactory $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = Mockery::mock(Filesystem::class);
        $this->cache = Mockery::mock(CacheRepository::class);
        $this->http = Mockery::mock(HttpFactory::class);

        $this->scanner = new ContentScanner(
            $this->files,
            $this->cache,
            $this->http,
            [
                'cache_enabled' => true,
                'cache_ttl' => 3600,
                'excluded_elements' => ['script', 'style'],
            ]
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test basic HTML scanning functionality.
     */
    public function testScanHtmlBasicFunctionality(): void
    {
        $html = '<div class="content"><h1>Hello World</h1><p>This is a test paragraph.</p></div>';

        $result = $this->scanner->scanHtml($html);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('elements', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertGreaterThan(0, count($result['elements']));
    }

    /**
     * Test translation key detection.
     */
    public function testFindTranslationKeys(): void
    {
        $content = '
            <p>{{ __("welcome.message") }}</p>
            <span>@lang("navigation.home")</span>
            <div>{{ trans("auth.failed") }}</div>
            <script>window._translations["js.confirm"] = "Are you sure?";</script>
        ';

        $translationKeys = $this->scanner->findTranslationKeys($content);

        $this->assertCount(4, $translationKeys);

        $keys = array_column($translationKeys, 'key');
        $this->assertContains('welcome.message', $keys);
        $this->assertContains('navigation.home', $keys);
        $this->assertContains('auth.failed', $keys);
        $this->assertContains('js.confirm', $keys);

        // Check pattern types
        $patternTypes = array_column($translationKeys, 'pattern_type');
        $this->assertContains('blade_echo', $patternTypes);
        $this->assertContains('blade_lang', $patternTypes);
        $this->assertContains('json_translation', $patternTypes);
    }

    /**
     * Test Blade component detection.
     */
    public function testDetectBladeComponents(): void
    {
        $content = '
            <x-alert type="success" :message="$successMessage" />
            <x-button class="btn-primary">Click me</x-button>
            @component("layouts.card")
                <p>Card content</p>
            @endcomponent
            @include("partials.header", ["title" => "Page Title"])
        ';

        $components = $this->scanner->detectBladeComponents($content);

        $this->assertCount(4, $components);

        $types = array_column($components, 'type');
        $this->assertContains('class_based', $types);
        $this->assertContains('anonymous', $types);
        $this->assertContains('include', $types);

        $names = array_column($components, 'name');
        $this->assertContains('alert', $names);
        $this->assertContains('button', $names);
        $this->assertContains('layouts.card', $names);
        $this->assertContains('partials.header', $names);
    }

    /**
     * Test Livewire component detection.
     */
    public function testDetectLivewireComponents(): void
    {
        $content = '
            <livewire:user-profile :user="$user" />
            <div wire:click="save" wire:loading.attr="disabled">Save</div>
            @livewire("chat.room", ["roomId" => $roomId])
        ';

        $components = $this->scanner->detectLivewireComponents($content);

        $this->assertCount(3, $components);

        $types = array_column($components, 'type');
        $this->assertContains('tag', $types);
        $this->assertContains('wire_method', $types);
        $this->assertContains('directive', $types);
    }

    /**
     * Test Vue.js and Alpine.js component detection.
     */
    public function testDetectJavaScriptComponents(): void
    {
        $content = '
            <div x-data="{ open: false }" x-show="open">Alpine component</div>
            <div v-model="username" v-if="showForm">Vue component</div>
            <my-vue-component :prop="value">Vue component</my-vue-component>
        ';

        $components = $this->scanner->detectJavaScriptComponents($content);

        $this->assertGreaterThan(0, count($components));

        $frameworks = array_column($components, 'framework');
        $this->assertContains('Alpine.js', $frameworks);
        $this->assertContains('Vue.js', $frameworks);
    }

    /**
     * Test asset reference analysis.
     */
    public function testAnalyzeAssetReferences(): void
    {
        $content = '
            <img src="{{ asset("images/logo.png") }}" alt="Logo">
            <link href="{{ asset("css/app.css") }}" rel="stylesheet">
            <script src="{{ asset("js/app.js") }}"></script>
            <img src="{{ Storage::url("uploads/photo.jpg") }}" alt="Photo">
            <img src="https://external.com/image.png" alt="External">
        ';

        $assets = $this->scanner->analyzeAssetReferences($content);

        $this->assertGreaterThan(0, count($assets));

        $types = array_column($assets, 'type');
        $this->assertContains('asset_helper', $types);
        $this->assertContains('storage_url', $types);
        $this->assertContains('image_src', $types);
        $this->assertContains('css_link', $types);
        $this->assertContains('js_script', $types);
    }

    /**
     * Test content type detection.
     */
    public function testDetectContentType(): void
    {
        $testCases = [
            '<img src="test.jpg" alt="Test">' => 'image',
            '<a href="https://example.com">Link</a>' => 'link',
            '<div x-data="{}">Alpine</div>' => 'alpine_component',
            '<div data-livewire-component="test">Livewire</div>' => 'livewire_component',
            '<p>Plain text content</p>' => 'plain_text',
            '<div><strong>Rich</strong> content</div>' => 'rich_text',
            '<video src="test.mp4"></video>' => 'video',
        ];

        foreach ($testCases as $html => $expectedType) {
            $doc = new DOMDocument();
            $doc->loadHTML($html);
            $element = $doc->getElementsByTagName('*')->item(0);

            $contentType = $this->scanner->detectContentType($element);
            $this->assertEquals($expectedType, $contentType, "Failed for HTML: {$html}");
        }
    }

    /**
     * Test element metadata generation.
     */
    public function testGetElementMetadata(): void
    {
        $html = '<div id="test" class="content editable" data-cms-editable="true">Test content</div>';
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $element = $doc->getElementById('test');

        $metadata = $this->scanner->getElementMetadata($element);

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('id', $metadata);
        $this->assertArrayHasKey('tag_name', $metadata);
        $this->assertArrayHasKey('content_type', $metadata);
        $this->assertArrayHasKey('text_content', $metadata);
        $this->assertArrayHasKey('attributes', $metadata);
        $this->assertArrayHasKey('xpath', $metadata);
        $this->assertArrayHasKey('css_selector', $metadata);

        $this->assertEquals('div', $metadata['tag_name']);
        $this->assertEquals('Test content', $metadata['text_content']);
        $this->assertContains('content', $metadata['attributes']['class']);
    }

    /**
     * Test caching functionality.
     */
    public function testCachingFunctionality(): void
    {
        $results = ['test' => 'data'];
        $contentHash = 'test_hash';

        $this->cache->shouldReceive('put')
            ->once()
            ->with(Mockery::type('string'), $results, 3600)
            ->andReturn(true);

        $cacheKey = $this->scanner->cacheResults($results, $contentHash);

        $this->assertIsString($cacheKey);
        $this->assertNotEmpty($cacheKey);
    }

    /**
     * Test differential scanning.
     */
    public function testPerformDifferentialScan(): void
    {
        $currentResults = [
            'elements' => [
                ['id' => 'elem1', 'text_content' => 'Updated content'],
                ['id' => 'elem2', 'text_content' => 'New content'],
            ]
        ];

        $previousResults = [
            'elements' => [
                ['id' => 'elem1', 'text_content' => 'Original content'],
                ['id' => 'elem3', 'text_content' => 'Removed content'],
            ]
        ];

        $cacheKey = 'test_cache_key';

        $this->cache->shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn($previousResults);

        $diff = $this->scanner->performDifferentialScan($currentResults, $cacheKey);

        $this->assertIsArray($diff);
        $this->assertArrayHasKey('type', $diff);
        $this->assertArrayHasKey('added', $diff);
        $this->assertArrayHasKey('modified', $diff);
        $this->assertArrayHasKey('removed', $diff);

        $this->assertEquals('differential_scan', $diff['type']);
        $this->assertCount(1, $diff['added']); // elem2
        $this->assertCount(1, $diff['modified']); // elem1
        $this->assertCount(1, $diff['removed']); // elem3
    }

    /**
     * Test content safety validation.
     */
    public function testValidateContentSafety(): void
    {
        $safeContent = '<p>This is safe content</p>';
        $unsafeContent = '<script>alert("xss")</script><p onclick="malicious()">Unsafe</p>';

        $safeValidation = $this->scanner->validateContentSafety($safeContent);
        $this->assertTrue($safeValidation['is_safe']);
        $this->assertEmpty($safeValidation['issues']);

        $unsafeValidation = $this->scanner->validateContentSafety($unsafeContent);
        $this->assertFalse($unsafeValidation['is_safe']);
        $this->assertNotEmpty($unsafeValidation['issues']);
        $this->assertContains('cms.edit.unsafe_content', $unsafeValidation['permissions_required']);
    }

    /**
     * Test error handling for invalid HTML.
     */
    public function testErrorHandlingInvalidHtml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HTML content cannot be empty');

        $this->scanner->scanHtml('');
    }

    /**
     * Test performance statistics collection.
     */
    public function testScanStatistics(): void
    {
        $html = '<div><p>Test content</p></div>';

        // Perform a scan to generate statistics
        $this->scanner->scanHtml($html);

        $statistics = $this->scanner->getScanStatistics();

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('elements_processed', $statistics);
        $this->assertArrayHasKey('current_memory_usage', $statistics);
        $this->assertGreaterThanOrEqual(0, $statistics['elements_processed']);
    }

    /**
     * Test XPath generation for elements.
     */
    public function testXPathGeneration(): void
    {
        $html = '<div><section><p id="target">Test</p></section></div>';
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $element = $doc->getElementById('target');

        $metadata = $this->scanner->getElementMetadata($element);
        $xpath = $metadata['xpath'];

        $this->assertIsString($xpath);
        $this->assertStringStartsWith('/', $xpath);
        $this->assertStringContains('p', $xpath);
    }

    /**
     * Test CSS selector generation.
     */
    public function testCssSelectorGeneration(): void
    {
        $html = '<div id="container" class="main content">Test</div>';
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $element = $doc->getElementById('container');

        $metadata = $this->scanner->getElementMetadata($element);
        $selector = $metadata['css_selector'];

        $this->assertStringContains('div', $selector);
        $this->assertStringContains('#container', $selector);
        $this->assertStringContains('.main', $selector);
        $this->assertStringContains('.content', $selector);
    }
}