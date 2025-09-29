<?php

namespace Webook\LaravelCMS\Tests\Helpers;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Webook\LaravelCMS\Models\TextContent;
use Webook\LaravelCMS\Models\Translation;
use Webook\LaravelCMS\Models\Image;
use Webook\LaravelCMS\Models\Link;
use Webook\LaravelCMS\Models\ContentHistory;

/**
 * Test Helper Trait
 *
 * Provides common helper methods for creating test data and assertions.
 */
trait TestHelpers
{
    /**
     * Create a test user with optional permissions.
     */
    protected function createTestUser(array $permissions = []): User
    {
        $user = User::create([
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // If using Spatie Permission package
        if (class_exists('\Spatie\Permission\Models\Permission')) {
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission);
            }
        }

        return $user;
    }

    /**
     * Create test content with various types.
     */
    protected function setupTestContent(): array
    {
        $user = $this->createTestUser();

        return [
            'text_content' => $this->createTestTextContent($user),
            'translations' => $this->createTestTranslations($user),
            'images' => $this->createTestImages($user),
            'links' => $this->createTestLinks($user),
        ];
    }

    /**
     * Create test text content.
     */
    protected function createTestTextContent(User $user = null): TextContent
    {
        if (!$user) {
            $user = $this->createTestUser();
        }

        return TextContent::create([
            'key' => 'test.content.key',
            'value' => 'This is test content with <strong>HTML</strong> tags.',
            'locale' => 'en',
            'metadata' => [
                'type' => 'text',
                'context' => 'testing',
                'tags' => ['test', 'content'],
            ],
            'file_path' => 'tests/fixtures/views/test.blade.php',
            'line_number' => 10,
            'selector' => '.test-content',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Create multiple test text contents.
     */
    protected function createMultipleTestContents(int $count = 5, User $user = null): array
    {
        if (!$user) {
            $user = $this->createTestUser();
        }

        $contents = [];
        for ($i = 1; $i <= $count; $i++) {
            $contents[] = TextContent::create([
                'key' => "test.content.key.{$i}",
                'value' => "Test content number {$i}",
                'locale' => 'en',
                'metadata' => ['index' => $i],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        return $contents;
    }

    /**
     * Create test translations.
     */
    protected function createTestTranslations(User $user = null): array
    {
        if (!$user) {
            $user = $this->createTestUser();
        }

        $translations = [];

        // English translations
        $translations[] = Translation::create([
            'locale' => 'en',
            'group' => 'messages',
            'key' => 'welcome',
            'value' => 'Welcome to our site',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $translations[] = Translation::create([
            'locale' => 'en',
            'group' => 'messages',
            'key' => 'about',
            'value' => 'About Us',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        // Spanish translations
        $translations[] = Translation::create([
            'locale' => 'es',
            'group' => 'messages',
            'key' => 'welcome',
            'value' => 'Bienvenido a nuestro sitio',
            'status' => 'active',
            'source_locale' => 'en',
            'created_by' => $user->id,
        ]);

        $translations[] = Translation::create([
            'locale' => 'es',
            'group' => 'messages',
            'key' => 'about',
            'value' => 'Acerca de Nosotros',
            'status' => 'active',
            'source_locale' => 'en',
            'created_by' => $user->id,
        ]);

        return $translations;
    }

    /**
     * Create test images.
     */
    protected function createTestImages(User $user = null): array
    {
        if (!$user) {
            $user = $this->createTestUser();
        }

        $images = [];

        $images[] = Image::create([
            'filename' => 'test-image-1.jpg',
            'original_name' => 'Test Image 1.jpg',
            'path' => 'images/2024/01/test-image-1.jpg',
            'url' => '/storage/images/2024/01/test-image-1.jpg',
            'size' => 1024000, // 1MB
            'mime_type' => 'image/jpeg',
            'width' => 1920,
            'height' => 1080,
            'alt_text' => 'Test image for CMS',
            'title' => 'Test Image Title',
            'description' => 'This is a test image description',
            'metadata' => [
                'camera' => 'Test Camera',
                'location' => 'Test Location',
            ],
            'uploaded_by' => $user->id,
        ]);

        $images[] = Image::create([
            'filename' => 'test-image-2.png',
            'original_name' => 'Test Image 2.png',
            'path' => 'images/2024/01/test-image-2.png',
            'url' => '/storage/images/2024/01/test-image-2.png',
            'size' => 512000, // 512KB
            'mime_type' => 'image/png',
            'width' => 800,
            'height' => 600,
            'alt_text' => 'Second test image',
            'title' => 'Test PNG Image',
            'uploaded_by' => $user->id,
        ]);

        return $images;
    }

    /**
     * Create test links.
     */
    protected function createTestLinks(User $user = null): array
    {
        if (!$user) {
            $user = $this->createTestUser();
        }

        $links = [];

        $links[] = Link::create([
            'identifier' => 'test.external.link',
            'url' => 'https://example.com',
            'text' => 'External Example Link',
            'title' => 'Visit Example.com',
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'metadata' => [
                'type' => 'external',
                'category' => 'reference',
            ],
            'is_active' => true,
            'is_valid' => true,
            'created_by' => $user->id,
        ]);

        $links[] = Link::create([
            'identifier' => 'test.internal.link',
            'url' => '/about',
            'text' => 'About Page',
            'title' => 'Learn more about us',
            'target' => '_self',
            'metadata' => [
                'type' => 'internal',
                'category' => 'navigation',
            ],
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return $links;
    }

    /**
     * Create test content history.
     */
    protected function createTestHistory(TextContent $content, User $user = null): ContentHistory
    {
        if (!$user) {
            $user = $this->createTestUser();
        }

        return ContentHistory::create([
            'content_type' => get_class($content),
            'content_id' => $content->id,
            'action' => 'updated',
            'old_data' => [
                'value' => 'Old content value',
                'metadata' => ['old' => true],
            ],
            'new_data' => [
                'value' => $content->value,
                'metadata' => $content->metadata,
            ],
            'metadata' => [
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test User Agent',
                'session_id' => 'test-session-id',
            ],
            'user_id' => $user->id,
        ]);
    }

    /**
     * Mock translation data structure.
     */
    protected function mockTranslations(): array
    {
        return [
            'en' => [
                'messages' => [
                    'welcome' => 'Welcome to our site',
                    'goodbye' => 'Thank you for visiting',
                    'navigation' => [
                        'home' => 'Home',
                        'about' => 'About',
                        'contact' => 'Contact',
                    ],
                ],
                'validation' => [
                    'required' => 'The :attribute field is required.',
                    'email' => 'Please enter a valid email address.',
                ],
            ],
            'es' => [
                'messages' => [
                    'welcome' => 'Bienvenido a nuestro sitio',
                    'goodbye' => 'Gracias por visitarnos',
                    'navigation' => [
                        'home' => 'Inicio',
                        'about' => 'Acerca de',
                        'contact' => 'Contacto',
                    ],
                ],
            ],
            'fr' => [
                'messages' => [
                    'welcome' => 'Bienvenue sur notre site',
                    'navigation' => [
                        'home' => 'Accueil',
                        'about' => 'À propos',
                    ],
                ],
            ],
        ];
    }

    /**
     * Assert that content was properly updated.
     */
    protected function assertContentUpdated(
        string $originalContent,
        string $updatedContent,
        string $expectedContent,
        string $message = 'Content was not updated correctly'
    ): void {
        $this->assertNotEquals($originalContent, $updatedContent, 'Content should have changed');
        $this->assertEquals($expectedContent, $updatedContent, $message);
    }

    /**
     * Assert that a backup was created for the given content.
     */
    protected function assertBackupExists(string $contentId, string $contentType = 'text'): void
    {
        $this->assertDatabaseHas('content_backups', [
            'content_id' => $contentId,
            'content_type' => $contentType,
        ]);
    }

    /**
     * Assert that translation exists in database.
     */
    protected function assertTranslationExists(string $locale, string $group, string $key, string $value = null): void
    {
        $conditions = compact('locale', 'group', 'key');

        if ($value !== null) {
            $conditions['value'] = $value;
        }

        $this->assertDatabaseHas('translations', $conditions);
    }

    /**
     * Assert that translation does not exist in database.
     */
    protected function assertTranslationMissing(string $locale, string $group, string $key): void
    {
        $this->assertDatabaseMissing('translations', compact('locale', 'group', 'key'));
    }

    /**
     * Assert API response has correct structure.
     */
    protected function assertApiSuccessResponse($response, array $expectedKeys = []): void
    {
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'timestamp',
        ]);

        $response->assertJson(['success' => true]);

        if (!empty($expectedKeys)) {
            $response->assertJsonStructure(['data' => $expectedKeys]);
        }
    }

    /**
     * Assert API error response.
     */
    protected function assertApiErrorResponse($response, int $expectedStatus = 400, string $expectedMessage = null): void
    {
        $response->assertStatus($expectedStatus);
        $response->assertJsonStructure([
            'success',
            'message',
            'timestamp',
        ]);

        $response->assertJson(['success' => false]);

        if ($expectedMessage) {
            $response->assertJsonFragment(['message' => $expectedMessage]);
        }
    }

    /**
     * Assert API validation error response.
     */
    protected function assertApiValidationError($response, array $expectedFields = []): void
    {
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors',
            'timestamp',
        ]);

        $response->assertJson(['success' => false]);

        if (!empty($expectedFields)) {
            $response->assertJsonValidationErrors($expectedFields);
        }
    }

    /**
     * Create test Blade content with various syntax elements.
     */
    protected function createTestBladeContent(): string
    {
        return <<<'BLADE'
@extends('layouts.master')

@section('title', 'Test Page')

@section('content')
<div class="container">
    <h1>{{ $title }}</h1>

    @if($showWelcome)
        <div class="welcome-message">
            {!! $welcomeMessage !!}
        </div>
    @endif

    @foreach($items as $item)
        <div class="item">
            <h3>{{ $item->name }}</h3>
            <p>{{ $item->description }}</p>
            @if($item->hasImage())
                <img src="{{ $item->image_url }}" alt="{{ $item->name }}">
            @endif
        </div>
    @endforeach

    @include('partials.sidebar')

    @push('scripts')
        <script>
            console.log('Page loaded');
        </script>
    @endpush
</div>
@endsection
BLADE;
    }

    /**
     * Create test HTML content with various elements.
     */
    protected function createTestHtmlContent(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test HTML Page</title>
</head>
<body>
    <header class="main-header">
        <h1 id="page-title">Welcome to Test Page</h1>
        <nav>
            <ul>
                <li><a href="/" class="nav-link">Home</a></li>
                <li><a href="/about" class="nav-link">About</a></li>
                <li><a href="/contact" class="nav-link">Contact</a></li>
            </ul>
        </nav>
    </header>

    <main class="content">
        <section class="hero">
            <h2>Hero Section</h2>
            <p class="description">This is the hero description.</p>
            <button class="cta-button">Call to Action</button>
        </section>

        <section class="features">
            <div class="feature" data-feature="1">
                <h3>Feature One</h3>
                <p>Description of feature one.</p>
            </div>
            <div class="feature" data-feature="2">
                <h3>Feature Two</h3>
                <p>Description of feature two.</p>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; 2024 Test Company</p>
    </footer>
</body>
</html>
HTML;
    }

    /**
     * Create test user with all CMS permissions.
     */
    protected function createAdminUser(): User
    {
        return $this->createTestUser([
            'access-cms-editor',
            'edit-content',
            'translate-content',
            'manage-media',
            'restore-content',
            'sync-translations',
            'view-user-details',
        ]);
    }

    /**
     * Create test user with limited permissions.
     */
    protected function createLimitedUser(): User
    {
        return $this->createTestUser([
            'access-cms-editor',
            'edit-content',
        ]);
    }

    /**
     * Create test user with no permissions.
     */
    protected function createGuestUser(): User
    {
        return $this->createTestUser([]);
    }

    /**
     * Assert performance within acceptable limits.
     */
    protected function assertPerformanceWithinLimits(callable $callback, array $limits = []): void
    {
        $defaultLimits = [
            'max_execution_time' => 1.0, // 1 second
            'max_memory_usage' => 10 * 1024 * 1024, // 10MB
            'max_database_queries' => 50,
        ];

        $limits = array_merge($defaultLimits, $limits);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Enable query logging if testing database queries
        if (isset($limits['max_database_queries'])) {
            \DB::enableQueryLog();
        }

        $result = $callback();

        $executionTime = microtime(true) - $startTime;
        $memoryUsage = memory_get_usage(true) - $startMemory;

        // Check execution time
        $this->assertLessThan(
            $limits['max_execution_time'],
            $executionTime,
            "Execution time {$executionTime}s exceeded limit of {$limits['max_execution_time']}s"
        );

        // Check memory usage
        $this->assertLessThan(
            $limits['max_memory_usage'],
            $memoryUsage,
            "Memory usage " . ($memoryUsage / 1024 / 1024) . "MB exceeded limit of " . ($limits['max_memory_usage'] / 1024 / 1024) . "MB"
        );

        // Check database queries
        if (isset($limits['max_database_queries'])) {
            $queryCount = count(\DB::getQueryLog());
            $this->assertLessThan(
                $limits['max_database_queries'],
                $queryCount,
                "Database queries {$queryCount} exceeded limit of {$limits['max_database_queries']}"
            );
            \DB::disableQueryLog();
        }

        return $result;
    }

    /**
     * Mock file system operations.
     */
    protected function mockFileSystem(): void
    {
        \Storage::fake('local');
        \Storage::fake('public');
        \Storage::fake('testing');
    }

    /**
     * Create temporary test file.
     */
    protected function createTempFile(string $content = 'test content', string $extension = 'txt'): string
    {
        $filename = uniqid('test_') . '.' . $extension;
        $path = storage_path('framework/testing/' . $filename);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Assert file was modified within time limit.
     */
    protected function assertFileModifiedRecently(string $filepath, int $maxSecondsAgo = 10): void
    {
        $this->assertFileExists($filepath);

        $modifiedTime = filemtime($filepath);
        $currentTime = time();
        $timeDifference = $currentTime - $modifiedTime;

        $this->assertLessThanOrEqual(
            $maxSecondsAgo,
            $timeDifference,
            "File was not modified recently enough. Modified {$timeDifference} seconds ago."
        );
    }
}