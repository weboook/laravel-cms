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
                // If the first part of the key matches the file name, remove it
                if (count($keyParts) > 0 && $keyParts[0] === $file) {
                    array_shift($keyParts);
                }
            } else {
                // Use the first part as the file name
                $file = array_shift($keyParts);
            }

            // The remaining parts form the nested key path
            $keyPath = $keyParts;

            // Get the lang file path - try Laravel 11+ path first, then fallback
            $baseLangPath = function_exists('lang_path') ? lang_path() : base_path('lang');
            if (!File::exists($baseLangPath)) {
                $baseLangPath = resource_path('lang');
            }

            $langPath = "{$baseLangPath}/{$validated['locale']}/{$file}.php";

            // Check if file exists
            if (!File::exists($langPath)) {
                // Try JSON format
                $jsonPath = "{$baseLangPath}/{$validated['locale']}.json";
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

            // Debug logging
            $this->logger->info('Translation update debug', [
                'key' => $validated['key'],
                'keyPath' => $keyPath,
                'value' => $validated['value'],
                'file' => $file,
                'langPath' => $langPath
            ]);

            // Update the nested key
            $translations = $this->setNestedArrayValue($translations, $keyPath, $validated['value']);

            // Write back to file
            $this->writePhpArray($langPath, $translations);

            // Clear Laravel's translation cache
            if (method_exists(\Illuminate\Support\Facades\Cache::class, 'forget')) {
                \Illuminate\Support\Facades\Cache::forget('translations');
            }

            // Clear OPcache for this file
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($langPath, true);
            }

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

        // Clear Laravel's translation cache
        if (method_exists(\Illuminate\Support\Facades\Cache::class, 'forget')) {
            \Illuminate\Support\Facades\Cache::forget('translations');
        }

        // Clear OPcache for this file
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filePath, true);
        }

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
        // If no keys, return the value directly
        if (empty($keys)) {
            return $value;
        }

        // Navigate through the array using references
        $current = &$array;

        // Process all keys except the last one
        for ($i = 0; $i < count($keys) - 1; $i++) {
            $key = $keys[$i];

            // Create nested array if it doesn't exist or isn't an array
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }

            // Move deeper into the array
            $current = &$current[$key];
        }

        // Set the final value using the last key
        $lastKey = $keys[count($keys) - 1];
        $current[$lastKey] = $value;

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
        // Try Laravel 11+ path first (base_path/lang), then fallback to Laravel 10 (resources/lang)
        $langPath = function_exists('lang_path') ? lang_path() : base_path('lang');

        if (!File::exists($langPath)) {
            $langPath = resource_path('lang');
        }

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

    /**
     * Convert a hard-coded string to a translation
     *
     * This creates a new translation key in the specified locales
     * and updates the source file to use @lang() or __() directive
     */
    public function convertToTranslation(Request $request)
    {
        $validated = $request->validate([
            'element_id' => 'required|string',
            'original_content' => 'required|string',
            'translation_key' => 'required|string',
            'file_path' => 'required|string',
            'locales' => 'required|array',
            'locales.*' => 'string',
            'namespace' => 'nullable|string',  // e.g., 'messages', 'common'
            'use_json' => 'boolean'  // Use JSON translations instead of PHP arrays
        ]);

        try {
            $filePath = base_path($validated['file_path']);

            // Security check - ensure file is within project
            if (!str_starts_with(realpath($filePath), realpath(base_path()))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid file path'
                ], 403);
            }

            // Validate that the file exists
            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Source file not found'
                ], 404);
            }

            // Parse the translation key
            $namespace = $validated['namespace'] ?? 'messages';
            $fullKey = $namespace . '.' . $validated['translation_key'];
            $useJson = $validated['use_json'] ?? false;

            // Create translation entries in all specified locales
            $createdTranslations = [];
            foreach ($validated['locales'] as $locale) {
                $result = $this->createTranslationEntry(
                    $fullKey,
                    $validated['original_content'],
                    $locale,
                    $namespace,
                    $useJson
                );

                if ($result['success']) {
                    $createdTranslations[] = [
                        'locale' => $locale,
                        'file' => $result['file']
                    ];
                } else {
                    // Log warning but continue with other locales
                    $this->logger->warning('Failed to create translation for locale', [
                        'locale' => $locale,
                        'key' => $fullKey,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                }
            }

            // Update the source Blade file to use translation directive
            $fileUpdater = app(\Webook\LaravelCMS\Services\FileUpdater::class);
            $updateResult = $fileUpdater->convertToTranslationDirective(
                $filePath,
                $validated['element_id'],
                $validated['original_content'],
                $fullKey
            );

            if (!$updateResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update source file: ' . ($updateResult['error'] ?? 'Unknown error')
                ], 500);
            }

            $this->logger->info('Converted string to translation', [
                'key' => $fullKey,
                'source_file' => $validated['file_path'],
                'locales' => $validated['locales'],
                'created_files' => array_column($createdTranslations, 'file')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully converted to translation',
                'translation_key' => $fullKey,
                'created_translations' => $createdTranslations,
                'updated_file' => $validated['file_path']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to convert to translation', [
                'error' => $e->getMessage(),
                'file' => $validated['file_path'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a translation entry in the specified locale
     *
     * @param string $key Full translation key (e.g., 'messages.welcome')
     * @param string $value The translation value
     * @param string $locale The locale code
     * @param string $namespace The namespace/file (e.g., 'messages')
     * @param bool $useJson Whether to use JSON format
     * @return array Result array with success status
     */
    protected function createTranslationEntry($key, $value, $locale, $namespace, $useJson = false)
    {
        try {
            $baseLangPath = function_exists('lang_path') ? lang_path() : base_path('lang');
            if (!File::exists($baseLangPath)) {
                $baseLangPath = resource_path('lang');
            }

            if ($useJson) {
                // Use JSON translation format
                $jsonPath = "{$baseLangPath}/{$locale}.json";

                // Create locale JSON file if it doesn't exist
                if (!File::exists($jsonPath)) {
                    File::put($jsonPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                // Load existing translations
                $translations = json_decode(File::get($jsonPath), true) ?: [];

                // Add new translation
                $translations[$key] = $value;

                // Sort by key for consistency
                ksort($translations);

                // Write back
                File::put($jsonPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $this->logger->info('Created JSON translation entry', [
                    'key' => $key,
                    'locale' => $locale,
                    'file' => $jsonPath
                ]);

                return [
                    'success' => true,
                    'file' => $jsonPath
                ];
            } else {
                // Use PHP array format
                $localeDir = "{$baseLangPath}/{$locale}";
                $filePath = "{$localeDir}/{$namespace}.php";

                // Create locale directory if it doesn't exist
                if (!File::exists($localeDir)) {
                    File::makeDirectory($localeDir, 0755, true);
                }

                // Create or load translation file
                if (File::exists($filePath)) {
                    $translations = include $filePath;
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                } else {
                    $translations = [];
                }

                // Parse nested key (e.g., 'messages.welcome.title' -> ['welcome', 'title'])
                $keyParts = explode('.', $key);
                // Remove namespace if it matches
                if (count($keyParts) > 0 && $keyParts[0] === $namespace) {
                    array_shift($keyParts);
                }

                // Add the translation
                $translations = $this->setNestedArrayValue($translations, $keyParts, $value);

                // Write to file
                $this->writePhpArray($filePath, $translations);

                $this->logger->info('Created PHP translation entry', [
                    'key' => $key,
                    'locale' => $locale,
                    'file' => $filePath
                ]);

                return [
                    'success' => true,
                    'file' => $filePath
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to create translation entry', [
                'key' => $key,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
