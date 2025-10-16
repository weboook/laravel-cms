<?php

namespace Webook\LaravelCMS\Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../vendor/autoload.php';

        // Bootstrap Laravel if available
        if (file_exists(__DIR__.'/../bootstrap/app.php')) {
            $app = require __DIR__.'/../bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }

        return $app;
    }

    /**
     * Create a temporary test file
     *
     * @param string $relativePath
     * @param string $content
     * @return string Full path to created file
     */
    protected function createTestFile($relativePath, $content)
    {
        $fullPath = base_path($relativePath);
        $directory = dirname($fullPath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($fullPath, $content);

        return $fullPath;
    }

    /**
     * Clean up a test file
     *
     * @param string $path
     */
    protected function cleanupTestFile($path)
    {
        if (File::exists($path)) {
            File::delete($path);
        }

        // Clean up empty parent directories
        $directory = dirname($path);
        if (File::exists($directory) && count(File::files($directory)) === 0) {
            File::deleteDirectory($directory);
        }
    }

    /**
     * Assert that a Blade file contains a translation directive
     *
     * @param string $filePath
     * @param string $translationKey
     */
    protected function assertBladeHasTranslation($filePath, $translationKey)
    {
        $content = File::get($filePath);
        $this->assertStringContainsString("@lang('{$translationKey}')", $content);
    }

    /**
     * Assert that a translation file has a specific key
     *
     * @param string $locale
     * @param string $file
     * @param string $key
     */
    protected function assertTranslationExists($locale, $file, $key)
    {
        $langPath = function_exists('lang_path') ? lang_path() : resource_path('lang');
        $filePath = "{$langPath}/{$locale}/{$file}.php";

        $this->assertFileExists($filePath);

        $translations = include $filePath;
        $keyParts = explode('.', $key);

        $current = $translations;
        foreach ($keyParts as $part) {
            $this->assertArrayHasKey($part, $current);
            $current = $current[$part];
        }
    }

    /**
     * Assert that a backup was created for a file
     *
     * @param string $originalFile
     */
    protected function assertBackupExists($originalFile)
    {
        $backupPath = storage_path('cms/backups');
        $this->assertDirectoryExists($backupPath);

        // Check if any backup exists for this file
        $filename = basename($originalFile);
        $backups = File::glob($backupPath . '/*/' . $filename);

        $this->assertNotEmpty($backups, "No backup found for {$originalFile}");
    }
}
