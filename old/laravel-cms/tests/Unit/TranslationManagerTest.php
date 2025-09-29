<?php

namespace Webook\LaravelCMS\Tests\Unit;

use Webook\LaravelCMS\Tests\TestCase;
use Webook\LaravelCMS\Services\TranslationManager;
use Webook\LaravelCMS\Exceptions\CMSException;
use Webook\LaravelCMS\Models\Translation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

class TranslationManagerTest extends TestCase
{
    protected TranslationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new TranslationManager();
        $this->createTestTranslationFiles();
    }

    public function test_gets_translation_correctly()
    {
        $this->createTestTranslation('en', 'messages', 'welcome', 'Welcome to our site');

        $translation = $this->manager->get('en', 'messages.welcome');

        $this->assertEquals('Welcome to our site', $translation);
    }

    public function test_gets_nested_translation()
    {
        $this->createTestTranslation('en', 'messages', 'nested.deep.value', 'Deep nested value');

        $translation = $this->manager->get('en', 'messages.nested.deep.value');

        $this->assertEquals('Deep nested value', $translation);
    }

    public function test_returns_default_for_missing_translation()
    {
        $translation = $this->manager->get('en', 'messages.nonexistent', 'Default Value');

        $this->assertEquals('Default Value', $translation);
    }

    public function test_creates_new_translation()
    {
        $result = $this->manager->set('en', 'messages.new_key', 'New translation value');

        $this->assertTrue($result);
        $this->assertDatabaseHasTranslation('en', 'messages', 'new_key', 'New translation value');
    }

    public function test_updates_existing_translation()
    {
        $this->createTestTranslation('en', 'messages', 'welcome', 'Old welcome message');

        $result = $this->manager->set('en', 'messages.welcome', 'Updated welcome message');

        $this->assertTrue($result);
        $this->assertDatabaseHasTranslation('en', 'messages', 'welcome', 'Updated welcome message');
    }

    public function test_deletes_translation()
    {
        $this->createTestTranslation('en', 'messages', 'to_delete', 'Will be deleted');

        $result = $this->manager->delete('en', 'messages.to_delete');

        $this->assertTrue($result);
        $this->assertDatabaseMissingTranslation('en', 'messages', 'to_delete');
    }

    public function test_validates_locale()
    {
        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Invalid locale');

        $this->manager->set('invalid_locale', 'messages.test', 'Test value');
    }

    public function test_validates_translation_key_format()
    {
        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Invalid translation key format');

        $this->manager->set('en', 'invalid.key.', 'Test value');
    }

    public function test_validates_reserved_keys()
    {
        Config::set('cms.translations.reserved_keys', ['system', 'internal']);

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Reserved translation key');

        $this->manager->set('en', 'system.test', 'Test value');
    }

    public function test_validates_key_depth_limit()
    {
        Config::set('cms.translations.max_key_depth', 3);

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Translation key depth exceeds limit');

        $this->manager->set('en', 'level1.level2.level3.level4.test', 'Test value');
    }

    public function test_validates_value_length()
    {
        Config::set('cms.translations.max_length', 50);

        $longValue = str_repeat('x', 100);

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Translation value exceeds maximum length');

        $this->manager->set('en', 'messages.test', $longValue);
    }

    public function test_imports_from_file()
    {
        $translations = [
            'imported_key1' => 'Imported value 1',
            'imported_key2' => 'Imported value 2',
            'nested' => [
                'key' => 'Nested imported value'
            ]
        ];

        $filePath = $this->createTestFile(
            'tests/fixtures/import.php',
            "<?php\n\nreturn " . var_export($translations, true) . ";\n"
        );

        $result = $this->manager->importFromFile('en', 'imported', $filePath);

        $this->assertTrue($result);
        $this->assertDatabaseHasTranslation('en', 'imported', 'imported_key1', 'Imported value 1');
        $this->assertDatabaseHasTranslation('en', 'imported', 'imported_key2', 'Imported value 2');
        $this->assertDatabaseHasTranslation('en', 'imported', 'nested.key', 'Nested imported value');
    }

    public function test_exports_to_file()
    {
        $this->createTestTranslation('en', 'export_test', 'key1', 'Value 1');
        $this->createTestTranslation('en', 'export_test', 'key2', 'Value 2');
        $this->createTestTranslation('en', 'export_test', 'nested.key', 'Nested Value');

        $exportPath = storage_path('framework/testing/export_test.php');

        $result = $this->manager->exportToFile('en', 'export_test', $exportPath);

        $this->assertTrue($result);
        $this->assertFileExists($exportPath);

        $exported = include $exportPath;
        $this->assertEquals('Value 1', $exported['key1']);
        $this->assertEquals('Value 2', $exported['key2']);
        $this->assertEquals('Nested Value', $exported['nested']['key']);
    }

    public function test_bulk_update()
    {
        $updates = [
            'messages.key1' => 'Bulk update 1',
            'messages.key2' => 'Bulk update 2',
            'auth.failed' => 'Authentication failed'
        ];

        $result = $this->manager->bulkUpdate('en', $updates);

        $this->assertTrue($result);
        $this->assertDatabaseHasTranslation('en', 'messages', 'key1', 'Bulk update 1');
        $this->assertDatabaseHasTranslation('en', 'messages', 'key2', 'Bulk update 2');
        $this->assertDatabaseHasTranslation('en', 'auth', 'failed', 'Authentication failed');
    }

    public function test_finds_missing_translations()
    {
        $this->createTestTranslation('en', 'messages', 'welcome', 'Welcome');
        $this->createTestTranslation('es', 'messages', 'welcome', 'Bienvenido');
        $this->createTestTranslation('en', 'messages', 'goodbye', 'Goodbye');
        // Missing 'goodbye' in Spanish

        $missing = $this->manager->findMissingTranslations('es', 'en');

        $this->assertNotEmpty($missing);
        $this->assertContains('messages.goodbye', $missing);
    }

    public function test_auto_translates_missing_keys()
    {
        Config::set('cms.features.ai_enabled', true);

        $this->createTestTranslation('en', 'messages', 'hello', 'Hello World');

        // Mock AI translation service
        $this->mockAITranslationService('Hello World', 'Hola Mundo');

        $result = $this->manager->autoTranslate('en', 'es', ['messages.hello']);

        $this->assertTrue($result);
        $this->assertDatabaseHasTranslation('es', 'messages', 'hello', 'Hola Mundo');
    }

    public function test_syncs_with_language_files()
    {
        Config::set('cms.translations.update_files', true);

        $this->createTestTranslation('en', 'sync_test', 'key1', 'Database Value 1');
        $this->createTestTranslation('en', 'sync_test', 'key2', 'Database Value 2');

        $langPath = resource_path('lang/en/sync_test.php');
        $this->manager->syncToFile('en', 'sync_test');

        $this->assertFileExists($langPath);
        $fileContent = include $langPath;
        $this->assertEquals('Database Value 1', $fileContent['key1']);
        $this->assertEquals('Database Value 2', $fileContent['key2']);
    }

    public function test_backs_up_before_update()
    {
        Config::set('cms.content.auto_backup', true);

        $this->createTestTranslation('en', 'messages', 'backup_test', 'Original value');

        $this->manager->set('en', 'messages.backup_test', 'Updated value');

        // Check backup was created
        $backupFiles = Storage::disk('testing')->files('backups');
        $this->assertNotEmpty($backupFiles);

        $found = false;
        foreach ($backupFiles as $backup) {
            if (str_contains($backup, 'translation_backup')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_caches_translations()
    {
        $this->createTestTranslation('en', 'messages', 'cached_key', 'Cached value');

        // First call - should hit database
        $translation1 = $this->manager->get('en', 'messages.cached_key');

        // Second call - should hit cache
        $translation2 = $this->manager->get('en', 'messages.cached_key');

        $this->assertEquals($translation1, $translation2);
        $this->assertEquals('Cached value', $translation2);
    }

    public function test_invalidates_cache_on_update()
    {
        $this->createTestTranslation('en', 'messages', 'cache_test', 'Original');

        // Cache the translation
        $original = $this->manager->get('en', 'messages.cache_test');
        $this->assertEquals('Original', $original);

        // Update the translation
        $this->manager->set('en', 'messages.cache_test', 'Updated');

        // Should get updated value, not cached
        $updated = $this->manager->get('en', 'messages.cache_test');
        $this->assertEquals('Updated', $updated);
    }

    public function test_handles_pluralization()
    {
        $this->createTestTranslation('en', 'messages', 'items', '{0} No items|{1} One item|[2,*] :count items');

        $zero = $this->manager->choice('en', 'messages.items', 0);
        $one = $this->manager->choice('en', 'messages.items', 1);
        $many = $this->manager->choice('en', 'messages.items', 5, ['count' => 5]);

        $this->assertEquals('No items', $zero);
        $this->assertEquals('One item', $one);
        $this->assertEquals('5 items', $many);
    }

    public function test_handles_parameter_replacement()
    {
        $this->createTestTranslation('en', 'messages', 'welcome_user', 'Welcome, :name! You have :count messages.');

        $translated = $this->manager->get('en', 'messages.welcome_user', null, [
            'name' => 'John',
            'count' => 3
        ]);

        $this->assertEquals('Welcome, John! You have 3 messages.', $translated);
    }

    public function test_validates_translation_conflicts()
    {
        $this->createTestTranslation('en', 'messages', 'conflict_test', 'Original value');

        // Simulate concurrent update
        $translation = Translation::where([
            'locale' => 'en',
            'group' => 'messages',
            'key' => 'conflict_test'
        ])->first();

        $translation->update(['value' => 'Concurrent update']);

        $this->expectException(CMSException::class);
        $this->expectExceptionMessage('Translation conflict detected');

        $this->manager->set('en', 'messages.conflict_test', 'New value', [
            'expected_version' => $translation->updated_at->subSecond()
        ]);
    }

    public function test_tracks_translation_history()
    {
        $this->createTestTranslation('en', 'messages', 'history_test', 'Version 1');

        $this->manager->set('en', 'messages.history_test', 'Version 2');
        $this->manager->set('en', 'messages.history_test', 'Version 3');

        $history = $this->manager->getHistory('en', 'messages.history_test');

        $this->assertCount(3, $history);
        $this->assertEquals('Version 3', $history[0]['value']); // Latest first
        $this->assertEquals('Version 2', $history[1]['value']);
        $this->assertEquals('Version 1', $history[2]['value']);
    }

    public function test_restores_from_history()
    {
        $this->createTestTranslation('en', 'messages', 'restore_test', 'Version 1');
        $this->manager->set('en', 'messages.restore_test', 'Version 2');
        $this->manager->set('en', 'messages.restore_test', 'Version 3');

        $history = $this->manager->getHistory('en', 'messages.restore_test');
        $versionToRestore = $history[2]; // Version 1

        $result = $this->manager->restoreFromHistory('en', 'messages.restore_test', $versionToRestore['id']);

        $this->assertTrue($result);
        $this->assertDatabaseHasTranslation('en', 'messages', 'restore_test', 'Version 1');
    }

    public function test_performance_with_many_translations()
    {
        // Create many translations
        for ($i = 1; $i <= 1000; $i++) {
            $this->createTestTranslation('en', 'performance', "key_{$i}", "Value {$i}");
        }

        $this->assertPerformance(function () {
            for ($i = 1; $i <= 100; $i++) {
                $this->manager->get('en', "performance.key_{$i}");
            }
        }, 1.0, 20 * 1024 * 1024); // 1 second, 20MB
    }

    public function test_handles_locale_fallback()
    {
        Config::set('app.fallback_locale', 'en');

        $this->createTestTranslation('en', 'messages', 'fallback_test', 'English fallback');
        // No Spanish translation

        $translation = $this->manager->get('es', 'messages.fallback_test');

        $this->assertEquals('English fallback', $translation);
    }

    public function test_batch_import_with_validation()
    {
        $importData = [
            'valid_key' => 'Valid value',
            'system.reserved' => 'Should be rejected', // Reserved key
            'valid_nested.key' => 'Valid nested value',
            'too.deep.nested.key.structure.exceeding.limit' => 'Too deep', // Exceeds depth
        ];

        Config::set('cms.translations.reserved_keys', ['system']);
        Config::set('cms.translations.max_key_depth', 3);

        $result = $this->manager->batchImport('en', 'batch_test', $importData);

        $this->assertArrayHasKey('imported', $result);
        $this->assertArrayHasKey('failed', $result);

        $this->assertCount(2, $result['imported']); // valid_key and valid_nested.key
        $this->assertCount(2, $result['failed']); // system.reserved and too deep key

        $this->assertDatabaseHasTranslation('en', 'batch_test', 'valid_key', 'Valid value');
        $this->assertDatabaseMissingTranslation('en', 'batch_test', 'system.reserved');
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

    protected function mockAITranslationService(string $source, string $translated): void
    {
        // Mock the AI translation service response
        // This would depend on your actual AI service implementation
    }
}