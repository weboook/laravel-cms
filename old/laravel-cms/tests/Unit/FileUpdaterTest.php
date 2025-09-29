<?php

namespace Webook\LaravelCMS\Tests\Unit;

use Webook\LaravelCMS\Tests\TestCase;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Exceptions\CMSException;
use Webook\LaravelCMS\Strategies\BladeStrategy;
use Webook\LaravelCMS\Strategies\DOMStrategy;
use Webook\LaravelCMS\Strategies\TextStrategy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class FileUpdaterTest extends TestCase
{
    protected FileUpdater $updater;
    protected string $testFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->updater = new FileUpdater();
    }

    public function test_updates_blade_content_correctly()
    {
        $content = <<<'BLADE'
@extends('layouts.app')

@section('content')
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
@endsection
BLADE;

        $this->testFile = $this->createTestFile('tests/fixtures/blade-update.blade.php', $content);

        $result = $this->updater->updateContent($this->testFile, 'Original Title', 'Updated Title');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'Updated Title');
        $this->assertFileNotContains($this->testFile, 'Original Title');
    }

    public function test_updates_html_content_by_selector()
    {
        $content = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Original Title</title>
</head>
<body>
    <h1 id="main-heading">Original Heading</h1>
    <p class="description">Original description</p>
</body>
</html>
HTML;

        $this->testFile = $this->createTestFile('tests/fixtures/html-update.html', $content);

        $result = $this->updater->updateBySelector($this->testFile, '#main-heading', 'Updated Heading');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'Updated Heading');
        $this->assertFileNotContains($this->testFile, 'Original Heading');
    }

    public function test_updates_content_by_line_number()
    {
        $content = <<<'TEXT'
Line 1: Original content
Line 2: This will be updated
Line 3: This stays the same
Line 4: Another line
TEXT;

        $this->testFile = $this->createTestFile('tests/fixtures/line-update.txt', $content);

        $result = $this->updater->updateByLineNumber($this->testFile, 2, 'Line 2: This was updated');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'Line 2: This was updated');
        $this->assertFileNotContains($this->testFile, 'Line 2: This will be updated');
    }

    public function test_updates_html_attributes()
    {
        $content = <<<'HTML'
<div class="container">
    <img src="old-image.jpg" alt="Old Alt Text" title="Old Title">
    <a href="old-link.html" class="nav-link">Link Text</a>
</div>
HTML;

        $this->testFile = $this->createTestFile('tests/fixtures/attr-update.html', $content);

        $result = $this->updater->updateAttribute($this->testFile, 'img', 'src', 'new-image.jpg');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'src="new-image.jpg"');
        $this->assertFileNotContains($this->testFile, 'src="old-image.jpg"');
    }

    public function test_batch_update_multiple_changes()
    {
        $content = <<<'BLADE'
@extends('layouts.app')

@section('title', 'Original Title')

@section('content')
    <h1>{{ $heading }}</h1>
    <p>{{ $description }}</p>
    <div class="alert">{{ $message }}</div>
@endsection
BLADE;

        $this->testFile = $this->createTestFile('tests/fixtures/batch-update.blade.php', $content);

        $updates = [
            [
                'type' => 'content',
                'search' => 'Original Title',
                'replace' => 'New Title'
            ],
            [
                'type' => 'content',
                'search' => '{{ $heading }}',
                'replace' => '{{ $pageHeading }}'
            ],
            [
                'type' => 'content',
                'search' => '{{ $description }}',
                'replace' => '{{ $pageDescription }}'
            ]
        ];

        $result = $this->updater->batchUpdate($this->testFile, $updates);

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'New Title');
        $this->assertFileContains($this->testFile, '{{ $pageHeading }}');
        $this->assertFileContains($this->testFile, '{{ $pageDescription }}');
    }

    public function test_creates_backup_before_update()
    {
        Config::set('cms.content.auto_backup', true);

        $content = 'Original content that will be updated';
        $this->testFile = $this->createTestFile('tests/fixtures/backup-test.txt', $content);

        $this->updater->updateContent($this->testFile, 'Original content', 'Updated content');

        $this->assertBackupCreated($this->testFile);
    }

    public function test_validates_file_permissions()
    {
        $this->testFile = $this->createTestFile('tests/fixtures/readonly-test.txt', 'Read only content');
        chmod($this->testFile, 0444); // Read-only

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('File is not writable');

        $this->updater->updateContent($this->testFile, 'Read only', 'Updated');
    }

    public function test_validates_allowed_paths()
    {
        Config::set('cms.content.allowed_paths', ['tests/fixtures']);

        $allowedFile = $this->createTestFile('tests/fixtures/allowed.txt', 'Allowed content');
        $disallowedFile = $this->createTestFile('tests/forbidden/disallowed.txt', 'Forbidden content');

        // Should work for allowed path
        $result = $this->updater->updateContent($allowedFile, 'Allowed', 'Updated');
        $this->assertTrue($result);

        // Should fail for disallowed path
        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('File path not allowed');

        $this->updater->updateContent($disallowedFile, 'Forbidden', 'Updated');
    }

    public function test_validates_file_extensions()
    {
        Config::set('cms.content.allowed_extensions', ['blade.php', 'html', 'txt']);

        $validFile = $this->createTestFile('tests/fixtures/valid.html', 'Valid content');
        $invalidFile = $this->createTestFile('tests/fixtures/invalid.exe', 'Invalid content');

        // Should work for valid extension
        $result = $this->updater->updateContent($validFile, 'Valid', 'Updated');
        $this->assertTrue($result);

        // Should fail for invalid extension
        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('File extension not allowed');

        $this->updater->updateContent($invalidFile, 'Invalid', 'Updated');
    }

    public function test_detects_proper_strategy_for_file_type()
    {
        $bladeFile = $this->createTestFile('tests/fixtures/strategy-test.blade.php', '@extends("layout")');
        $htmlFile = $this->createTestFile('tests/fixtures/strategy-test.html', '<html><title>Test</title></html>');
        $textFile = $this->createTestFile('tests/fixtures/strategy-test.txt', 'Plain text content');

        $bladeStrategy = $this->updater->determineStrategy($bladeFile);
        $htmlStrategy = $this->updater->determineStrategy($htmlFile);
        $textStrategy = $this->updater->determineStrategy($textFile);

        $this->assertInstanceOf(BladeStrategy::class, $bladeStrategy);
        $this->assertInstanceOf(DOMStrategy::class, $htmlStrategy);
        $this->assertInstanceOf(TextStrategy::class, $textStrategy);
    }

    public function test_handles_blade_variables_correctly()
    {
        $content = <<<'BLADE'
<h1>{{ $title }}</h1>
<p>{{ $description }}</p>
<div>{{ $user->name }}</div>
<span>{{ $items[0]['name'] }}</span>
BLADE;

        $this->testFile = $this->createTestFile('tests/fixtures/blade-vars.blade.php', $content);

        $result = $this->updater->updateContent($this->testFile, '{{ $title }}', '{{ $pageTitle }}');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, '{{ $pageTitle }}');
        $this->assertFileNotContains($this->testFile, '{{ $title }}');
        $this->assertFileContains($this->testFile, '{{ $description }}'); // Should remain unchanged
    }

    public function test_handles_blade_directives_correctly()
    {
        $content = <<<'BLADE'
@extends('layouts.app')

@section('title', 'Page Title')

@section('content')
    @if($showAlert)
        <div class="alert">{{ $message }}</div>
    @endif

    @foreach($items as $item)
        <div>{{ $item->name }}</div>
    @endforeach
@endsection
BLADE;

        $this->testFile = $this->createTestFile('tests/fixtures/blade-directives.blade.php', $content);

        $result = $this->updater->updateContent($this->testFile, '@if($showAlert)', '@if($displayAlert)');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, '@if($displayAlert)');
        $this->assertFileNotContains($this->testFile, '@if($showAlert)');
    }

    public function test_preserves_blade_structure()
    {
        $content = <<<'BLADE'
@extends('layouts.app')

@section('content')
    <div class="container">
        @include('partials.header')

        <main>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </main>

        @include('partials.footer')
    </div>
@endsection
BLADE;

        $this->testFile = $this->createTestFile('tests/fixtures/blade-structure.blade.php', $content);

        $result = $this->updater->updateContent($this->testFile, '{{ $title }}', '{{ $pageTitle }}');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, '@extends(\'layouts.app\')');
        $this->assertFileContains($this->testFile, '@section(\'content\')');
        $this->assertFileContains($this->testFile, '@include(\'partials.header\')');
        $this->assertFileContains($this->testFile, '{{ $pageTitle }}');
    }

    public function test_handles_complex_html_updates()
    {
        $content = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Original Title</title>
</head>
<body>
    <nav class="navbar">
        <a href="/home" class="nav-link active">Home</a>
        <a href="/about" class="nav-link">About</a>
    </nav>
    <main>
        <h1 id="page-title">Original Heading</h1>
        <p class="description">Original description with <strong>formatting</strong>.</p>
    </main>
</body>
</html>
HTML;

        $this->testFile = $this->createTestFile('tests/fixtures/complex-html.html', $content);

        $result = $this->updater->updateBySelector($this->testFile, 'title', 'Updated Title');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, '<title>Updated Title</title>');
        $this->assertFileNotContains($this->testFile, '<title>Original Title</title>');
    }

    public function test_validates_selector_format()
    {
        $this->testFile = $this->createTestHtmlFile();

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Invalid CSS selector');

        $this->updater->updateBySelector($this->testFile, 'invalid>>selector', 'New content');
    }

    public function test_handles_missing_selector_gracefully()
    {
        $this->testFile = $this->createTestHtmlFile();

        $result = $this->updater->updateBySelector($this->testFile, '#nonexistent-element', 'New content');

        $this->assertFalse($result);
    }

    public function test_validates_line_number_bounds()
    {
        $content = "Line 1\nLine 2\nLine 3";
        $this->testFile = $this->createTestFile('tests/fixtures/line-bounds.txt', $content);

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Line number out of bounds');

        $this->updater->updateByLineNumber($this->testFile, 10, 'New content');
    }

    public function test_search_and_replace_with_regex()
    {
        $content = <<<'TEXT'
Phone: 123-456-7890
Phone: 987-654-3210
Email: user@example.com
Phone: 555-123-4567
TEXT;

        $this->testFile = $this->createTestFile('tests/fixtures/regex-test.txt', $content);

        $result = $this->updater->searchAndReplace($this->testFile, '/Phone: \d{3}-\d{3}-\d{4}/', 'Phone: [REDACTED]', true);

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'Phone: [REDACTED]');
        $this->assertFileNotContains($this->testFile, '123-456-7890');
        $this->assertFileContains($this->testFile, 'Email: user@example.com'); // Should remain unchanged
    }

    public function test_search_and_replace_case_insensitive()
    {
        $content = 'Hello WORLD and hello world and Hello World';
        $this->testFile = $this->createTestFile('tests/fixtures/case-test.txt', $content);

        $result = $this->updater->searchAndReplace($this->testFile, 'hello', 'hi', false, ['case_insensitive' => true]);

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'hi WORLD and hi world and hi World');
    }

    public function test_dry_run_mode()
    {
        $originalContent = 'Original content to be changed';
        $this->testFile = $this->createTestFile('tests/fixtures/dry-run.txt', $originalContent);

        $result = $this->updater->updateContent($this->testFile, 'Original', 'Updated', ['dry_run' => true]);

        $this->assertTrue($result);
        // File should remain unchanged in dry run
        $this->assertFileContains($this->testFile, 'Original content to be changed');
        $this->assertFileNotContains($this->testFile, 'Updated content to be changed');
    }

    public function test_preview_changes()
    {
        $content = 'Original content that will be updated';
        $this->testFile = $this->createTestFile('tests/fixtures/preview.txt', $content);

        $preview = $this->updater->previewChanges($this->testFile, 'Original', 'Updated');

        $this->assertArrayHasKey('original', $preview);
        $this->assertArrayHasKey('updated', $preview);
        $this->assertArrayHasKey('changes', $preview);

        $this->assertEquals($content, $preview['original']);
        $this->assertEquals('Updated content that will be updated', $preview['updated']);
        $this->assertNotEmpty($preview['changes']);
    }

    public function test_tracks_file_changes()
    {
        Config::set('cms.history.retention_days', 30);

        $this->testFile = $this->createTestFile('tests/fixtures/tracked.txt', 'Original content');

        $this->updater->updateContent($this->testFile, 'Original', 'First update');
        $this->updater->updateContent($this->testFile, 'First update', 'Second update');

        $history = $this->updater->getFileHistory($this->testFile);

        $this->assertCount(3, $history); // Original + 2 updates
        $this->assertEquals('Second update content', $history[0]['content']); // Latest first
    }

    public function test_restores_from_backup()
    {
        Config::set('cms.content.auto_backup', true);

        $originalContent = 'Original content before changes';
        $this->testFile = $this->createTestFile('tests/fixtures/restore.txt', $originalContent);

        // Make an update (creates backup)
        $this->updater->updateContent($this->testFile, 'Original', 'Updated');

        // Restore from backup
        $result = $this->updater->restoreFromBackup($this->testFile);

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'Original content before changes');
    }

    public function test_performance_with_large_files()
    {
        $largeContent = str_repeat("Line with content that will be processed\n", 10000);
        $this->testFile = $this->createTestFile('tests/fixtures/large-file.txt', $largeContent);

        $this->assertPerformance(function () {
            $this->updater->updateContent($this->testFile, 'content that will be', 'content that was');
        }, 3.0, 100 * 1024 * 1024); // 3 seconds, 100MB
    }

    public function test_handles_encoding_correctly()
    {
        $unicodeContent = 'Héllo Wørld with émojis 🌟 and spéciál characters';
        $this->testFile = $this->createTestFile('tests/fixtures/unicode.txt', $unicodeContent);

        $result = $this->updater->updateContent($this->testFile, 'Héllo', 'Hëllo');

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'Hëllo Wørld with émojis 🌟');
    }

    public function test_atomic_updates()
    {
        $this->testFile = $this->createTestFile('tests/fixtures/atomic.txt', 'Original content');

        // Simulate file being read by another process during update
        $result = $this->updater->updateContent($this->testFile, 'Original', 'Updated', ['atomic' => true]);

        $this->assertTrue($result);
        $this->assertFileContains($this->testFile, 'Updated content');
    }

    public function test_validates_content_safety()
    {
        $maliciousContent = '<script>alert("XSS")</script>';
        $this->testFile = $this->createTestHtmlFile();

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Content contains potentially unsafe elements');

        $this->updater->updateBySelector($this->testFile, 'body', $maliciousContent);
    }

    public function test_transaction_rollback_on_failure()
    {
        $originalContent = 'Original safe content';
        $this->testFile = $this->createTestFile('tests/fixtures/transaction.txt', $originalContent);

        $updates = [
            [
                'type' => 'content',
                'search' => 'Original',
                'replace' => 'Updated'
            ],
            [
                'type' => 'invalid_operation', // This will cause failure
                'search' => 'safe',
                'replace' => 'modified'
            ]
        ];

        $result = $this->updater->batchUpdate($this->testFile, $updates);

        $this->assertFalse($result);
        // File should remain unchanged due to rollback
        $this->assertFileContains($this->testFile, 'Original safe content');
    }
}