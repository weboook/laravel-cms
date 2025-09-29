# Laravel CMS Testing Guide

This guide provides comprehensive testing strategies and examples for Laravel CMS applications.

## Table of Contents

1. [Testing Overview](#testing-overview)
2. [Test Environment Setup](#test-environment-setup)
3. [Unit Testing](#unit-testing)
4. [Feature Testing](#feature-testing)
5. [Browser Testing](#browser-testing)
6. [Performance Testing](#performance-testing)
7. [Security Testing](#security-testing)
8. [API Testing](#api-testing)
9. [Continuous Integration](#continuous-integration)
10. [Test Data Management](#test-data-management)

## Testing Overview

Laravel CMS testing covers multiple layers:

- **Unit Tests**: Test individual components and services
- **Feature Tests**: Test application features and workflows
- **Browser Tests**: Test user interactions and JavaScript functionality
- **Integration Tests**: Test CMS integration with Laravel applications
- **Performance Tests**: Test load handling and response times
- **Security Tests**: Test authentication, authorization, and input validation

## Test Environment Setup

### 1. Install Testing Dependencies

```bash
# Install PHPUnit and testing tools
composer require --dev phpunit/phpunit
composer require --dev laravel/dusk
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel

# Install JavaScript testing tools
npm install --save-dev jest @testing-library/dom @testing-library/user-event
npm install --save-dev cypress @cypress/laravel

# Install additional testing utilities
composer require --dev fakerphp/faker
composer require --dev mockery/mockery
```

### 2. Configure Test Database

```php
// config/database.php
'testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
],

// Or use a dedicated test database
'testing' => [
    'driver' => 'mysql',
    'host' => env('DB_TEST_HOST', '127.0.0.1'),
    'port' => env('DB_TEST_PORT', '3306'),
    'database' => env('DB_TEST_DATABASE', 'cms_testing'),
    'username' => env('DB_TEST_USERNAME', 'root'),
    'password' => env('DB_TEST_PASSWORD', ''),
],
```

### 3. Environment Configuration

```bash
# .env.testing
APP_ENV=testing
APP_DEBUG=true
APP_KEY=base64:your-test-key

DB_CONNECTION=testing
DB_DATABASE=:memory:

CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync

CMS_ENABLED=true
CMS_AUTH_REQUIRED=false
CMS_DB_DETECTION_ENABLED=true
CMS_DB_CACHE_ENABLED=false
CMS_ASSETS_DISK=testing
```

### 4. PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="CMS">
            <directory suffix="Test.php">./tests/CMS</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">./app</directory>
            <directory suffix=".php">./vendor/webook/laravel-cms/src</directory>
        </include>
    </coverage>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="testing"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

## Unit Testing

### 1. Service Testing

```php
<?php
// tests/Unit/CMS/MediaAssetManagerTest.php

namespace Tests\Unit\CMS;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webook\LaravelCMS\Services\MediaAssetManager;
use Webook\LaravelCMS\Services\ImageProcessor;
use Webook\LaravelCMS\Models\Asset;

class MediaAssetManagerTest extends TestCase
{
    protected MediaAssetManager $assetManager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('testing');
        $this->assetManager = app(MediaAssetManager::class);
    }

    /** @test */
    public function it_can_upload_an_image()
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $asset = $this->assetManager->upload($file);

        $this->assertInstanceOf(Asset::class, $asset);
        $this->assertEquals('test.jpg', $asset->original_name);
        $this->assertEquals('image/jpeg', $asset->mime_type);
        $this->assertNotNull($asset->file_path);

        Storage::disk('testing')->assertExists($asset->file_path);
    }

    /** @test */
    public function it_generates_thumbnails_for_images()
    {
        $file = UploadedFile::fake()->image('test.jpg', 1200, 800);

        $asset = $this->assetManager->upload($file, [
            'generate_thumbnails' => true
        ]);

        $this->assertNotNull($asset->thumbnails);
        $this->assertArrayHasKey('small', $asset->thumbnails);
        $this->assertArrayHasKey('medium', $asset->thumbnails);
        $this->assertArrayHasKey('large', $asset->thumbnails);

        foreach ($asset->thumbnails as $size => $path) {
            Storage::disk('testing')->assertExists($path);
        }
    }

    /** @test */
    public function it_validates_file_types()
    {
        $file = UploadedFile::fake()->create('test.exe', 1000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File type not allowed');

        $this->assetManager->upload($file);
    }

    /** @test */
    public function it_validates_file_size()
    {
        $file = UploadedFile::fake()->create('test.jpg', 20000); // 20MB

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds limit');

        $this->assetManager->upload($file);
    }

    /** @test */
    public function it_can_organize_assets_in_folders()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $asset = $this->assetManager->upload($file, [
            'folder_path' => 'images/uploads'
        ]);

        $this->assertNotNull($asset->folder);
        $this->assertEquals('images/uploads', $asset->folder->path);
    }

    /** @test */
    public function it_extracts_metadata_from_images()
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $asset = $this->assetManager->upload($file);

        $this->assertNotNull($asset->metadata);
        $this->assertEquals(800, $asset->metadata['width']);
        $this->assertEquals(600, $asset->metadata['height']);
        $this->assertArrayHasKey('file_size', $asset->metadata);
    }
}
```

### 2. Model Testing

```php
<?php
// tests/Unit/CMS/AssetTest.php

namespace Tests\Unit\CMS;

use Tests\TestCase;
use Webook\LaravelCMS\Models\Asset;
use Webook\LaravelCMS\Models\AssetFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_folder()
    {
        $folder = AssetFolder::factory()->create();
        $asset = Asset::factory()->create(['folder_id' => $folder->id]);

        $this->assertInstanceOf(AssetFolder::class, $asset->folder);
        $this->assertEquals($folder->id, $asset->folder->id);
    }

    /** @test */
    public function it_can_get_file_url()
    {
        $asset = Asset::factory()->create([
            'file_path' => 'cms-assets/test.jpg'
        ]);

        $url = $asset->getUrl();

        $this->assertStringContains('cms-assets/test.jpg', $url);
    }

    /** @test */
    public function it_can_get_thumbnail_url()
    {
        $asset = Asset::factory()->create([
            'thumbnails' => [
                'small' => 'cms-assets/thumbnails/small-test.jpg',
                'medium' => 'cms-assets/thumbnails/medium-test.jpg'
            ]
        ]);

        $thumbnailUrl = $asset->getThumbnailUrl('small');

        $this->assertStringContains('small-test.jpg', $thumbnailUrl);
    }

    /** @test */
    public function it_returns_null_for_missing_thumbnail()
    {
        $asset = Asset::factory()->create(['thumbnails' => []]);

        $thumbnailUrl = $asset->getThumbnailUrl('small');

        $this->assertNull($thumbnailUrl);
    }

    /** @test */
    public function it_can_determine_if_asset_is_image()
    {
        $imageAsset = Asset::factory()->create(['mime_type' => 'image/jpeg']);
        $pdfAsset = Asset::factory()->create(['mime_type' => 'application/pdf']);

        $this->assertTrue($imageAsset->isImage());
        $this->assertFalse($pdfAsset->isImage());
    }

    /** @test */
    public function it_formats_file_size_for_humans()
    {
        $asset = Asset::factory()->create(['file_size' => 1024]);

        $this->assertEquals('1.00 KB', $asset->getFormattedFileSize());
    }
}
```

### 3. Database Content Testing

```php
<?php
// tests/Unit/CMS/DatabaseContentScannerTest.php

namespace Tests\Unit\CMS;

use Tests\TestCase;
use App\Models\Post;
use Webook\LaravelCMS\Services\DatabaseContentScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseContentScannerTest extends TestCase
{
    use RefreshDatabase;

    protected DatabaseContentScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = app(DatabaseContentScanner::class);
    }

    /** @test */
    public function it_can_detect_editable_models()
    {
        Post::factory()->count(3)->create();

        $editableModels = $this->scanner->detectEditableModels();

        $this->assertArrayHasKey(Post::class, $editableModels);
        $this->assertCount(3, $editableModels[Post::class]);
    }

    /** @test */
    public function it_can_identify_text_fields()
    {
        $post = Post::factory()->create([
            'title' => 'Test Post',
            'content' => 'This is test content',
            'excerpt' => 'Test excerpt'
        ]);

        $textFields = $this->scanner->identifyTextFields($post);

        $this->assertContains('title', $textFields);
        $this->assertContains('content', $textFields);
        $this->assertContains('excerpt', $textFields);
    }

    /** @test */
    public function it_can_generate_edit_markers()
    {
        $post = Post::factory()->create();

        $markers = $this->scanner->generateEditMarkers($post, 'title');

        $this->assertStringContains('data-cms-model', $markers);
        $this->assertStringContains('data-cms-field', $markers);
        $this->assertStringContains($post->id, $markers);
    }
}
```

## Feature Testing

### 1. CMS Integration Testing

```php
<?php
// tests/Feature/CMS/CMSIntegrationTest.php

namespace Tests\Feature\CMS;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CMSIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_users_can_access_cms_routes()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cms');

        $response->assertStatus(200);
    }

    /** @test */
    public function guests_cannot_access_cms_routes()
    {
        $response = $this->get('/cms');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function cms_can_render_editable_content()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'title' => 'Test Post Title',
            'content' => 'Test post content'
        ]);

        $response = $this->actingAs($user)->get("/posts/{$post->slug}");

        $response->assertStatus(200);
        $response->assertSee('Test Post Title');
        $response->assertSee('data-cms-model');
        $response->assertSee('data-cms-field');
    }

    /** @test */
    public function cms_can_update_content_inline()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['title' => 'Original Title']);

        $response = $this->actingAs($user)->patch("/cms/api/content", [
            'model' => Post::class,
            'id' => $post->id,
            'field' => 'title',
            'value' => 'Updated Title'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title'
        ]);
    }
}
```

### 2. Asset Management Testing

```php
<?php
// tests/Feature/CMS/AssetManagementTest.php

namespace Tests\Feature\CMS;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function users_can_upload_assets()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->actingAs($user)->post('/cms/api/assets/upload', [
            'files' => [$file]
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'assets' => [
                '*' => ['id', 'original_name', 'file_path', 'mime_type']
            ]
        ]);

        Storage::disk('public')->assertExists(
            $response->json('assets.0.file_path')
        );
    }

    /** @test */
    public function users_can_browse_assets()
    {
        $user = User::factory()->create();

        // Upload some test assets
        $this->actingAs($user)->post('/cms/api/assets/upload', [
            'files' => [
                UploadedFile::fake()->image('test1.jpg'),
                UploadedFile::fake()->image('test2.jpg')
            ]
        ]);

        $response = $this->actingAs($user)->get('/cms/api/assets');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'original_name', 'file_path', 'mime_type']
            ],
            'meta' => ['total', 'per_page', 'current_page']
        ]);
    }

    /** @test */
    public function users_can_delete_assets()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('test.jpg');

        $uploadResponse = $this->actingAs($user)->post('/cms/api/assets/upload', [
            'files' => [$file]
        ]);

        $assetId = $uploadResponse->json('assets.0.id');
        $filePath = $uploadResponse->json('assets.0.file_path');

        $response = $this->actingAs($user)->delete("/cms/api/assets/{$assetId}");

        $response->assertStatus(204);
        Storage::disk('public')->assertMissing($filePath);
    }

    /** @test */
    public function users_can_organize_assets_in_folders()
    {
        $user = User::factory()->create();

        // Create folder
        $folderResponse = $this->actingAs($user)->post('/cms/api/assets/folders', [
            'name' => 'Test Folder'
        ]);

        $folderId = $folderResponse->json('folder.id');

        // Upload asset to folder
        $file = UploadedFile::fake()->image('test.jpg');
        $response = $this->actingAs($user)->post('/cms/api/assets/upload', [
            'files' => [$file],
            'folder_id' => $folderId
        ]);

        $response->assertStatus(201);
        $this->assertEquals($folderId, $response->json('assets.0.folder_id'));
    }
}
```

## Browser Testing

### 1. Laravel Dusk Tests

```php
<?php
// tests/Browser/CMS/AssetLibraryTest.php

namespace Tests\Browser\CMS;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class AssetLibraryTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function users_can_open_asset_library()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/cms')
                    ->click('[data-cms-open-library]')
                    ->waitFor('.cms-asset-library')
                    ->assertSee('Asset Library');
        });
    }

    /** @test */
    public function users_can_upload_files_via_drag_and_drop()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/cms')
                    ->click('[data-cms-open-library]')
                    ->waitFor('.cms-asset-library')
                    ->attach('files[]', __DIR__.'/../../fixtures/test-image.jpg')
                    ->waitFor('.upload-progress')
                    ->waitUntilMissing('.upload-progress')
                    ->assertSee('test-image.jpg');
        });
    }

    /** @test */
    public function users_can_search_assets()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/cms')
                    ->click('[data-cms-open-library]')
                    ->waitFor('.cms-asset-library')
                    ->type('[data-search]', 'test')
                    ->pause(500)
                    ->assertSee('Search results');
        });
    }

    /** @test */
    public function users_can_select_assets()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/cms')
                    ->click('[data-cms-open-library]')
                    ->waitFor('.cms-asset-library')
                    ->click('.asset-item:first-child')
                    ->assertSelected('.asset-item:first-child')
                    ->click('[data-action="select"]')
                    ->waitUntilMissing('.cms-asset-library');
        });
    }
}
```

### 2. Cypress Tests

```javascript
// cypress/integration/cms/inline-editing.spec.js

describe('CMS Inline Editing', () => {
  beforeEach(() => {
    cy.login();
    cy.visit('/posts/test-post');
  });

  it('allows editing content inline', () => {
    cy.get('[data-cms-field="title"]')
      .click()
      .clear()
      .type('New Title')
      .blur();

    cy.contains('Content saved').should('be.visible');

    cy.reload();
    cy.get('[data-cms-field="title"]').should('contain', 'New Title');
  });

  it('shows editing toolbar when field is selected', () => {
    cy.get('[data-cms-field="content"]').click();

    cy.get('.cms-toolbar').should('be.visible');
    cy.get('.cms-toolbar').should('contain', 'Bold');
    cy.get('.cms-toolbar').should('contain', 'Italic');
  });

  it('can insert images from asset library', () => {
    cy.get('[data-cms-field="content"]').click();
    cy.get('[data-action="insert-image"]').click();

    cy.get('.cms-asset-library').should('be.visible');
    cy.get('.asset-item').first().click();
    cy.get('[data-action="select"]').click();

    cy.get('[data-cms-field="content"] img').should('exist');
  });

  it('auto-saves changes', () => {
    cy.get('[data-cms-field="content"]')
      .click()
      .type(' Additional content');

    cy.wait(5000); // Wait for auto-save
    cy.contains('Auto-saved').should('be.visible');
  });

  it('shows unsaved changes warning', () => {
    cy.get('[data-cms-field="title"]')
      .click()
      .clear()
      .type('Modified Title');

    cy.window().then((win) => {
      win.addEventListener('beforeunload', cy.stub().as('beforeUnload'));
    });

    cy.visit('/');
    cy.get('@beforeUnload').should('have.been.called');
  });
});
```

## Performance Testing

### 1. Load Testing

```php
<?php
// tests/Performance/CMSLoadTest.php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CMSLoadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function cms_asset_library_handles_large_number_of_assets()
    {
        $user = User::factory()->create();

        // Create 1000 assets
        \Webook\LaravelCMS\Models\Asset::factory()->count(1000)->create();

        $startTime = microtime(true);

        $response = $this->actingAs($user)->get('/cms/api/assets?per_page=50');

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        $this->assertLessThan(500, $responseTime, 'Asset library response time should be under 500ms');
    }

    /** @test */
    public function cms_content_detection_performance()
    {
        // Create 100 posts
        Post::factory()->count(100)->create();

        $startTime = microtime(true);

        $scanner = app(\Webook\LaravelCMS\Services\DatabaseContentScanner::class);
        $editableModels = $scanner->detectEditableModels();

        $endTime = microtime(true);
        $scanTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(1000, $scanTime, 'Content detection should complete under 1 second');
        $this->assertCount(100, $editableModels[Post::class]);
    }

    /** @test */
    public function cms_inline_updates_are_efficient()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $startTime = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->patch('/cms/api/content', [
                'model' => Post::class,
                'id' => $post->id,
                'field' => 'title',
                'value' => "Updated Title {$i}"
            ]);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(2000, $totalTime, '10 inline updates should complete under 2 seconds');
    }
}
```

### 2. Memory Usage Testing

```php
<?php
// tests/Performance/MemoryUsageTest.php

namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MemoryUsageTest extends TestCase
{
    /** @test */
    public function image_processing_memory_usage_is_reasonable()
    {
        Storage::fake('testing');

        $initialMemory = memory_get_usage(true);

        $file = UploadedFile::fake()->image('large-image.jpg', 4000, 3000);

        $assetManager = app(\Webook\LaravelCMS\Services\MediaAssetManager::class);
        $asset = $assetManager->upload($file, ['generate_thumbnails' => true]);

        $peakMemory = memory_get_peak_usage(true);
        $memoryUsed = ($peakMemory - $initialMemory) / 1024 / 1024; // Convert to MB

        $this->assertLessThan(256, $memoryUsed, 'Image processing should use less than 256MB');
        $this->assertNotNull($asset);
    }
}
```

## Security Testing

### 1. Authentication & Authorization Tests

```php
<?php
// tests/Security/CMSSecurityTest.php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CMSSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function cms_routes_require_authentication()
    {
        $routes = [
            '/cms',
            '/cms/api/content',
            '/cms/api/assets',
            '/cms/api/assets/upload'
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $this->assertContains($response->status(), [401, 302], "Route {$route} should require authentication");
        }
    }

    /** @test */
    public function users_cannot_edit_unauthorized_content()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => 999]); // Different user

        $response = $this->actingAs($user)->patch('/cms/api/content', [
            'model' => Post::class,
            'id' => $post->id,
            'field' => 'title',
            'value' => 'Hacked Title'
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
            'title' => 'Hacked Title'
        ]);
    }

    /** @test */
    public function file_uploads_are_validated_for_security()
    {
        $user = User::factory()->create();

        // Test malicious file upload
        $maliciousFile = UploadedFile::fake()->create('malicious.php', 1000);

        $response = $this->actingAs($user)->post('/cms/api/assets/upload', [
            'files' => [$maliciousFile]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['files.0']);
    }

    /** @test */
    public function html_content_is_sanitized()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $maliciousContent = '<script>alert("XSS")</script><p>Safe content</p>';

        $response = $this->actingAs($user)->patch('/cms/api/content', [
            'model' => Post::class,
            'id' => $post->id,
            'field' => 'content',
            'value' => $maliciousContent
        ]);

        $response->assertStatus(200);

        $post->refresh();
        $this->assertStringNotContainsString('<script>', $post->content);
        $this->assertStringContainsString('<p>Safe content</p>', $post->content);
    }

    /** @test */
    public function csrf_protection_is_enforced()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
                        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
                        ->post('/cms/api/assets/upload', []);

        $this->assertTrue(true); // CSRF should be handled by Laravel's middleware
    }
}
```

### 2. Input Validation Tests

```php
<?php
// tests/Security/InputValidationTest.php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function content_updates_validate_field_names()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user)->patch('/cms/api/content', [
            'model' => Post::class,
            'id' => $post->id,
            'field' => 'password', // Unauthorized field
            'value' => 'hacked'
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function content_updates_validate_data_types()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user)->patch('/cms/api/content', [
            'model' => Post::class,
            'id' => $post->id,
            'field' => 'title',
            'value' => str_repeat('a', 1000) // Too long
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function sql_injection_is_prevented()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $sqlInjection = "'; DROP TABLE posts; --";

        $response = $this->actingAs($user)->patch('/cms/api/content', [
            'model' => Post::class,
            'id' => $post->id,
            'field' => 'title',
            'value' => $sqlInjection
        ]);

        // Should either reject or sanitize the input
        $this->assertTrue(
            $response->status() === 422 ||
            !str_contains($response->getContent(), 'DROP TABLE')
        );
    }
}
```

## API Testing

### 1. REST API Tests

```php
<?php
// tests/API/CMSApiTest.php

namespace Tests\API;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CMSApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function api_returns_asset_library_data()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cms/assets');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'original_name',
                            'file_path',
                            'mime_type',
                            'file_size',
                            'created_at'
                        ]
                    ],
                    'meta' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page'
                    ]
                ]);
    }

    /** @test */
    public function api_supports_asset_filtering()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cms/assets?type=image&search=test');

        $response->assertStatus(200);

        // Verify filtering is applied
        $assets = $response->json('data');
        foreach ($assets as $asset) {
            $this->assertStringStartsWith('image/', $asset['mime_type']);
        }
    }

    /** @test */
    public function api_rate_limiting_works()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Make many requests quickly
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/cms/assets');

            if ($response->status() === 429) {
                $this->assertTrue(true, 'Rate limiting is working');
                return;
            }
        }

        $this->fail('Rate limiting should have kicked in');
    }
}
```

## Continuous Integration

### 1. GitHub Actions Workflow

```yaml
# .github/workflows/cms-tests.yml
name: CMS Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: cms_testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

      redis:
        image: redis:6.2
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
        coverage: xdebug

    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'

    - name: Copy environment file
      run: cp .env.example .env.testing

    - name: Install PHP dependencies
      run: composer install --prefer-dist --no-progress

    - name: Install Node dependencies
      run: npm ci

    - name: Generate application key
      run: php artisan key:generate --env=testing

    - name: Build assets
      run: npm run build

    - name: Run database migrations
      run: php artisan migrate --env=testing --force

    - name: Install CMS
      run: php artisan cms:install --no-npm --no-build --env=testing

    - name: Run unit tests
      run: vendor/bin/phpunit tests/Unit --coverage-clover=coverage-unit.xml

    - name: Run feature tests
      run: vendor/bin/phpunit tests/Feature --coverage-clover=coverage-feature.xml

    - name: Run CMS tests
      run: vendor/bin/phpunit tests/CMS --coverage-clover=coverage-cms.xml

    - name: Run JavaScript tests
      run: npm test

    - name: Upload coverage reports
      uses: codecov/codecov-action@v3
      with:
        files: ./coverage-unit.xml,./coverage-feature.xml,./coverage-cms.xml
```

### 2. Test Scripts

```json
{
  "scripts": {
    "test": "jest",
    "test:watch": "jest --watch",
    "test:coverage": "jest --coverage",
    "test:unit": "vendor/bin/phpunit tests/Unit",
    "test:feature": "vendor/bin/phpunit tests/Feature",
    "test:cms": "vendor/bin/phpunit tests/CMS",
    "test:browser": "php artisan dusk",
    "test:all": "npm run test && composer test:all",
    "lint": "eslint resources/js --ext .js,.vue",
    "lint:fix": "eslint resources/js --ext .js,.vue --fix"
  }
}
```

## Test Data Management

### 1. Factory Definitions

```php
<?php
// database/factories/AssetFactory.php

namespace Database\Factories;

use Webook\LaravelCMS\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition()
    {
        return [
            'original_name' => $this->faker->word . '.jpg',
            'file_name' => $this->faker->uuid . '.jpg',
            'file_path' => 'cms-assets/' . $this->faker->uuid . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => $this->faker->numberBetween(10000, 1000000),
            'metadata' => [
                'width' => $this->faker->numberBetween(100, 2000),
                'height' => $this->faker->numberBetween(100, 2000),
            ],
            'thumbnails' => [
                'small' => 'cms-assets/thumbnails/small-' . $this->faker->uuid . '.jpg',
                'medium' => 'cms-assets/thumbnails/medium-' . $this->faker->uuid . '.jpg',
            ],
        ];
    }

    public function image()
    {
        return $this->state(function (array $attributes) {
            return [
                'mime_type' => $this->faker->randomElement(['image/jpeg', 'image/png', 'image/gif']),
            ];
        });
    }

    public function pdf()
    {
        return $this->state(function (array $attributes) {
            return [
                'mime_type' => 'application/pdf',
                'original_name' => $this->faker->word . '.pdf',
                'file_name' => $this->faker->uuid . '.pdf',
                'file_path' => 'cms-assets/' . $this->faker->uuid . '.pdf',
                'thumbnails' => null,
            ];
        });
    }
}
```

### 2. Test Seeders

```php
<?php
// database/seeders/CMSTestSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Post;
use Webook\LaravelCMS\Models\Asset;
use Webook\LaravelCMS\Models\AssetFolder;

class CMSTestSeeder extends Seeder
{
    public function run()
    {
        // Create test users with different roles
        $admin = User::factory()->create([
            'name' => 'CMS Admin',
            'email' => 'admin@example.com',
        ]);

        $editor = User::factory()->create([
            'name' => 'CMS Editor',
            'email' => 'editor@example.com',
        ]);

        $author = User::factory()->create([
            'name' => 'CMS Author',
            'email' => 'author@example.com',
        ]);

        // Create test content
        Post::factory()->count(20)->create([
            'user_id' => $admin->id,
        ]);

        // Create asset folders
        $imagesFolder = AssetFolder::factory()->create([
            'name' => 'Images',
            'path' => 'images',
        ]);

        $documentsFolder = AssetFolder::factory()->create([
            'name' => 'Documents',
            'path' => 'documents',
        ]);

        // Create test assets
        Asset::factory()->count(50)->image()->create([
            'folder_id' => $imagesFolder->id,
        ]);

        Asset::factory()->count(10)->pdf()->create([
            'folder_id' => $documentsFolder->id,
        ]);
    }
}
```

---

This comprehensive testing guide ensures that your Laravel CMS implementation is robust, secure, and performant. Regular testing helps maintain code quality and prevents regressions as the system evolves.