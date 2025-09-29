<?php

namespace Webook\LaravelCMS\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Webook\LaravelCMS\Tests\Helpers\TestHelpers;
use Webook\LaravelCMS\LaravelCMSServiceProvider;

/**
 * Base TestCase for Laravel CMS Package
 *
 * Provides common setup and utilities for all CMS tests.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase, WithFaker, TestHelpers;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestEnvironment();
        $this->setUpTestDatabase();
        $this->setUpTestStorage();
        $this->setUpTestConfig();
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelCMSServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        // Testing configuration
        $app['config']->set('app.debug', true);
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // Database configuration
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Cache configuration
        $app['config']->set('cache.default', 'array');

        // Session configuration
        $app['config']->set('session.driver', 'array');

        // Queue configuration
        $app['config']->set('queue.default', 'sync');

        // Filesystem configuration
        $app['config']->set('filesystems.default', 'testing');
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing'),
        ]);

        // CMS specific configuration
        $this->setCMSConfig($app);
    }

    /**
     * Set up CMS specific configuration.
     */
    protected function setCMSConfig($app): void
    {
        $app['config']->set('cms', [
            'locales' => [
                'en' => ['name' => 'English', 'flag' => '🇺🇸', 'rtl' => false],
                'es' => ['name' => 'Español', 'flag' => '🇪🇸', 'rtl' => false],
                'fr' => ['name' => 'Français', 'flag' => '🇫🇷', 'rtl' => false],
            ],
            'content' => [
                'max_text_length' => 65535,
                'auto_backup' => true,
                'allowed_paths' => [
                    'resources/views',
                    'resources/lang',
                    'tests/fixtures',
                ],
                'allowed_extensions' => [
                    'blade.php', 'php', 'html', 'txt', 'md', 'json',
                ],
            ],
            'images' => [
                'max_file_size' => 10240, // 10MB in KB
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'],
                'generate_thumbnails' => true,
                'prevent_duplicates' => true,
                'daily_upload_limit' => 100,
                'hourly_upload_limit' => 20,
                'user_storage_limit' => 1073741824, // 1GB
            ],
            'translations' => [
                'max_length' => 10000,
                'update_files' => false, // Disable file updates in tests
                'reserved_keys' => ['system', 'internal', 'config', 'debug'],
                'max_key_depth' => 5,
            ],
            'history' => [
                'retention_days' => 90,
                'max_restore_age_days' => 30,
                'daily_restore_limit' => 5,
            ],
            'security' => [
                'restricted_paths' => ['config/', '.env', 'vendor/'],
            ],
            'features' => [
                'ai_enabled' => false,
                'git_integration' => false,
            ],
        ]);
    }

    /**
     * Set up test environment variables.
     */
    protected function setUpTestEnvironment(): void
    {
        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=testing');
        putenv('CACHE_DRIVER=array');
        putenv('SESSION_DRIVER=array');
        putenv('QUEUE_CONNECTION=sync');
    }

    /**
     * Set up test database with migrations.
     */
    protected function setUpTestDatabase(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Run package migrations
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    /**
     * Set up test storage.
     */
    protected function setUpTestStorage(): void
    {
        Storage::fake('testing');
        Storage::fake('public');

        // Create test directories
        Storage::disk('testing')->makeDirectory('backups');
        Storage::disk('testing')->makeDirectory('uploads');
        Storage::disk('testing')->makeDirectory('translations');
    }

    /**
     * Set up test configuration.
     */
    protected function setUpTestConfig(): void
    {
        Config::set('cms.testing', true);
        Config::set('cms.debug', true);
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        // Clear caches
        Cache::flush();

        // Clear events
        Event::fake();

        // Clean up storage
        Storage::disk('testing')->deleteDirectory('');
        Storage::disk('public')->deleteDirectory('');

        parent::tearDown();
    }

    /**
     * Create test file with content.
     */
    protected function createTestFile(string $path, string $content): string
    {
        $fullPath = base_path($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, $content);

        return $fullPath;
    }

    /**
     * Assert that a file contains specific content.
     */
    protected function assertFileContains(string $path, string $content): void
    {
        $this->assertFileExists($path);
        $fileContent = file_get_contents($path);
        $this->assertStringContainsString($content, $fileContent);
    }

    /**
     * Assert that a file does not contain specific content.
     */
    protected function assertFileNotContains(string $path, string $content): void
    {
        $this->assertFileExists($path);
        $fileContent = file_get_contents($path);
        $this->assertStringNotContainsString($content, $fileContent);
    }

    /**
     * Assert API response structure.
     */
    protected function assertApiResponse(array $response, array $structure): void
    {
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('timestamp', $response);

        foreach ($structure as $key) {
            $this->assertArrayHasKey($key, $response['data']);
        }
    }

    /**
     * Assert API error response.
     */
    protected function assertApiError(array $response, string $message = null): void
    {
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertFalse($response['success']);

        if ($message) {
            $this->assertStringContainsString($message, $response['message']);
        }
    }

    /**
     * Assert content was updated correctly.
     */
    protected function assertContentUpdated(string $originalContent, string $newContent, string $expectedContent): void
    {
        $this->assertNotEquals($originalContent, $newContent);
        $this->assertEquals($expectedContent, $newContent);
    }

    /**
     * Assert backup was created.
     */
    protected function assertBackupCreated(string $filePath): void
    {
        $backupFiles = Storage::disk('testing')->files('backups');
        $this->assertNotEmpty($backupFiles);

        $found = false;
        foreach ($backupFiles as $backup) {
            if (str_contains($backup, basename($filePath))) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "No backup found for file: {$filePath}");
    }

    /**
     * Create test blade file.
     */
    protected function createTestBladeFile(string $name = 'test.blade.php'): string
    {
        $content = <<<'BLADE'
@extends('layouts.app')

@section('title', '{{ $title }}')

@section('content')
    <div class="container">
        <h1>{{ $heading }}</h1>
        <p>{{ $description }}</p>

        @if($showAlert)
            <div class="alert alert-info">
                {{ $alertMessage }}
            </div>
        @endif

        <ul>
            @foreach($items as $item)
                <li>{{ $item['name'] }} - {{ $item['price'] }}</li>
            @endforeach
        </ul>

        @include('partials.footer')
    </div>
@endsection
BLADE;

        return $this->createTestFile("tests/fixtures/views/{$name}", $content);
    }

    /**
     * Create test HTML file.
     */
    protected function createTestHtmlFile(string $name = 'test.html'): string
    {
        $content = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Page</title>
</head>
<body>
    <header>
        <h1 id="main-title">Welcome to Test Page</h1>
        <nav>
            <a href="/" class="nav-link">Home</a>
            <a href="/about" class="nav-link">About</a>
            <a href="/contact" class="nav-link">Contact</a>
        </nav>
    </header>

    <main>
        <section class="content">
            <h2>Main Content</h2>
            <p class="description">This is a test description with <strong>bold text</strong> and <em>italic text</em>.</p>

            <div class="image-container">
                <img src="/images/test.jpg" alt="Test Image" title="Test Image Title">
            </div>

            <form action="/submit" method="POST">
                <input type="text" name="name" placeholder="Your Name">
                <textarea name="message" placeholder="Your Message"></textarea>
                <button type="submit">Submit</button>
            </form>
        </section>
    </main>

    <footer>
        <p>&copy; 2024 Test Site. All rights reserved.</p>
    </footer>
</body>
</html>
HTML;

        return $this->createTestFile("tests/fixtures/html/{$name}", $content);
    }

    /**
     * Create test translation files.
     */
    protected function createTestTranslationFiles(): array
    {
        $translations = [
            'en' => [
                'messages' => [
                    'welcome' => 'Welcome to our site',
                    'about' => 'About Us',
                    'contact' => 'Contact Information',
                    'nested' => [
                        'deep' => [
                            'value' => 'Deep nested value',
                        ],
                    ],
                ],
                'validation' => [
                    'required' => 'The :attribute field is required.',
                    'email' => 'The :attribute must be a valid email address.',
                ],
            ],
            'es' => [
                'messages' => [
                    'welcome' => 'Bienvenido a nuestro sitio',
                    'about' => 'Acerca de Nosotros',
                    'contact' => 'Información de Contacto',
                    'nested' => [
                        'deep' => [
                            'value' => 'Valor anidado profundo',
                        ],
                    ],
                ],
            ],
        ];

        $files = [];
        foreach ($translations as $locale => $groups) {
            foreach ($groups as $group => $trans) {
                $content = "<?php\n\nreturn " . var_export($trans, true) . ";\n";
                $path = "tests/fixtures/lang/{$locale}/{$group}.php";
                $files[] = $this->createTestFile($path, $content);
            }
        }

        return $files;
    }

    /**
     * Create test user with specific permissions.
     */
    protected function createTestUserWithPermissions(array $permissions = []): \Illuminate\Foundation\Auth\User
    {
        return $this->createTestUser($permissions);
    }

    /**
     * Mock external HTTP requests.
     */
    protected function mockHttpRequests(): void
    {
        // Mock HTTP client responses for external API calls
        // This would be implemented based on your HTTP client setup
    }

    /**
     * Get test configuration array.
     */
    protected function getTestConfig(): array
    {
        return [
            'test_mode' => true,
            'debug' => true,
            'backup_enabled' => false,
            'git_integration' => false,
        ];
    }

    /**
     * Cleanup test files.
     */
    protected function cleanupTestFiles(): void
    {
        $testDirs = [
            base_path('tests/fixtures'),
            storage_path('framework/testing'),
        ];

        foreach ($testDirs as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
            }
        }
    }

    /**
     * Recursively delete directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Assert performance metrics.
     */
    protected function assertPerformance(callable $callback, float $maxExecutionTime = 1.0, int $maxMemoryUsage = 10485760): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $callback();

        $executionTime = microtime(true) - $startTime;
        $memoryUsage = memory_get_usage(true) - $startMemory;

        $this->assertLessThan($maxExecutionTime, $executionTime, "Execution time exceeded {$maxExecutionTime} seconds");
        $this->assertLessThan($maxMemoryUsage, $memoryUsage, "Memory usage exceeded " . ($maxMemoryUsage / 1024 / 1024) . " MB");
    }

    /**
     * Mock translation file system.
     */
    protected function mockTranslationFileSystem(): void
    {
        // Create mock translation files in memory
        $this->createTestTranslationFiles();
    }

    /**
     * Assert database has translation.
     */
    protected function assertDatabaseHasTranslation(string $locale, string $group, string $key, string $value): void
    {
        $this->assertDatabaseHas('translations', [
            'locale' => $locale,
            'group' => $group,
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * Assert database missing translation.
     */
    protected function assertDatabaseMissingTranslation(string $locale, string $group, string $key): void
    {
        $this->assertDatabaseMissing('translations', [
            'locale' => $locale,
            'group' => $group,
            'key' => $key,
        ]);
    }
}