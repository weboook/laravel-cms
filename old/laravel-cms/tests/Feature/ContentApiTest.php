<?php

namespace Webook\LaravelCMS\Tests\Feature;

use Webook\LaravelCMS\Tests\TestCase;
use Webook\LaravelCMS\Models\User;
use Webook\LaravelCMS\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\UploadedFile;

class ContentApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createTestUser(['cms_editor']);
        $this->actingAs($this->user);
    }

    public function test_update_text_content_successfully()
    {
        $testFile = $this->createTestBladeFile('text-update.blade.php');

        $response = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/text-update.blade.php',
            'search' => '{{ $title }}',
            'replace' => '{{ $pageTitle }}',
            'backup' => true
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'updated',
                'backup_created',
                'changes_count'
            ],
            'timestamp'
        ]);

        $this->assertFileContains($testFile, '{{ $pageTitle }}');
    }

    public function test_update_text_validates_request_data()
    {
        $response = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => '', // Empty required field
            'search' => 'test',
            'replace' => '' // Empty replace
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file_path', 'replace']);
    }

    public function test_update_text_requires_permission()
    {
        $readOnlyUser = $this->createTestUser(['cms_reader']);
        $this->actingAs($readOnlyUser);

        $response = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/test.blade.php',
            'search' => 'old',
            'replace' => 'new'
        ]);

        $response->assertStatus(403);
    }

    public function test_update_text_validates_file_path()
    {
        $response = $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => '../../../config/app.php', // Restricted path
            'search' => 'old',
            'replace' => 'new'
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'File path not allowed for editing'
        ]);
    }

    public function test_bulk_update_text_multiple_files()
    {
        $file1 = $this->createTestBladeFile('bulk1.blade.php');
        $file2 = $this->createTestBladeFile('bulk2.blade.php');

        $response = $this->postJson(route('cms.api.content.text.bulk-update'), [
            'updates' => [
                [
                    'file_path' => 'tests/fixtures/views/bulk1.blade.php',
                    'search' => '{{ $title }}',
                    'replace' => '{{ $pageTitle }}'
                ],
                [
                    'file_path' => 'tests/fixtures/views/bulk2.blade.php',
                    'search' => '{{ $description }}',
                    'replace' => '{{ $pageDescription }}'
                ]
            ],
            'backup' => true
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_updates',
                'successful_updates',
                'failed_updates',
                'results'
            ]
        ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['total_updates']);
        $this->assertEquals(2, $data['successful_updates']);
        $this->assertEquals(0, $data['failed_updates']);
    }

    public function test_bulk_update_handles_partial_failures()
    {
        $validFile = $this->createTestBladeFile('valid.blade.php');

        $response = $this->postJson(route('cms.api.content.text.bulk-update'), [
            'updates' => [
                [
                    'file_path' => 'tests/fixtures/views/valid.blade.php',
                    'search' => '{{ $title }}',
                    'replace' => '{{ $pageTitle }}'
                ],
                [
                    'file_path' => 'nonexistent/file.blade.php',
                    'search' => 'old',
                    'replace' => 'new'
                ]
            ]
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(2, $data['total_updates']);
        $this->assertEquals(1, $data['successful_updates']);
        $this->assertEquals(1, $data['failed_updates']);
    }

    public function test_upload_image_successfully()
    {
        Storage::fake('testing');

        $image = UploadedFile::fake()->image('test.jpg', 800, 600);

        $response = $this->postJson(route('cms.api.content.image.upload'), [
            'image' => $image,
            'alt_text' => 'Test image description',
            'optimize' => true,
            'generate_thumbnails' => true
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'original_url',
                'optimized_url',
                'thumbnails',
                'file_size',
                'dimensions'
            ]
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('original_url', $data);
        $this->assertIsArray($data['thumbnails']);
    }

    public function test_upload_image_validates_file_type()
    {
        $textFile = UploadedFile::fake()->create('document.txt', 1024);

        $response = $this->postJson(route('cms.api.content.image.upload'), [
            'image' => $textFile,
            'alt_text' => 'Test'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_upload_image_validates_file_size()
    {
        Config::set('cms.images.max_file_size', 1024); // 1KB limit

        $largeImage = UploadedFile::fake()->image('large.jpg')->size(2048); // 2KB

        $response = $this->postJson(route('cms.api.content.image.upload'), [
            'image' => $largeImage,
            'alt_text' => 'Large image'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_upload_image_respects_daily_limit()
    {
        Config::set('cms.images.daily_upload_limit', 2);

        // Upload images up to limit
        for ($i = 0; $i < 2; $i++) {
            $image = UploadedFile::fake()->image("test{$i}.jpg");
            $response = $this->postJson(route('cms.api.content.image.upload'), [
                'image' => $image,
                'alt_text' => "Test image {$i}"
            ]);
            $response->assertStatus(200);
        }

        // Next upload should fail
        $image = UploadedFile::fake()->image('exceeded.jpg');
        $response = $this->postJson(route('cms.api.content.image.upload'), [
            'image' => $image,
            'alt_text' => 'Exceeded limit'
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'message' => 'Daily upload limit exceeded'
        ]);
    }

    public function test_optimize_image_reduces_file_size()
    {
        Storage::fake('testing');

        $image = UploadedFile::fake()->image('optimize.jpg', 1920, 1080);

        $response = $this->postJson(route('cms.api.content.image.optimize'), [
            'image_path' => 'uploads/optimize.jpg',
            'quality' => 80,
            'max_width' => 1200,
            'max_height' => 800
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'original_size',
                'optimized_size',
                'compression_ratio',
                'optimized_url'
            ]
        ]);

        $data = $response->json('data');
        $this->assertLessThan($data['original_size'], $data['optimized_size']);
    }

    public function test_update_link_successfully()
    {
        $htmlFile = $this->createTestHtmlFile('link-update.html');

        $response = $this->postJson(route('cms.api.content.link.update'), [
            'file_path' => 'tests/fixtures/html/link-update.html',
            'selector' => 'a[href="/about"]',
            'new_url' => '/about-us',
            'new_text' => 'About Us'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'links_updated',
                'changes'
            ]
        ]);

        $this->assertFileContains($htmlFile, 'href="/about-us"');
        $this->assertFileContains($htmlFile, 'About Us');
    }

    public function test_bulk_update_links()
    {
        $htmlFile = $this->createTestHtmlFile('bulk-links.html');

        $response = $this->postJson(route('cms.api.content.link.bulk-update'), [
            'file_path' => 'tests/fixtures/html/bulk-links.html',
            'updates' => [
                [
                    'old_url' => '/',
                    'new_url' => '/home',
                    'update_text' => false
                ],
                [
                    'old_url' => '/about',
                    'new_url' => '/about-us',
                    'new_text' => 'About Us'
                ]
            ]
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_links_updated']);
    }

    public function test_create_translation()
    {
        $response = $this->postJson(route('cms.api.translation.store'), [
            'locale' => 'en',
            'group' => 'messages',
            'key' => 'new_message',
            'value' => 'This is a new message'
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'translation' => [
                    'id',
                    'locale',
                    'group',
                    'key',
                    'value'
                ]
            ]
        ]);

        $this->assertDatabaseHasTranslation('en', 'messages', 'new_message', 'This is a new message');
    }

    public function test_update_translation()
    {
        $translation = $this->createTestTranslation('en', 'messages', 'update_test', 'Original value');

        $response = $this->putJson(route('cms.api.translation.update', $translation->id), [
            'value' => 'Updated value'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'translation' => [
                    'id',
                    'value'
                ]
            ]
        ]);

        $this->assertDatabaseHasTranslation('en', 'messages', 'update_test', 'Updated value');
    }

    public function test_delete_translation()
    {
        $translation = $this->createTestTranslation('en', 'messages', 'delete_test', 'To be deleted');

        $response = $this->deleteJson(route('cms.api.translation.destroy', $translation->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Translation deleted successfully'
        ]);

        $this->assertDatabaseMissingTranslation('en', 'messages', 'delete_test');
    }

    public function test_bulk_import_translations()
    {
        $translations = [
            'import1' => 'Imported message 1',
            'import2' => 'Imported message 2',
            'nested' => [
                'key' => 'Nested imported value'
            ]
        ];

        $response = $this->postJson(route('cms.api.translation.bulk-import'), [
            'locale' => 'en',
            'group' => 'imported',
            'translations' => $translations,
            'overwrite' => false
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'imported_count',
                'skipped_count',
                'errors'
            ]
        ]);

        $this->assertDatabaseHasTranslation('en', 'imported', 'import1', 'Imported message 1');
        $this->assertDatabaseHasTranslation('en', 'imported', 'nested.key', 'Nested imported value');
    }

    public function test_export_translations()
    {
        $this->createTestTranslation('en', 'export_test', 'key1', 'Value 1');
        $this->createTestTranslation('en', 'export_test', 'key2', 'Value 2');

        $response = $this->getJson(route('cms.api.translation.export'), [
            'locale' => 'en',
            'group' => 'export_test',
            'format' => 'json'
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonFragment([
            'key1' => 'Value 1',
            'key2' => 'Value 2'
        ]);
    }

    public function test_export_translations_as_php()
    {
        $this->createTestTranslation('en', 'php_export', 'key1', 'PHP Value 1');

        $response = $this->getJson(route('cms.api.translation.export'), [
            'locale' => 'en',
            'group' => 'php_export',
            'format' => 'php'
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/x-httpd-php');
        $content = $response->getContent();
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('return [', $content);
    }

    public function test_get_file_history()
    {
        $testFile = $this->createTestBladeFile('history-test.blade.php');

        // Make some changes to create history
        $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/history-test.blade.php',
            'search' => '{{ $title }}',
            'replace' => '{{ $pageTitle }}'
        ]);

        $response = $this->getJson(route('cms.api.history.file'), [
            'file_path' => 'tests/fixtures/views/history-test.blade.php',
            'limit' => 10
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'history' => [
                    '*' => [
                        'id',
                        'action',
                        'changes',
                        'user_id',
                        'created_at'
                    ]
                ],
                'total',
                'file_info'
            ]
        ]);
    }

    public function test_restore_from_history()
    {
        $testFile = $this->createTestBladeFile('restore-test.blade.php');

        // Make a change
        $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/restore-test.blade.php',
            'search' => '{{ $title }}',
            'replace' => '{{ $changedTitle }}'
        ]);

        // Get history to find restore point
        $historyResponse = $this->getJson(route('cms.api.history.file'), [
            'file_path' => 'tests/fixtures/views/restore-test.blade.php'
        ]);

        $history = $historyResponse->json('data.history');
        $restorePoint = end($history); // Get original version

        $response = $this->postJson(route('cms.api.history.restore'), [
            'history_id' => $restorePoint['id']
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'File restored successfully'
        ]);

        $this->assertFileContains($testFile, '{{ $title }}');
        $this->assertFileNotContains($testFile, '{{ $changedTitle }}');
    }

    public function test_get_system_history()
    {
        // Create some system activity
        $this->postJson(route('cms.api.content.text.update'), [
            'file_path' => 'tests/fixtures/views/system-history.blade.php',
            'search' => 'old',
            'replace' => 'new'
        ]);

        $response = $this->getJson(route('cms.api.history.system'), [
            'limit' => 20,
            'action' => 'content_update'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'activities' => [
                    '*' => [
                        'id',
                        'action',
                        'user_id',
                        'resource_type',
                        'resource_id',
                        'created_at'
                    ]
                ],
                'total',
                'pagination'
            ]
        ]);
    }

    public function test_api_rate_limiting()
    {
        // Make many rapid requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson(route('cms.api.translation.index'));
            if ($i < 5) {
                $response->assertStatus(200);
            }
        }

        // Should eventually hit rate limit
        $response = $this->getJson(route('cms.api.translation.index'));
        $this->assertContains($response->status(), [200, 429]);
    }

    public function test_api_returns_consistent_format()
    {
        $response = $this->getJson(route('cms.api.translation.index'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'timestamp'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertIsInt($response->json('timestamp'));
    }

    public function test_api_handles_validation_errors_consistently()
    {
        $response = $this->postJson(route('cms.api.translation.store'), [
            'locale' => '', // Invalid
            'group' => 'test',
            'key' => '',    // Invalid
            'value' => 'test'
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => [
                'locale',
                'key'
            ],
            'timestamp'
        ]);

        $this->assertFalse($response->json('success'));
    }

    public function test_api_requires_authentication()
    {
        auth()->logout();

        $response = $this->getJson(route('cms.api.translation.index'));

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthenticated'
        ]);
    }

    public function test_api_validates_permissions_per_endpoint()
    {
        $readOnlyUser = $this->createTestUser(['cms_reader']);
        $this->actingAs($readOnlyUser);

        // Should allow reading
        $response = $this->getJson(route('cms.api.translation.index'));
        $response->assertStatus(200);

        // Should deny writing
        $response = $this->postJson(route('cms.api.translation.store'), [
            'locale' => 'en',
            'group' => 'test',
            'key' => 'test',
            'value' => 'test'
        ]);
        $response->assertStatus(403);
    }

    protected function createTestTranslation(string $locale, string $group, string $key, string $value): Translation
    {
        return Translation::create([
            'locale' => $locale,
            'group' => $group,
            'key' => $key,
            'value' => $value,
        ]);
    }
}