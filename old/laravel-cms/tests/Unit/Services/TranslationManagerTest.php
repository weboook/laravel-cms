<?php

namespace Webook\LaravelCMS\Tests\Unit\Services;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use PHPUnit\Framework\TestCase;
use Webook\LaravelCMS\Services\TranslationManager;

/**
 * Translation Manager Unit Tests
 *
 * Comprehensive test suite for the TranslationManager service covering:
 * - File format support (JSON, PHP arrays)
 * - Core CRUD operations
 * - Advanced features (backup, restore, git integration)
 * - Caching and performance optimization
 * - Security validation and safety features
 */
class TranslationManagerTest extends TestCase
{
    protected TranslationManager $manager;
    protected Filesystem $files;
    protected CacheRepository $cache;
    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = Mockery::mock(Filesystem::class);
        $this->cache = Mockery::mock(CacheRepository::class);

        $this->config = [
            'default_locale' => 'en',
            'supported_locales' => ['en', 'es', 'fr'],
            'translations_path' => '/tmp/translations',
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
                'prefix' => 'translations',
            ],
            'backup' => [
                'enabled' => true,
                'path' => '/tmp/backups',
                'auto_backup' => false,
            ],
            'git' => [
                'enabled' => false,
                'auto_commit' => false,
            ],
            'format' => [
                'default' => 'json',
                'php_array_style' => 'short',
            ],
            'validation' => [
                'strict_keys' => true,
                'allow_html' => false,
                'max_length' => 1000,
            ],
        ];

        $this->files->shouldReceive('exists')->with('/tmp/translations')->andReturn(true);

        $this->manager = new TranslationManager($this->files, $this->cache, $this->config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test basic translation retrieval.
     */
    public function testGetTranslation(): void
    {
        $translations = ['welcome' => 'Welcome', 'nav' => ['home' => 'Home']];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        $result = $this->manager->get('welcome');
        $this->assertEquals('Welcome', $result);

        $result = $this->manager->get('nav.home');
        $this->assertEquals('Home', $result);
    }

    /**
     * Test translation with replacement parameters.
     */
    public function testGetTranslationWithReplacements(): void
    {
        $translations = ['greeting' => 'Hello :name!'];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        $result = $this->manager->get('greeting', null, ['name' => 'John']);
        $this->assertEquals('Hello John!', $result);
    }

    /**
     * Test setting a translation.
     */
    public function testSetTranslation(): void
    {
        $translations = ['welcome' => 'Welcome'];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        // Mock file operations for saving
        $this->files->shouldReceive('exists')->with('/tmp/translations')->andReturn(true);
        $this->files->shouldReceive('put')->once()->andReturn(true);
        $this->files->shouldReceive('move')->once()->andReturn(true);

        // Mock cache operations
        $this->cache->shouldReceive('forget')->once();

        $success = $this->manager->set('new_key', 'New Value', 'en');
        $this->assertTrue($success);
    }

    /**
     * Test checking if translation exists.
     */
    public function testHasTranslation(): void
    {
        $translations = ['welcome' => 'Welcome', 'nav' => ['home' => 'Home']];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        $this->assertTrue($this->manager->has('welcome'));
        $this->assertTrue($this->manager->has('nav.home'));
        $this->assertFalse($this->manager->has('nonexistent'));
    }

    /**
     * Test removing a translation.
     */
    public function testForgetTranslation(): void
    {
        $translations = ['welcome' => 'Welcome', 'goodbye' => 'Goodbye'];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        // Mock file operations for saving
        $this->files->shouldReceive('exists')->with('/tmp/translations')->andReturn(true);
        $this->files->shouldReceive('put')->once()->andReturn(true);
        $this->files->shouldReceive('move')->once()->andReturn(true);

        // Mock cache operations
        $this->cache->shouldReceive('forget')->once();

        $success = $this->manager->forget('goodbye', 'en');
        $this->assertTrue($success);
    }

    /**
     * Test getting all translations.
     */
    public function testGetAllTranslations(): void
    {
        $translations = ['welcome' => 'Welcome', 'nav' => ['home' => 'Home']];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        $result = $this->manager->all();
        $this->assertEquals($translations, $result);
    }

    /**
     * Test finding missing translations.
     */
    public function testMissingTranslations(): void
    {
        $baseTranslations = ['welcome' => 'Welcome', 'goodbye' => 'Goodbye', 'nav' => ['home' => 'Home']];
        $targetTranslations = ['welcome' => 'Bienvenido'];

        // Mock for base locale (en)
        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($baseTranslations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        // Mock for target locale (es)
        $this->files->shouldReceive('exists')->with('/tmp/translations/es.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/es.json')->andReturn(json_encode($targetTranslations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/es.json')->andReturn(time());

        $missing = $this->manager->missing('es');
        $this->assertContains('goodbye', $missing);
        $this->assertContains('nav.home', $missing);
    }

    /**
     * Test translation key generation.
     */
    public function testGenerateKey(): void
    {
        $key = $this->manager->generateKey('Hello World!');
        $this->assertEquals('hello_world', $key);

        $key = $this->manager->generateKey('User Settings', 'settings');
        $this->assertEquals('settings.user_settings', $key);
    }

    /**
     * Test export functionality.
     */
    public function testExportTranslations(): void
    {
        $translations = ['welcome' => 'Welcome', 'nav' => ['home' => 'Home']];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        // Test JSON export
        $jsonExport = $this->manager->export('en', 'json');
        $this->assertJson($jsonExport);
        $this->assertEquals($translations, json_decode($jsonExport, true));

        // Test CSV export
        $csvExport = $this->manager->export('en', 'csv');
        $this->assertStringContains('key,value', $csvExport);
        $this->assertStringContains('welcome,"Welcome"', $csvExport);
    }

    /**
     * Test import functionality.
     */
    public function testImportTranslations(): void
    {
        $jsonContent = json_encode(['imported' => 'Imported Value']);

        // Mock file operations
        $this->files->shouldReceive('exists')->with('/tmp/translations')->andReturn(true);
        $this->files->shouldReceive('put')->once()->andReturn(true);
        $this->files->shouldReceive('move')->once()->andReturn(true);

        // Mock cache operations
        $this->cache->shouldReceive('forget')->once();

        $success = $this->manager->import('en', $jsonContent, 'json');
        $this->assertTrue($success);
    }

    /**
     * Test validation functionality.
     */
    public function testValidateTranslation(): void
    {
        // Valid translation
        $result = $this->manager->validate('valid.key', 'Valid value', 'en');
        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['issues']);

        // Invalid key
        $result = $this->manager->validate('invalid key!', 'Value', 'en');
        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['issues']);

        // Invalid locale
        $result = $this->manager->validate('key', 'Value', 'invalid');
        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['issues']);
    }

    /**
     * Test statistics functionality.
     */
    public function testGetStatistics(): void
    {
        $translations = ['welcome' => 'Welcome', 'nav' => ['home' => 'Home']];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());
        $this->files->shouldReceive('size')->with('/tmp/translations/en.json')->andReturn(1024);

        $stats = $this->manager->getStatistics();

        $this->assertArrayHasKey('locale', $stats);
        $this->assertArrayHasKey('total_keys', $stats);
        $this->assertArrayHasKey('total_characters', $stats);
        $this->assertEquals('en', $stats['locale']);
        $this->assertEquals(2, $stats['total_keys']); // 'welcome' and 'nav.home'
    }

    /**
     * Test cache operations.
     */
    public function testCacheOperations(): void
    {
        // Test cache clearing
        $this->cache->shouldReceive('forget')->times(3); // For all locales

        $success = $this->manager->clearCache();
        $this->assertTrue($success);

        // Test cache warm-up
        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn('{}');
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        $this->files->shouldReceive('exists')->with('/tmp/translations/es.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/es.json')->andReturn('{}');
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/es.json')->andReturn(time());

        $this->files->shouldReceive('exists')->with('/tmp/translations/fr.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/fr.json')->andReturn('{}');
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/fr.json')->andReturn(time());

        $success = $this->manager->warmUp();
        $this->assertTrue($success);
    }

    /**
     * Test search functionality.
     */
    public function testSearchTranslations(): void
    {
        $translations = ['welcome' => 'Welcome', 'greeting' => 'Hello World'];

        $this->files->shouldReceive('exists')->with('/tmp/translations/en.json')->andReturn(true);
        $this->files->shouldReceive('get')->with('/tmp/translations/en.json')->andReturn(json_encode($translations));
        $this->files->shouldReceive('lastModified')->with('/tmp/translations/en.json')->andReturn(time());

        // Search by value
        $results = $this->manager->search('Welcome', ['type' => 'value']);
        $this->assertNotEmpty($results);
        $this->assertEquals('welcome', $results[0]['key']);

        // Search by key
        $results = $this->manager->search('greeting', ['type' => 'key']);
        $this->assertNotEmpty($results);
        $this->assertEquals('Hello World', $results[0]['value']);
    }

    /**
     * Test file format detection.
     */
    public function testFileFormatDetection(): void
    {
        $fileInfo = $this->manager->getFileInfo('en');
        $this->assertArrayHasKey('format', $fileInfo);
        $this->assertArrayHasKey('exists', $fileInfo);
        $this->assertArrayHasKey('path', $fileInfo);
    }

    /**
     * Test error handling for invalid operations.
     */
    public function testErrorHandling(): void
    {
        // Test invalid translation key
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->get('invalid key!');
    }

    /**
     * Test locale management.
     */
    public function testLocaleManagement(): void
    {
        $availableLocales = $this->manager->getAvailableLocales();
        $this->assertEquals(['en', 'es', 'fr'], $availableLocales);

        $currentLocale = $this->manager->getCurrentLocale();
        $this->assertEquals('en', $currentLocale);

        $this->manager->setCurrentLocale('es');
        $this->assertEquals('es', $this->manager->getCurrentLocale());

        // Test invalid locale
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->setCurrentLocale('invalid');
    }

    /**
     * Test bulk operations.
     */
    public function testBulkOperations(): void
    {
        $operations = [
            ['type' => 'set', 'key' => 'key1', 'value' => 'Value 1', 'locale' => 'en'],
            ['type' => 'set', 'key' => 'key2', 'value' => 'Value 2', 'locale' => 'en'],
        ];

        // Mock the necessary file operations for each bulk operation
        $this->files->shouldReceive('exists')->andReturn(true);
        $this->files->shouldReceive('get')->andReturn('{}');
        $this->files->shouldReceive('lastModified')->andReturn(time());
        $this->files->shouldReceive('put')->andReturn(true);
        $this->files->shouldReceive('move')->andReturn(true);
        $this->cache->shouldReceive('forget');

        $results = $this->manager->bulk($operations, ['commit_after_bulk' => false]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
    }
}