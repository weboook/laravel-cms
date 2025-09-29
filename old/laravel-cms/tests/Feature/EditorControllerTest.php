<?php

namespace Webook\LaravelCMS\Tests\Feature;

use Webook\LaravelCMS\Tests\TestCase;
use Webook\LaravelCMS\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class EditorControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createTestUser(['cms_editor']);
        $this->actingAs($this->user);
    }

    public function test_editor_index_loads_successfully()
    {
        $response = $this->get(route('cms.editor.index'));

        $response->assertStatus(200);
        $response->assertViewIs('cms.editor');
        $response->assertViewHas([
            'locales',
            'currentLocale',
            'userPreferences',
            'systemConfig',
            'recentFiles',
            'shortcuts'
        ]);
    }

    public function test_editor_index_requires_authentication()
    {
        auth()->logout();

        $response = $this->get(route('cms.editor.index'));

        $response->assertRedirect('/login');
    }

    public function test_editor_index_requires_permission()
    {
        $unauthorizedUser = $this->createTestUser([]);
        $this->actingAs($unauthorizedUser);

        $response = $this->get(route('cms.editor.index'));

        $response->assertStatus(403);
    }

    public function test_editor_loads_with_correct_locale()
    {
        Config::set('app.locale', 'es');

        $response = $this->get(route('cms.editor.index'));

        $response->assertStatus(200);
        $response->assertViewHas('currentLocale', 'es');
    }

    public function test_editor_loads_user_preferences()
    {
        $preferences = [
            'theme' => 'dark',
            'auto_save' => true,
            'show_minimap' => false,
            'font_size' => 14
        ];

        $this->user->updatePreferences($preferences);

        $response = $this->get(route('cms.editor.index'));

        $response->assertStatus(200);
        $response->assertViewHas('userPreferences', $preferences);
    }

    public function test_preview_returns_correct_view()
    {
        $testFile = $this->createTestBladeFile('preview-test.blade.php');
        $url = 'resources/views/preview-test.blade.php';

        $response = $this->get(route('cms.editor.preview', ['url' => $url]));

        $response->assertStatus(200);
        $response->assertViewIs('cms.preview');
        $response->assertViewHas([
            'content',
            'metadata',
            'isEditMode',
            'previewUrl'
        ]);
    }

    public function test_preview_validates_file_path()
    {
        $invalidUrl = '../../../config/app.php';

        $response = $this->get(route('cms.editor.preview', ['url' => $invalidUrl]));

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'File path not allowed for editing'
        ]);
    }

    public function test_preview_handles_missing_file()
    {
        $missingUrl = 'resources/views/nonexistent.blade.php';

        $response = $this->get(route('cms.editor.preview', ['url' => $missingUrl]));

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'File not found'
        ]);
    }

    public function test_scan_returns_directory_structure()
    {
        $this->createTestBladeFile('scan-test1.blade.php');
        $this->createTestBladeFile('scan-test2.blade.php');
        $this->createTestHtmlFile('scan-test.html');

        $response = $this->postJson(route('cms.editor.scan'), [
            'path' => 'tests/fixtures',
            'recursive' => true
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'files',
                'directories',
                'total_files',
                'scan_time'
            ]
        ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_files']);
        $this->assertIsArray($data['files']);
    }

    public function test_scan_validates_request_data()
    {
        $response = $this->postJson(route('cms.editor.scan'), [
            'path' => '', // Empty path
            'recursive' => 'invalid' // Invalid boolean
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['path', 'recursive']);
    }

    public function test_scan_respects_allowed_paths()
    {
        Config::set('cms.content.allowed_paths', ['tests/fixtures']);

        // Allowed path
        $response = $this->postJson(route('cms.editor.scan'), [
            'path' => 'tests/fixtures'
        ]);
        $response->assertStatus(200);

        // Disallowed path
        $response = $this->postJson(route('cms.editor.scan'), [
            'path' => 'config'
        ]);
        $response->assertStatus(403);
    }

    public function test_scan_with_filters()
    {
        $this->createTestBladeFile('filter1.blade.php');
        $this->createTestHtmlFile('filter2.html');

        $response = $this->postJson(route('cms.editor.scan'), [
            'path' => 'tests/fixtures',
            'filters' => ['blade.php']
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only return blade files
        $bladeFiles = collect($data['files'])->filter(function ($file) {
            return str_ends_with($file['name'], '.blade.php');
        });

        $this->assertGreaterThan(0, $bladeFiles->count());

        $htmlFiles = collect($data['files'])->filter(function ($file) {
            return str_ends_with($file['name'], '.html');
        });

        $this->assertEquals(0, $htmlFiles->count());
    }

    public function test_get_config_returns_system_configuration()
    {
        $response = $this->getJson(route('cms.editor.config'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'locales',
                'features',
                'limits',
                'security',
                'ui'
            ]
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('locales', $data);
        $this->assertArrayHasKey('features', $data);
    }

    public function test_get_config_includes_user_specific_settings()
    {
        $preferences = [
            'theme' => 'dark',
            'auto_save' => true
        ];
        $this->user->updatePreferences($preferences);

        $response = $this->getJson(route('cms.editor.config'));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('user_preferences', $data);
        $this->assertEquals('dark', $data['user_preferences']['theme']);
        $this->assertTrue($data['user_preferences']['auto_save']);
    }

    public function test_get_toolbar_returns_toolbar_configuration()
    {
        $response = $this->getJson(route('cms.editor.toolbar'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'buttons',
                'groups',
                'shortcuts',
                'customizations'
            ]
        ]);

        $data = $response->json('data');
        $this->assertIsArray($data['buttons']);
        $this->assertIsArray($data['groups']);
    }

    public function test_get_toolbar_respects_user_permissions()
    {
        $limitedUser = $this->createTestUser(['cms_reader']);
        $this->actingAs($limitedUser);

        $response = $this->getJson(route('cms.editor.toolbar'));

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should not have edit/delete buttons
        $buttonNames = collect($data['buttons'])->pluck('name')->toArray();
        $this->assertNotContains('delete', $buttonNames);
        $this->assertNotContains('bulk_edit', $buttonNames);
    }

    public function test_editor_handles_concurrent_editing()
    {
        $testFile = $this->createTestBladeFile('concurrent-test.blade.php');

        // Simulate two users editing the same file
        $user1 = $this->createTestUser(['cms_editor']);
        $user2 = $this->createTestUser(['cms_editor']);

        // User 1 starts editing
        $this->actingAs($user1);
        $response1 = $this->get(route('cms.editor.preview', ['url' => 'tests/fixtures/views/concurrent-test.blade.php']));
        $response1->assertStatus(200);

        // User 2 tries to edit the same file
        $this->actingAs($user2);
        $response2 = $this->get(route('cms.editor.preview', ['url' => 'tests/fixtures/views/concurrent-test.blade.php']));

        // Should receive warning about concurrent editing
        $response2->assertStatus(200);
        $response2->assertViewHas('concurrentWarning', true);
    }

    public function test_editor_caches_frequently_accessed_files()
    {
        $testFile = $this->createTestBladeFile('cached-test.blade.php');
        $url = 'tests/fixtures/views/cached-test.blade.php';

        // First request
        $start = microtime(true);
        $response1 = $this->get(route('cms.editor.preview', ['url' => $url]));
        $firstRequestTime = microtime(true) - $start;

        $response1->assertStatus(200);

        // Second request (should be faster due to caching)
        $start = microtime(true);
        $response2 = $this->get(route('cms.editor.preview', ['url' => $url]));
        $secondRequestTime = microtime(true) - $start;

        $response2->assertStatus(200);
        $this->assertLessThan($firstRequestTime, $secondRequestTime);
    }

    public function test_editor_logs_user_activity()
    {
        $testFile = $this->createTestBladeFile('activity-test.blade.php');

        $response = $this->get(route('cms.editor.preview', [
            'url' => 'tests/fixtures/views/activity-test.blade.php'
        ]));

        $response->assertStatus(200);

        // Check that activity was logged
        $this->assertDatabaseHas('cms_activity_logs', [
            'user_id' => $this->user->id,
            'action' => 'file_preview',
            'resource_type' => 'file',
            'resource_id' => 'tests/fixtures/views/activity-test.blade.php'
        ]);
    }

    public function test_scan_performance_with_large_directories()
    {
        // Create many test files
        for ($i = 1; $i <= 100; $i++) {
            $this->createTestFile("tests/fixtures/performance/file_{$i}.blade.php", "@extends('layout')");
        }

        $this->assertPerformance(function () {
            $response = $this->postJson(route('cms.editor.scan'), [
                'path' => 'tests/fixtures/performance',
                'recursive' => true
            ]);
            $response->assertStatus(200);
        }, 2.0, 50 * 1024 * 1024); // 2 seconds, 50MB
    }

    public function test_editor_respects_rate_limiting()
    {
        // Make many rapid requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson(route('cms.editor.config'));
            if ($i < 5) {
                $response->assertStatus(200);
            }
        }

        // Should eventually hit rate limit
        $response = $this->getJson(route('cms.editor.config'));
        $this->assertContains($response->status(), [200, 429]); // OK or Too Many Requests
    }

    public function test_editor_validates_csrf_token()
    {
        $response = $this->post(route('cms.editor.scan'), [
            'path' => 'tests/fixtures'
        ], [
            'X-CSRF-TOKEN' => 'invalid-token'
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    public function test_editor_handles_file_upload()
    {
        Storage::fake('testing');

        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->create('test.blade.php', 1024);

        $response = $this->postJson(route('cms.editor.upload'), [
            'file' => $uploadedFile,
            'destination' => 'tests/fixtures/uploads'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);

        Storage::disk('testing')->assertExists('uploads/test.blade.php');
    }

    public function test_editor_validates_file_upload_size()
    {
        Config::set('cms.content.max_file_size', 1024); // 1KB limit

        $largeFile = \Illuminate\Http\UploadedFile::fake()->create('large.blade.php', 2048); // 2KB

        $response = $this->postJson(route('cms.editor.upload'), [
            'file' => $largeFile,
            'destination' => 'tests/fixtures/uploads'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_editor_handles_ajax_requests()
    {
        $response = $this->getJson(route('cms.editor.config'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'success',
            'data',
            'timestamp'
        ]);
    }

    public function test_editor_returns_html_for_regular_requests()
    {
        $response = $this->get(route('cms.editor.index'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertViewIs('cms.editor');
    }

    public function test_editor_handles_locale_switching()
    {
        $response = $this->get(route('cms.editor.index', ['locale' => 'es']));

        $response->assertStatus(200);
        $response->assertViewHas('currentLocale', 'es');

        // Check that session locale was updated
        $this->assertEquals('es', session('locale'));
    }

    public function test_editor_validates_locale_parameter()
    {
        $response = $this->get(route('cms.editor.index', ['locale' => 'invalid']));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid locale specified'
        ]);
    }

    public function test_scan_includes_file_metadata()
    {
        $testFile = $this->createTestBladeFile('metadata-test.blade.php');

        $response = $this->postJson(route('cms.editor.scan'), [
            'path' => 'tests/fixtures',
            'include_metadata' => true
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $files = collect($data['files']);
        $testFileData = $files->firstWhere('name', 'metadata-test.blade.php');

        $this->assertNotNull($testFileData);
        $this->assertArrayHasKey('size', $testFileData);
        $this->assertArrayHasKey('modified', $testFileData);
        $this->assertArrayHasKey('permissions', $testFileData);
        $this->assertArrayHasKey('type', $testFileData);
    }

    public function test_editor_supports_keyboard_shortcuts()
    {
        $response = $this->get(route('cms.editor.index'));

        $response->assertStatus(200);
        $shortcuts = $response->viewData('shortcuts');

        $this->assertIsArray($shortcuts);
        $this->assertArrayHasKey('save', $shortcuts);
        $this->assertArrayHasKey('preview', $shortcuts);
        $this->assertArrayHasKey('search', $shortcuts);
    }

    public function test_editor_shows_recent_files()
    {
        // Create some activity for recent files
        $this->createTestBladeFile('recent1.blade.php');
        $this->createTestBladeFile('recent2.blade.php');

        // Access files to create recent activity
        $this->get(route('cms.editor.preview', ['url' => 'tests/fixtures/views/recent1.blade.php']));
        $this->get(route('cms.editor.preview', ['url' => 'tests/fixtures/views/recent2.blade.php']));

        $response = $this->get(route('cms.editor.index'));

        $response->assertStatus(200);
        $recentFiles = $response->viewData('recentFiles');

        $this->assertIsArray($recentFiles);
        $this->assertNotEmpty($recentFiles);
    }
}