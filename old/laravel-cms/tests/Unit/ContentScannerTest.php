<?php

namespace Webook\LaravelCMS\Tests\Unit;

use Webook\LaravelCMS\Tests\TestCase;
use Webook\LaravelCMS\Services\ContentScanner;
use Webook\LaravelCMS\Exceptions\CMSException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class ContentScannerTest extends TestCase
{
    protected ContentScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new ContentScanner();
    }

    public function test_scans_blade_files_correctly()
    {
        $bladeFile = $this->createTestBladeFile('test.blade.php');

        $results = $this->scanner->scanPath(dirname($bladeFile));

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('blade', $results);
        $this->assertCount(1, $results['blade']);

        $scannedFile = $results['blade'][0];
        $this->assertEquals(basename($bladeFile), $scannedFile['filename']);
        $this->assertArrayHasKey('variables', $scannedFile);
        $this->assertArrayHasKey('includes', $scannedFile);
        $this->assertArrayHasKey('extends', $scannedFile);
    }

    public function test_scans_html_files_correctly()
    {
        $htmlFile = $this->createTestHtmlFile('test.html');

        $results = $this->scanner->scanPath(dirname($htmlFile));

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('html', $results);
        $this->assertCount(1, $results['html']);

        $scannedFile = $results['html'][0];
        $this->assertEquals(basename($htmlFile), $scannedFile['filename']);
        $this->assertArrayHasKey('title', $scannedFile);
        $this->assertArrayHasKey('headings', $scannedFile);
        $this->assertArrayHasKey('images', $scannedFile);
        $this->assertArrayHasKey('links', $scannedFile);
    }

    public function test_extracts_blade_variables()
    {
        $content = <<<'BLADE'
@extends('layout')
@section('title', $pageTitle)
@section('content')
    <h1>{{ $heading }}</h1>
    <p>{{ $description }}</p>
    @if($showAlert)
        <div class="alert">{{ $alertMessage }}</div>
    @endif
    @foreach($items as $item)
        <li>{{ $item['name'] }}</li>
    @endforeach
@endsection
BLADE;

        $file = $this->createTestFile('tests/fixtures/extract-test.blade.php', $content);
        $results = $this->scanner->scanFile($file);

        $this->assertArrayHasKey('variables', $results);
        $variables = $results['variables'];

        $this->assertContains('$pageTitle', $variables);
        $this->assertContains('$heading', $variables);
        $this->assertContains('$description', $variables);
        $this->assertContains('$showAlert', $variables);
        $this->assertContains('$alertMessage', $variables);
        $this->assertContains('$items', $variables);
    }

    public function test_extracts_blade_directives()
    {
        $content = <<<'BLADE'
@extends('layouts.app')
@include('partials.header')
@includeIf('partials.sidebar')
@component('components.card')
@slot('title', 'Card Title')
@endcomponent
BLADE;

        $file = $this->createTestFile('tests/fixtures/directives-test.blade.php', $content);
        $results = $this->scanner->scanFile($file);

        $this->assertArrayHasKey('extends', $results);
        $this->assertEquals('layouts.app', $results['extends']);

        $this->assertArrayHasKey('includes', $results);
        $includes = $results['includes'];
        $this->assertContains('partials.header', $includes);
        $this->assertContains('partials.sidebar', $includes);

        $this->assertArrayHasKey('components', $results);
        $this->assertContains('components.card', $results['components']);
    }

    public function test_extracts_html_metadata()
    {
        $content = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test Page Title</title>
    <meta name="description" content="Test description">
    <meta name="keywords" content="test, html, scanner">
</head>
<body>
    <h1 id="main-title">Main Heading</h1>
    <h2 class="subtitle">Subtitle</h2>
    <h3>Sub-subtitle</h3>
    <img src="/test.jpg" alt="Test Image" title="Image Title">
    <a href="/link1" class="nav-link">Link 1</a>
    <a href="https://external.com">External Link</a>
</body>
</html>
HTML;

        $file = $this->createTestFile('tests/fixtures/metadata-test.html', $content);
        $results = $this->scanner->scanFile($file);

        $this->assertEquals('Test Page Title', $results['title']);
        $this->assertArrayHasKey('meta', $results);
        $this->assertEquals('Test description', $results['meta']['description']);
        $this->assertEquals('test, html, scanner', $results['meta']['keywords']);

        $this->assertArrayHasKey('headings', $results);
        $headings = $results['headings'];
        $this->assertCount(3, $headings);
        $this->assertEquals('Main Heading', $headings[0]['text']);
        $this->assertEquals('h1', $headings[0]['tag']);

        $this->assertArrayHasKey('images', $results);
        $images = $results['images'];
        $this->assertCount(1, $images);
        $this->assertEquals('/test.jpg', $images[0]['src']);
        $this->assertEquals('Test Image', $images[0]['alt']);

        $this->assertArrayHasKey('links', $results);
        $links = $results['links'];
        $this->assertCount(2, $links);
        $this->assertEquals('/link1', $links[0]['href']);
        $this->assertEquals('https://external.com', $links[1]['href']);
    }

    public function test_respects_allowed_paths_configuration()
    {
        Config::set('cms.content.allowed_paths', ['tests/fixtures']);

        $allowedFile = $this->createTestFile('tests/fixtures/allowed.blade.php', '@extends("layout")');
        $disallowedFile = $this->createTestFile('tests/forbidden/disallowed.blade.php', '@extends("layout")');

        $this->assertTrue($this->scanner->isPathAllowed('tests/fixtures/allowed.blade.php'));
        $this->assertFalse($this->scanner->isPathAllowed('tests/forbidden/disallowed.blade.php'));
    }

    public function test_respects_allowed_extensions_configuration()
    {
        Config::set('cms.content.allowed_extensions', ['blade.php', 'html']);

        $this->assertTrue($this->scanner->isExtensionAllowed('test.blade.php'));
        $this->assertTrue($this->scanner->isExtensionAllowed('test.html'));
        $this->assertFalse($this->scanner->isExtensionAllowed('test.php'));
        $this->assertFalse($this->scanner->isExtensionAllowed('test.txt'));
    }

    public function test_handles_nested_directories()
    {
        $this->createTestFile('tests/fixtures/level1/level2/nested.blade.php', '@extends("layout")');
        $this->createTestFile('tests/fixtures/level1/another.html', '<html><title>Test</title></html>');

        $results = $this->scanner->scanPath('tests/fixtures', true); // recursive

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('blade', $results);
        $this->assertArrayHasKey('html', $results);

        $foundNested = false;
        $foundAnother = false;

        foreach ($results['blade'] as $file) {
            if ($file['filename'] === 'nested.blade.php') {
                $foundNested = true;
            }
        }

        foreach ($results['html'] as $file) {
            if ($file['filename'] === 'another.html') {
                $foundAnother = true;
            }
        }

        $this->assertTrue($foundNested);
        $this->assertTrue($foundAnother);
    }

    public function test_throws_exception_for_invalid_path()
    {
        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Path does not exist');

        $this->scanner->scanPath('/non/existent/path');
    }

    public function test_throws_exception_for_restricted_path()
    {
        Config::set('cms.security.restricted_paths', ['config/', '.env']);

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Access to this path is restricted');

        $this->scanner->scanPath('config/app.php');
    }

    public function test_detects_translation_keys()
    {
        $content = <<<'BLADE'
<h1>{{ __('messages.welcome') }}</h1>
<p>{{ trans('auth.failed') }}</p>
<span>@lang('validation.required')</span>
<div>{{ __('nested.deep.value') }}</div>
BLADE;

        $file = $this->createTestFile('tests/fixtures/translations-test.blade.php', $content);
        $results = $this->scanner->scanFile($file);

        $this->assertArrayHasKey('translations', $results);
        $translations = $results['translations'];

        $this->assertContains('messages.welcome', $translations);
        $this->assertContains('auth.failed', $translations);
        $this->assertContains('validation.required', $translations);
        $this->assertContains('nested.deep.value', $translations);
    }

    public function test_extracts_css_classes_from_html()
    {
        $content = <<<'HTML'
<div class="container mx-auto">
    <h1 class="text-2xl font-bold text-blue-600">Title</h1>
    <p class="text-gray-700 leading-relaxed">Content</p>
    <button class="btn btn-primary hover:bg-blue-700">Click me</button>
</div>
HTML;

        $file = $this->createTestFile('tests/fixtures/classes-test.html', $content);
        $results = $this->scanner->scanFile($file);

        $this->assertArrayHasKey('css_classes', $results);
        $classes = $results['css_classes'];

        $this->assertContains('container', $classes);
        $this->assertContains('mx-auto', $classes);
        $this->assertContains('text-2xl', $classes);
        $this->assertContains('font-bold', $classes);
        $this->assertContains('btn', $classes);
        $this->assertContains('btn-primary', $classes);
    }

    public function test_performance_with_large_files()
    {
        // Create a large file
        $largeContent = str_repeat('<div class="item">{{ $item->name }}</div>' . PHP_EOL, 1000);
        $file = $this->createTestFile('tests/fixtures/large-test.blade.php', $largeContent);

        $this->assertPerformance(function () use ($file) {
            $this->scanner->scanFile($file);
        }, 2.0, 50 * 1024 * 1024); // 2 seconds, 50MB
    }

    public function test_handles_malformed_html_gracefully()
    {
        $malformedContent = <<<'HTML'
<html>
<head>
    <title>Malformed Test
<body>
    <h1>Unclosed heading
    <div class="container">
        <p>Paragraph without closing tag
        <img src="image.jpg" alt="Missing quote>
    </div>
</html>
HTML;

        $file = $this->createTestFile('tests/fixtures/malformed-test.html', $malformedContent);

        // Should not throw exception, but handle gracefully
        $results = $this->scanner->scanFile($file);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('title', $results);
        $this->assertEquals('Malformed Test', $results['title']);
    }

    public function test_scan_with_filters()
    {
        $this->createTestFile('tests/fixtures/filter1.blade.php', '@extends("layout")');
        $this->createTestFile('tests/fixtures/filter2.html', '<html><title>Test</title></html>');
        $this->createTestFile('tests/fixtures/filter3.txt', 'Plain text');

        $results = $this->scanner->scanPath('tests/fixtures', false, ['blade.php']);

        $this->assertArrayHasKey('blade', $results);
        $this->assertArrayNotHasKey('html', $results);
        $this->assertCount(1, $results['blade']);
    }

    public function test_extracts_form_elements()
    {
        $content = <<<'HTML'
<form action="/submit" method="POST">
    <input type="text" name="username" placeholder="Username" required>
    <input type="email" name="email" id="email-field">
    <textarea name="message" rows="5"></textarea>
    <select name="country">
        <option value="us">United States</option>
        <option value="ca">Canada</option>
    </select>
    <button type="submit">Submit</button>
</form>
HTML;

        $file = $this->createTestFile('tests/fixtures/form-test.html', $content);
        $results = $this->scanner->scanFile($file);

        $this->assertArrayHasKey('forms', $results);
        $forms = $results['forms'];
        $this->assertCount(1, $forms);

        $form = $forms[0];
        $this->assertEquals('/submit', $form['action']);
        $this->assertEquals('POST', $form['method']);

        $this->assertArrayHasKey('fields', $form);
        $fields = $form['fields'];
        $this->assertCount(5, $fields);

        // Check specific fields
        $usernameField = collect($fields)->firstWhere('name', 'username');
        $this->assertEquals('text', $usernameField['type']);
        $this->assertTrue($usernameField['required']);
    }

    public function test_caches_scan_results()
    {
        $file = $this->createTestBladeFile('cache-test.blade.php');

        // First scan
        $start = microtime(true);
        $results1 = $this->scanner->scanFile($file);
        $firstScanTime = microtime(true) - $start;

        // Second scan (should be cached)
        $start = microtime(true);
        $results2 = $this->scanner->scanFile($file);
        $secondScanTime = microtime(true) - $start;

        $this->assertEquals($results1, $results2);
        $this->assertLessThan($firstScanTime, $secondScanTime);
    }

    public function test_validates_file_size_limits()
    {
        Config::set('cms.content.max_file_size', 1024); // 1KB limit

        $largeContent = str_repeat('x', 2048); // 2KB content
        $file = $this->createTestFile('tests/fixtures/large-file.html', $largeContent);

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('File size exceeds limit');

        $this->scanner->scanFile($file);
    }

    public function test_detects_security_issues()
    {
        $content = <<<'HTML'
<script>alert('XSS');</script>
<iframe src="javascript:alert('XSS')"></iframe>
<img src="x" onerror="alert('XSS')">
<a href="javascript:void(0)">Suspicious link</a>
HTML;

        $file = $this->createTestFile('tests/fixtures/security-test.html', $content);
        $results = $this->scanner->scanFile($file);

        $this->assertArrayHasKey('security_issues', $results);
        $issues = $results['security_issues'];

        $this->assertNotEmpty($issues);
        $this->assertGreaterThan(0, count($issues));
    }
}