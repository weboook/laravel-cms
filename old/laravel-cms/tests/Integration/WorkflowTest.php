<?php

namespace Webook\LaravelCMS\Tests\Integration;

use Webook\LaravelCMS\Tests\TestCase;
use Webook\LaravelCMS\Models\User;
use Webook\LaravelCMS\Models\Translation;
use Webook\LaravelCMS\Services\ContentScanner;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\TranslationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;

class WorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected ContentScanner $scanner;
    protected FileUpdater $updater;
    protected TranslationManager $translationManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createTestUser(['cms_editor', 'cms_translator']);
        $this->actingAs($this->user);

        $this->scanner = new ContentScanner();
        $this->updater = new FileUpdater();
        $this->translationManager = new TranslationManager();
    }

    public function test_complete_content_editing_workflow()
    {
        // Step 1: Create initial content files
        $bladeFile = $this->createTestBladeFile('workflow-test.blade.php');
        $htmlFile = $this->createTestHtmlFile('workflow-test.html');

        // Step 2: Scan content to discover editable elements
        $scanResults = $this->scanner->scanPath('tests/fixtures');

        $this->assertNotEmpty($scanResults);
        $this->assertArrayHasKey('blade', $scanResults);
        $this->assertArrayHasKey('html', $scanResults);

        // Step 3: Access editor interface
        $response = $this->get(route('cms.editor.index'));
        $response->assertStatus(200);
        $response->assertViewIs('cms.editor');

        // Step 4: Preview a file
        $previewResponse = $this->get(route('cms.editor.preview', [
            'url' => 'tests/fixtures/views/workflow-test.blade.php'
        ]));
        $previewResponse->assertStatus(200);

        // Step 5: Update content via API
        $updateResponse = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/workflow-test.blade.php',
            'search' => '{{ $title }}',
            'replace' => '{{ $workflowTitle }}',
            'backup' => true
        ]);
        $updateResponse->assertStatus(200);

        // Step 6: Verify the change was applied
        $this->assertFileContains($bladeFile, '{{ $workflowTitle }}');
        $this->assertFileNotContains($bladeFile, '{{ $title }}');

        // Step 7: Check that backup was created
        $this->assertBackupCreated($bladeFile);

        // Step 8: Verify change appears in history
        $historyResponse = $this->getJson(route('cms.api.history.file'), [
            'file_path' => 'tests/fixtures/views/workflow-test.blade.php'
        ]);
        $historyResponse->assertStatus(200);
        $history = $historyResponse->json('data.history');
        $this->assertNotEmpty($history);
    }

    public function test_translation_management_workflow()
    {
        // Step 1: Create some initial translations
        $englishTranslations = [
            'welcome' => 'Welcome to our site',
            'about' => 'About Us',
            'contact' => 'Contact Information',
            'nested.deep.value' => 'Deep nested value'
        ];

        foreach ($englishTranslations as $key => $value) {
            $this->translationManager->set('en', "messages.{$key}", $value);
        }

        // Step 2: Verify translations were created
        foreach ($englishTranslations as $key => $value) {
            $retrieved = $this->translationManager->get('en', "messages.{$key}");
            $this->assertEquals($value, $retrieved);
        }

        // Step 3: Bulk import Spanish translations
        $spanishTranslations = [
            'welcome' => 'Bienvenido a nuestro sitio',
            'about' => 'Acerca de Nosotros',
            'contact' => 'Información de Contacto'
        ];

        $importResponse = $this->postJson(route('cms.api.translation.bulk-import'), [
            'locale' => 'es',
            'group' => 'messages',
            'translations' => $spanishTranslations,
            'overwrite' => false
        ]);
        $importResponse->assertStatus(200);

        // Step 4: Find missing translations
        $missing = $this->translationManager->findMissingTranslations('es', 'en');
        $this->assertContains('messages.nested.deep.value', $missing);

        // Step 5: Create the missing translation
        $createResponse = $this->postJson(route('cms.api.translation.store'), [
            'locale' => 'es',
            'group' => 'messages',
            'key' => 'nested.deep.value',
            'value' => 'Valor anidado profundo'
        ]);
        $createResponse->assertStatus(201);

        // Step 6: Export translations
        $exportResponse = $this->getJson(route('cms.api.translation.export'), [
            'locale' => 'es',
            'group' => 'messages',
            'format' => 'json'
        ]);
        $exportResponse->assertStatus(200);

        $exportedData = $exportResponse->json();
        $this->assertEquals('Bienvenido a nuestro sitio', $exportedData['welcome']);
        $this->assertEquals('Valor anidado profundo', $exportedData['nested']['deep']['value']);
    }

    public function test_image_upload_and_optimization_workflow()
    {
        Storage::fake('testing');
        Config::set('cms.images.generate_thumbnails', true);

        // Step 1: Upload an image
        $image = \Illuminate\Http\UploadedFile::fake()->image('workflow-image.jpg', 1920, 1080);

        $uploadResponse = $this->postJson(route('cms.api.content.image.upload'), [
            'image' => $image,
            'alt_text' => 'Workflow test image',
            'optimize' => true,
            'generate_thumbnails' => true
        ]);
        $uploadResponse->assertStatus(200);

        $uploadData = $uploadResponse->json('data');
        $this->assertArrayHasKey('original_url', $uploadData);
        $this->assertArrayHasKey('optimized_url', $uploadData);
        $this->assertArrayHasKey('thumbnails', $uploadData);

        // Step 2: Verify image was stored
        $originalPath = str_replace('/storage/', '', $uploadData['original_url']);
        Storage::disk('testing')->assertExists($originalPath);

        // Step 3: Verify thumbnails were generated
        $thumbnails = $uploadData['thumbnails'];
        $this->assertNotEmpty($thumbnails);

        foreach ($thumbnails as $thumbnail) {
            $thumbPath = str_replace('/storage/', '', $thumbnail['url']);
            Storage::disk('testing')->assertExists($thumbPath);
        }

        // Step 4: Update HTML file to use the uploaded image
        $htmlFile = $this->createTestHtmlFile('image-workflow.html');

        $updateResponse = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/html/image-workflow.html',
            'search' => 'src="/images/test.jpg"',
            'replace' => 'src="' . $uploadData['original_url'] . '"'
        ]);
        $updateResponse->assertStatus(200);

        // Step 5: Verify the image reference was updated
        $this->assertFileContains($htmlFile, $uploadData['original_url']);
    }

    public function test_multi_file_content_update_workflow()
    {
        // Step 1: Create multiple related files
        $files = [
            'header.blade.php' => '@section("title", "{{ $pageTitle }}")',
            'content.blade.php' => '<h1>{{ $pageTitle }}</h1><p>{{ $pageDescription }}</p>',
            'footer.blade.php' => '<p>&copy; {{ $currentYear }} {{ $siteName }}</p>'
        ];

        $createdFiles = [];
        foreach ($files as $filename => $content) {
            $createdFiles[$filename] = $this->createTestFile("tests/fixtures/views/{$filename}", $content);
        }

        // Step 2: Scan all files to understand structure
        $scanResponse = $this->postJson(route('cms.editor.scan'), [
            'path' => 'tests/fixtures/views',
            'recursive' => false
        ]);
        $scanResponse->assertStatus(200);

        // Step 3: Perform bulk update across all files
        $updates = [
            [
                'file_path' => 'tests/fixtures/views/header.blade.php',
                'search' => '{{ $pageTitle }}',
                'replace' => '{{ $mainTitle }}'
            ],
            [
                'file_path' => 'tests/fixtures/views/content.blade.php',
                'search' => '{{ $pageTitle }}',
                'replace' => '{{ $mainTitle }}'
            ],
            [
                'file_path' => 'tests/fixtures/views/content.blade.php',
                'search' => '{{ $pageDescription }}',
                'replace' => '{{ $mainDescription }}'
            ]
        ];

        $bulkResponse = $this->postJson(route('cms.api.content.text.bulk-update'), [
            'updates' => $updates,
            'backup' => true
        ]);
        $bulkResponse->assertStatus(200);

        $bulkData = $bulkResponse->json('data');
        $this->assertEquals(3, $bulkData['total_updates']);
        $this->assertEquals(3, $bulkData['successful_updates']);
        $this->assertEquals(0, $bulkData['failed_updates']);

        // Step 4: Verify all changes were applied
        $this->assertFileContains($createdFiles['header.blade.php'], '{{ $mainTitle }}');
        $this->assertFileContains($createdFiles['content.blade.php'], '{{ $mainTitle }}');
        $this->assertFileContains($createdFiles['content.blade.php'], '{{ $mainDescription }}');

        // Step 5: Verify original content was replaced
        $this->assertFileNotContains($createdFiles['header.blade.php'], '{{ $pageTitle }}');
        $this->assertFileNotContains($createdFiles['content.blade.php'], '{{ $pageTitle }}');
        $this->assertFileNotContains($createdFiles['content.blade.php'], '{{ $pageDescription }}');
    }

    public function test_error_handling_and_recovery_workflow()
    {
        $testFile = $this->createTestBladeFile('error-workflow.blade.php');

        // Step 1: Make a successful update
        $successResponse = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/error-workflow.blade.php',
            'search' => '{{ $title }}',
            'replace' => '{{ $successTitle }}',
            'backup' => true
        ]);
        $successResponse->assertStatus(200);

        // Step 2: Attempt an invalid update (should fail gracefully)
        $failResponse = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/error-workflow.blade.php',
            'search' => 'nonexistent_content',
            'replace' => 'replacement'
        ]);
        $failResponse->assertStatus(200); // Should succeed but report no changes

        $failData = $failResponse->json('data');
        $this->assertEquals(0, $failData['changes_count']);

        // Step 3: Get file history
        $historyResponse = $this->getJson(route('cms.api.history.file'), [
            'file_path' => 'tests/fixtures/views/error-workflow.blade.php'
        ]);
        $historyResponse->assertStatus(200);

        $history = $historyResponse->json('data.history');
        $this->assertCount(2, $history); // Original + successful update

        // Step 4: Restore to previous version
        $restorePoint = end($history); // Get original version

        $restoreResponse = $this->postJson(route('cms.api.history.restore'), [
            'history_id' => $restorePoint['id']
        ]);
        $restoreResponse->assertStatus(200);

        // Step 5: Verify restoration
        $this->assertFileContains($testFile, '{{ $title }}');
        $this->assertFileNotContains($testFile, '{{ $successTitle }}');
    }

    public function test_concurrent_editing_workflow()
    {
        $testFile = $this->createTestBladeFile('concurrent-workflow.blade.php');

        // Step 1: User 1 starts editing
        $user1 = $this->createTestUser(['cms_editor']);
        $this->actingAs($user1);

        $preview1 = $this->get(route('cms.editor.preview', [
            'url' => 'tests/fixtures/views/concurrent-workflow.blade.php'
        ]));
        $preview1->assertStatus(200);

        // Step 2: User 2 tries to edit the same file
        $user2 = $this->createTestUser(['cms_editor']);
        $this->actingAs($user2);

        $preview2 = $this->get(route('cms.editor.preview', [
            'url' => 'tests/fixtures/views/concurrent-workflow.blade.php'
        ]));
        $preview2->assertStatus(200);
        $preview2->assertViewHas('concurrentWarning', true);

        // Step 3: User 1 makes an update
        $this->actingAs($user1);
        $update1 = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/concurrent-workflow.blade.php',
            'search' => '{{ $title }}',
            'replace' => '{{ $user1Title }}'
        ]);
        $update1->assertStatus(200);

        // Step 4: User 2 tries to make a conflicting update
        $this->actingAs($user2);
        $update2 = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/concurrent-workflow.blade.php',
            'search' => '{{ $title }}', // This content no longer exists
            'replace' => '{{ $user2Title }}'
        ]);
        $update2->assertStatus(200); // Should succeed but report no changes

        $update2Data = $update2->json('data');
        $this->assertEquals(0, $update2Data['changes_count']);

        // Step 5: Verify only User 1's changes were applied
        $this->assertFileContains($testFile, '{{ $user1Title }}');
        $this->assertFileNotContains($testFile, '{{ $user2Title }}');
    }

    public function test_backup_and_restore_workflow()
    {
        Config::set('cms.content.auto_backup', true);

        $testFile = $this->createTestBladeFile('backup-workflow.blade.php');
        $originalContent = file_get_contents($testFile);

        // Step 1: Make multiple updates to create history
        $updates = [
            '{{ $title }}' => '{{ $newTitle }}',
            '{{ $description }}' => '{{ $newDescription }}',
            '{{ $heading }}' => '{{ $newHeading }}'
        ];

        foreach ($updates as $search => $replace) {
            $response = $this->postJson(route('cms.api.content.text.update'), [
                'file_path' => 'tests/fixtures/views/backup-workflow.blade.php',
                'search' => $search,
                'replace' => $replace,
                'backup' => true
            ]);
            $response->assertStatus(200);
        }

        // Step 2: Verify multiple backups were created
        $backupFiles = Storage::disk('testing')->files('backups');
        $workflowBackups = array_filter($backupFiles, function ($file) {
            return str_contains($file, 'backup-workflow');
        });
        $this->assertGreaterThan(0, count($workflowBackups));

        // Step 3: Get complete file history
        $historyResponse = $this->getJson(route('cms.api.history.file'), [
            'file_path' => 'tests/fixtures/views/backup-workflow.blade.php'
        ]);
        $historyResponse->assertStatus(200);

        $history = $historyResponse->json('data.history');
        $this->assertGreaterThanOrEqual(4, count($history)); // Original + 3 updates

        // Step 4: Restore to original version
        $originalVersion = end($history);

        $restoreResponse = $this->postJson(route('cms.api.history.restore'), [
            'history_id' => $originalVersion['id']
        ]);
        $restoreResponse->assertStatus(200);

        // Step 5: Verify file was restored to original state
        $restoredContent = file_get_contents($testFile);
        $this->assertEquals($originalContent, $restoredContent);
    }

    public function test_performance_monitoring_workflow()
    {
        // Step 1: Create many files for performance testing
        for ($i = 1; $i <= 50; $i++) {
            $this->createTestFile("tests/fixtures/performance/file_{$i}.blade.php",
                "@extends('layout') @section('title', 'Performance Test {$i}')");
        }

        // Step 2: Measure scan performance
        $this->assertPerformance(function () {
            $scanResponse = $this->postJson(route('cms.editor.scan'), [
                'path' => 'tests/fixtures/performance',
                'recursive' => true
            ]);
            $scanResponse->assertStatus(200);
        }, 3.0, 100 * 1024 * 1024); // 3 seconds, 100MB

        // Step 3: Measure bulk update performance
        $updates = [];
        for ($i = 1; $i <= 20; $i++) {
            $updates[] = [
                'file_path' => "tests/fixtures/performance/file_{$i}.blade.php",
                'search' => "Performance Test {$i}",
                'replace' => "Updated Performance Test {$i}"
            ];
        }

        $this->assertPerformance(function () use ($updates) {
            $bulkResponse = $this->postJson(route('cms.api.content.text.bulk-update'), [
                'updates' => $updates
            ]);
            $bulkResponse->assertStatus(200);
        }, 5.0, 200 * 1024 * 1024); // 5 seconds, 200MB

        // Step 4: Verify all updates were applied
        $bulkResponse = $this->postJson(route('cms.api.content.text.bulk-update'), [
            'updates' => array_slice($updates, 0, 5) // Test smaller batch
        ]);
        $bulkResponse->assertStatus(200);

        $bulkData = $bulkResponse->json('data');
        $this->assertEquals(5, $bulkData['successful_updates']);
    }

    public function test_cache_invalidation_workflow()
    {
        $testFile = $this->createTestBladeFile('cache-workflow.blade.php');

        // Step 1: Access file to populate cache
        $response1 = $this->get(route('cms.editor.preview', [
            'url' => 'tests/fixtures/views/cache-workflow.blade.php'
        ]));
        $response1->assertStatus(200);

        // Step 2: Verify content is cached
        $cacheKey = "cms_file_content_" . md5('tests/fixtures/views/cache-workflow.blade.php');
        $this->assertTrue(Cache::has($cacheKey));

        // Step 3: Update the file
        $updateResponse = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/cache-workflow.blade.php',
            'search' => '{{ $title }}',
            'replace' => '{{ $cachedTitle }}'
        ]);
        $updateResponse->assertStatus(200);

        // Step 4: Verify cache was invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Step 5: Access file again to verify fresh content
        $response2 = $this->get(route('cms.editor.preview', [
            'url' => 'tests/fixtures/views/cache-workflow.blade.php'
        ]));
        $response2->assertStatus(200);

        // Should contain updated content
        $this->assertFileContains($testFile, '{{ $cachedTitle }}');
    }
}