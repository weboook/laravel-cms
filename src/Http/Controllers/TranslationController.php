<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use Webook\LaravelCMS\Services\CMSLogger;

class TranslationController extends Controller
{
    protected $logger;

    public function __construct(CMSLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get all translations for the current page
     */
    public function getPageTranslations(Request $request)
    {
        $url = $request->input('url');
        $locale = $request->input('locale', App::getLocale());

        // Get translations used on the page
        // This would require parsing the page content
        // For now, return empty array
        return response()->json([
            'success' => true,
            'translations' => [],
            'locale' => $locale
        ]);
    }

    /**
     * Update a translation key
     */
    public function updateTranslation(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'required|string',
            'locale' => 'required|string',
            'file' => 'nullable|string'
        ]);

        try {
            // Parse the translation key to determine file and nested key
            // e.g., 'messages.welcome' -> file: messages.php, key: welcome
            // e.g., 'auth.failed' -> file: auth.php, key: failed

            $keyParts = explode('.', $validated['key']);

            // Determine the file
            if ($validated['file']) {
                $file = $validated['file'];
            } else {
                // Use the first part as the file name
                $file = array_shift($keyParts);
            }

            // The remaining parts form the nested key path
            $keyPath = $keyParts;

            // Get the lang file path
            $langPath = resource_path("lang/{$validated['locale']}/{$file}.php");

            // Check if file exists
            if (!File::exists($langPath)) {
                // Try JSON format
                $jsonPath = resource_path("lang/{$validated['locale']}.json");
                if (File::exists($jsonPath)) {
                    return $this->updateJsonTranslation($jsonPath, $validated['key'], $validated['value']);
                }

                return response()->json([
                    'success' => false,
                    'error' => "Translation file not found: {$langPath}"
                ], 404);
            }

            // Load the translation file
            $translations = include $langPath;

            if (!is_array($translations)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid translation file format'
                ], 400);
            }

            // Create backup
            $this->createBackup($langPath);

            // Update the nested key
            $translations = $this->setNestedArrayValue($translations, $keyPath, $validated['value']);

            // Write back to file
            $this->writePhpArray($langPath, $translations);

            // Log the change
            $this->logger->info('Translation updated', [
                'key' => $validated['key'],
                'locale' => $validated['locale'],
                'file' => $langPath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Translation updated successfully'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update translation', [
                'key' => $validated['key'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update JSON translation
     */
    protected function updateJsonTranslation($filePath, $key, $value)
    {
        $translations = json_decode(File::get($filePath), true) ?: [];

        // Create backup
        $this->createBackup($filePath);

        // Update the translation
        $translations[$key] = $value;

        // Write back to file
        File::put($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->logger->info('JSON translation updated', [
            'key' => $key,
            'file' => $filePath
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Translation updated successfully'
        ]);
    }

    /**
     * Set a value in a nested array by key path
     */
    protected function setNestedArrayValue(array $array, array $keys, $value)
    {
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        // Set the final value
        if (empty($keys)) {
            // If no nested keys, set at root level
            return $value;
        } else {
            // Get the last key
            $lastKey = array_pop($keys);
            $current = &$array;

            foreach ($keys as $key) {
                if (!isset($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }

            $current[$lastKey] = $value;
        }

        return $array;
    }

    /**
     * Write a PHP array to a file
     */
    protected function writePhpArray($filePath, array $data)
    {
        $export = var_export($data, true);

        // Format the array export to be more readable
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $export = str_replace(['array (', ')'], ['[', ']'], $export);
        $export = preg_replace("/=>[ \n\t]+\[/", '=> [', $export);

        $content = "<?php\n\nreturn " . $export . ";\n";

        File::put($filePath, $content);
    }

    /**
     * Create backup of translation file
     */
    protected function createBackup($filePath)
    {
        $backupPath = storage_path('cms/backups/translations');

        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = basename($filePath);
        $backupFile = $backupPath . '/' . $timestamp . '_' . $filename;

        File::copy($filePath, $backupFile);

        $this->logger->logBackup($filePath, $backupFile);
    }

    /**
     * Get available locales from lang directory
     */
    public function getLocales()
    {
        $langPath = resource_path('lang');
        $locales = [];

        if (!File::exists($langPath)) {
            return response()->json([
                'success' => true,
                'locales' => ['en'], // Default
                'current' => App::getLocale()
            ]);
        }

        // Get directories (each represents a locale)
        $directories = File::directories($langPath);

        foreach ($directories as $dir) {
            $locales[] = basename($dir);
        }

        // Also check for JSON translation files
        $jsonFiles = File::glob($langPath . '/*.json');
        foreach ($jsonFiles as $file) {
            $locale = basename($file, '.json');
            if (!in_array($locale, $locales)) {
                $locales[] = $locale;
            }
        }

        return response()->json([
            'success' => true,
            'locales' => $locales,
            'current' => App::getLocale()
        ]);
    }

    /**
     * Get translation by key
     */
    public function getTranslation(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'locale' => 'required|string'
        ]);

        $value = trans($validated['key'], [], $validated['locale']);

        return response()->json([
            'success' => true,
            'key' => $validated['key'],
            'value' => $value,
            'locale' => $validated['locale']
        ]);
    }
}
