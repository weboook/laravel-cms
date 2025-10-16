<?php

namespace Webook\LaravelCMS\Tests\Feature;

use Webook\LaravelCMS\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class TranslationConversionTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    /**
     * Test converting hard-coded string to translation
     *
     * @test
     */
    public function test_convert_hardcoded_string_to_translation()
    {
        // Create a test view with hard-coded content
        $viewPath = 'resources/views/test-hardcoded.blade.php';
        $viewContent = <<<'BLADE'
<html>
<body>
    <p>Welcome to our website</p>
</body>
</html>
BLADE;

        $viewFile = $this->createTestFile($viewPath, $viewContent);

        // Convert the string to a translation
        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'Welcome to our website',
            'translation_key' => 'welcome_message',
            'file_path' => $viewPath,
            'locales' => ['en', 'es'],
            'namespace' => 'messages',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'translation_key' => 'messages.welcome_message',
        ]);

        // Assert view file was updated with @lang directive
        $this->assertBladeHasTranslation($viewFile, 'messages.welcome_message');

        // Assert translation files were created
        $this->assertTranslationExists('en', 'messages', 'welcome_message');
        $this->assertTranslationExists('es', 'messages', 'welcome_message');
    }

    /**
     * Test translation files are seeded with original content
     *
     * @test
     */
    public function test_translation_files_seeded_with_original_content()
    {
        $viewPath = 'resources/views/test-seed.blade.php';
        $viewContent = '<p>Original text content</p>';
        $viewFile = $this->createTestFile($viewPath, $viewContent);

        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'Original text content',
            'translation_key' => 'test_content',
            'file_path' => $viewPath,
            'locales' => ['en'],
            'namespace' => 'messages',
        ]);

        $response->assertStatus(200);

        // Check that the translation file contains the original content
        $langPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        $translationFile = "{$langPath}/en/messages.php";

        $translations = include $translationFile;
        $this->assertEquals('Original text content', $translations['test_content']);
    }

    /**
     * Test multiple locales are created simultaneously
     *
     * @test
     */
    public function test_multiple_locales_created_simultaneously()
    {
        $viewPath = 'resources/views/test-multi-locale.blade.php';
        $viewContent = '<p>Multi-language text</p>';
        $viewFile = $this->createTestFile($viewPath, $viewContent);

        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'Multi-language text',
            'translation_key' => 'multi_lang',
            'file_path' => $viewPath,
            'locales' => ['en', 'es', 'fr', 'de'],
            'namespace' => 'messages',
        ]);

        $response->assertStatus(200);

        // Assert all locale files were created
        foreach (['en', 'es', 'fr', 'de'] as $locale) {
            $this->assertTranslationExists($locale, 'messages', 'multi_lang');
        }
    }

    /**
     * Test JSON translation format
     *
     * @test
     */
    public function test_json_translation_format()
    {
        $viewPath = 'resources/views/test-json.blade.php';
        $viewContent = '<p>JSON format text</p>';
        $viewFile = $this->createTestFile($viewPath, $viewContent);

        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'JSON format text',
            'translation_key' => 'json_text',
            'file_path' => $viewPath,
            'locales' => ['en'],
            'namespace' => 'messages',
            'use_json' => true,
        ]);

        $response->assertStatus(200);

        // Check JSON file was created
        $langPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        $jsonFile = "{$langPath}/en.json";

        $this->assertFileExists($jsonFile);

        $translations = json_decode(File::get($jsonFile), true);
        $this->assertArrayHasKey('messages.json_text', $translations);
        $this->assertEquals('JSON format text', $translations['messages.json_text']);
    }

    /**
     * Test backups are created before conversion
     *
     * @test
     */
    public function test_backups_created_before_conversion()
    {
        $viewPath = 'resources/views/test-backup-convert.blade.php';
        $viewContent = '<p>Backup this content</p>';
        $viewFile = $this->createTestFile($viewPath, $viewContent);

        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'Backup this content',
            'translation_key' => 'backup_test',
            'file_path' => $viewPath,
            'locales' => ['en'],
            'namespace' => 'messages',
        ]);

        $response->assertStatus(200);

        // Assert backup was created for the view file
        $this->assertBackupExists($viewFile);
    }

    /**
     * Test nested translation keys
     *
     * @test
     */
    public function test_nested_translation_keys()
    {
        $viewPath = 'resources/views/test-nested.blade.php';
        $viewContent = '<p>Nested key content</p>';
        $viewFile = $this->createTestFile($viewPath, $viewContent);

        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'Nested key content',
            'translation_key' => 'section.subsection.key',
            'file_path' => $viewPath,
            'locales' => ['en'],
            'namespace' => 'messages',
        ]);

        $response->assertStatus(200);

        // Check nested structure was created
        $langPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        $translationFile = "{$langPath}/en/messages.php";

        $translations = include $translationFile;
        $this->assertArrayHasKey('section', $translations);
        $this->assertArrayHasKey('subsection', $translations['section']);
        $this->assertArrayHasKey('key', $translations['section']['subsection']);
        $this->assertEquals('Nested key content', $translations['section']['subsection']['key']);
    }

    /**
     * Test invalid file path is rejected
     *
     * @test
     */
    public function test_invalid_file_path_rejected_for_conversion()
    {
        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'Test content',
            'translation_key' => 'test_key',
            'file_path' => '../../../etc/passwd',
            'locales' => ['en'],
            'namespace' => 'messages',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'Invalid file path'
        ]);
    }

    /**
     * Test conversion with missing original content
     *
     * @test
     */
    public function test_conversion_fails_if_content_not_found()
    {
        $viewPath = 'resources/views/test-not-found.blade.php';
        $viewContent = '<p>This is the actual content</p>';
        $viewFile = $this->createTestFile($viewPath, $viewContent);

        $response = $this->postJson('/api/cms/translations/convert', [
            'element_id' => 'p-test',
            'original_content' => 'Content that does not exist',
            'translation_key' => 'test_key',
            'file_path' => $viewPath,
            'locales' => ['en'],
            'namespace' => 'messages',
        ]);

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
    }

    /**
     * Clean up test files
     */
    protected function cleanupTestFiles()
    {
        $testFiles = [
            'resources/views/test-hardcoded.blade.php',
            'resources/views/test-seed.blade.php',
            'resources/views/test-multi-locale.blade.php',
            'resources/views/test-json.blade.php',
            'resources/views/test-backup-convert.blade.php',
            'resources/views/test-nested.blade.php',
            'resources/views/test-not-found.blade.php',
        ];

        foreach ($testFiles as $file) {
            $fullPath = base_path($file);
            if (File::exists($fullPath)) {
                $this->cleanupTestFile($fullPath);
            }
        }

        // Clean up test translation files
        $langPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        $testLocales = ['en', 'es', 'fr', 'de'];

        foreach ($testLocales as $locale) {
            $messagesFile = "{$langPath}/{$locale}/messages.php";
            if (File::exists($messagesFile)) {
                $translations = include $messagesFile;

                // Remove test keys
                unset($translations['welcome_message']);
                unset($translations['test_content']);
                unset($translations['multi_lang']);
                unset($translations['backup_test']);
                unset($translations['section']);

                // If empty, delete the file
                if (empty($translations)) {
                    File::delete($messagesFile);
                } else {
                    // Otherwise write back
                    $export = var_export($translations, true);
                    $content = "<?php\n\nreturn " . $export . ";\n";
                    File::put($messagesFile, $content);
                }
            }

            // Clean up JSON files
            $jsonFile = "{$langPath}/{$locale}.json";
            if (File::exists($jsonFile)) {
                $translations = json_decode(File::get($jsonFile), true);
                unset($translations['messages.json_text']);

                if (empty($translations)) {
                    File::delete($jsonFile);
                } else {
                    File::put($jsonFile, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }
}
