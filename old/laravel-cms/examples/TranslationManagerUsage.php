<?php

namespace Webook\LaravelCMS\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Webook\LaravelCMS\Contracts\TranslationRepositoryInterface;

/**
 * Translation Manager Usage Examples
 *
 * This file demonstrates various ways to use the TranslationManager service
 * in your Laravel controllers and services.
 */
class TranslationManagerUsage
{
    protected TranslationRepositoryInterface $translationManager;

    public function __construct(TranslationRepositoryInterface $translationManager)
    {
        $this->translationManager = $translationManager;
    }

    /**
     * Example 1: Basic translation operations
     *
     * Demonstrates get, set, has, and forget operations.
     */
    public function basicOperationsExample(Request $request): JsonResponse
    {
        try {
            // Get a translation
            $welcomeMessage = $this->translationManager->get('welcome.message', 'en');

            // Set a new translation
            $this->translationManager->set('new.key', 'New translation value', 'en');

            // Check if a translation exists
            $hasTranslation = $this->translationManager->has('welcome.message', 'en');

            // Get translation with parameters
            $greeting = $this->translationManager->get(
                'greeting.user',
                'en',
                ['name' => 'John', 'time' => 'morning']
            );

            // Remove a translation
            $this->translationManager->forget('old.key', 'en');

            return response()->json([
                'success' => true,
                'data' => [
                    'welcome_message' => $welcomeMessage,
                    'has_translation' => $hasTranslation,
                    'greeting' => $greeting,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 2: Working with multiple locales
     *
     * Demonstrates locale management and synchronization.
     */
    public function multiLocaleExample(Request $request): JsonResponse
    {
        try {
            // Get available locales
            $availableLocales = $this->translationManager->getAvailableLocales();

            // Set current locale
            $this->translationManager->setCurrentLocale('es');

            // Synchronize a key across multiple locales
            $this->translationManager->sync('welcome.message', [
                'en' => 'Welcome',
                'es' => 'Bienvenido',
                'fr' => 'Bienvenue',
            ]);

            // Find missing translations
            $missingInSpanish = $this->translationManager->missing('es', 'en');
            $missingInFrench = $this->translationManager->missing('fr', 'en');

            // Get all translations for a specific locale
            $allSpanishTranslations = $this->translationManager->all('es');

            return response()->json([
                'success' => true,
                'data' => [
                    'available_locales' => $availableLocales,
                    'current_locale' => $this->translationManager->getCurrentLocale(),
                    'missing_in_spanish' => $missingInSpanish,
                    'missing_in_french' => $missingInFrench,
                    'spanish_translation_count' => count($allSpanishTranslations),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 3: Import and export operations
     *
     * Demonstrates file format conversion and data exchange.
     */
    public function importExportExample(Request $request): JsonResponse
    {
        try {
            $locale = $request->input('locale', 'en');
            $format = $request->input('format', 'json');

            // Export translations
            $exportedData = $this->translationManager->export($locale, $format);

            // Example JSON import data
            $importData = json_encode([
                'imported' => [
                    'key1' => 'Imported value 1',
                    'key2' => 'Imported value 2',
                ],
                'app' => [
                    'name' => 'My Application',
                    'version' => '1.0.0',
                ],
            ]);

            // Import translations
            $importSuccess = $this->translationManager->import($locale, $importData, 'json', [
                'merge' => true, // Merge with existing translations
            ]);

            // Example CSV export
            $csvExport = $this->translationManager->export($locale, 'csv');

            return response()->json([
                'success' => true,
                'data' => [
                    'exported_data' => $exportedData,
                    'import_success' => $importSuccess,
                    'csv_preview' => substr($csvExport, 0, 200) . '...',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 4: Backup and restore operations
     *
     * Demonstrates backup creation and restoration.
     */
    public function backupRestoreExample(Request $request): JsonResponse
    {
        try {
            // Create a backup of all translations
            $backupId = $this->translationManager->backup([
                'locales' => ['en', 'es', 'fr'],
                'format' => 'json',
            ]);

            // Get list of available backups
            $availableBackups = $this->translationManager->getBackups();

            // Make some changes
            $this->translationManager->set('test.backup', 'This is a test', 'en');

            // Restore from backup (if requested)
            if ($request->boolean('restore') && $backupId) {
                $restoreSuccess = $this->translationManager->restore($backupId, [
                    'locales' => ['en'], // Only restore English
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'backup_id' => $backupId,
                    'available_backups' => array_slice($availableBackups, 0, 5), // Show last 5
                    'restore_success' => $restoreSuccess ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 5: Translation suggestions and AI assistance
     *
     * Demonstrates key generation and similarity matching.
     */
    public function suggestionsExample(Request $request): JsonResponse
    {
        try {
            $content = $request->input('content', 'Welcome to our amazing application! Please login to continue.');
            $locale = $request->input('locale', 'en');

            // Generate translation key suggestions
            $suggestions = $this->translationManager->suggestKeys($content, $locale);

            // Generate a key from text
            $generatedKey = $this->translationManager->generateKey('User Dashboard Settings', 'app');

            // Find similar translations
            $similarTranslations = $this->translationManager->findSimilar(
                'Welcome message',
                $locale,
                0.7 // 70% similarity threshold
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions,
                    'generated_key' => $generatedKey,
                    'similar_translations' => $similarTranslations,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 6: Validation and security
     *
     * Demonstrates translation validation and safety checks.
     */
    public function validationExample(Request $request): JsonResponse
    {
        try {
            $key = $request->input('key', 'app.welcome');
            $value = $request->input('value', 'Welcome to our application');
            $locale = $request->input('locale', 'en');

            // Validate translation
            $validation = $this->translationManager->validate($key, $value, $locale);

            // Example of unsafe content validation
            $unsafeValue = '<script>alert("xss")</script>Hello';
            $unsafeValidation = $this->translationManager->validate('test.unsafe', $unsafeValue, $locale);

            // Example of invalid key validation
            $invalidKey = 'invalid key with spaces!';
            $invalidKeyValidation = $this->translationManager->validate($invalidKey, 'Value', $locale);

            return response()->json([
                'success' => true,
                'data' => [
                    'safe_validation' => $validation,
                    'unsafe_validation' => $unsafeValidation,
                    'invalid_key_validation' => $invalidKeyValidation,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 7: Search and discovery
     *
     * Demonstrates translation search capabilities.
     */
    public function searchExample(Request $request): JsonResponse
    {
        try {
            $query = $request->input('query', 'welcome');
            $locales = $request->input('locales', ['en']);

            // Search by value
            $valueResults = $this->translationManager->search($query, [
                'type' => 'value',
                'locales' => $locales,
                'case_sensitive' => false,
            ]);

            // Search by key
            $keyResults = $this->translationManager->search($query, [
                'type' => 'key',
                'locales' => $locales,
                'case_sensitive' => false,
            ]);

            // Search both keys and values with regex
            $regexResults = $this->translationManager->search('wel.*me', [
                'type' => 'both',
                'locales' => $locales,
                'regex' => true,
                'case_sensitive' => false,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'value_results' => $valueResults,
                    'key_results' => $keyResults,
                    'regex_results' => $regexResults,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 8: Bulk operations
     *
     * Demonstrates batch processing of translations.
     */
    public function bulkOperationsExample(Request $request): JsonResponse
    {
        try {
            // Define bulk operations
            $operations = [
                [
                    'type' => 'set',
                    'key' => 'bulk.test1',
                    'value' => 'Bulk operation test 1',
                    'locale' => 'en',
                ],
                [
                    'type' => 'set',
                    'key' => 'bulk.test2',
                    'value' => 'Bulk operation test 2',
                    'locale' => 'en',
                ],
                [
                    'type' => 'sync',
                    'key' => 'bulk.sync',
                    'locales' => [
                        'en' => 'Synchronized message',
                        'es' => 'Mensaje sincronizado',
                        'fr' => 'Message synchronisé',
                    ],
                ],
                [
                    'type' => 'forget',
                    'key' => 'old.unused.key',
                    'locale' => 'en',
                ],
            ];

            // Execute bulk operations
            $results = $this->translationManager->bulk($operations, [
                'commit_after_bulk' => false, // Don't auto-commit to git
            ]);

            // Count successful and failed operations
            $successful = count(array_filter($results, fn($r) => $r['success']));
            $failed = count(array_filter($results, fn($r) => !$r['success']));

            return response()->json([
                'success' => true,
                'data' => [
                    'total_operations' => count($operations),
                    'successful' => $successful,
                    'failed' => $failed,
                    'results' => $results,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 9: Statistics and monitoring
     *
     * Demonstrates performance monitoring and analytics.
     */
    public function statisticsExample(Request $request): JsonResponse
    {
        try {
            $locale = $request->input('locale', 'en');

            // Get translation statistics for specific locale
            $localeStats = $this->translationManager->getStatistics($locale);

            // Get statistics for all available locales
            $allStats = [];
            foreach ($this->translationManager->getAvailableLocales() as $availableLocale) {
                $allStats[$availableLocale] = $this->translationManager->getStatistics($availableLocale);
            }

            // Get file information
            $fileInfo = $this->translationManager->getFileInfo($locale);

            // Calculate completion percentage
            $baseLocale = 'en';
            $baseStats = $this->translationManager->getStatistics($baseLocale);
            $completionPercentage = $baseStats['total_keys'] > 0
                ? round(($localeStats['total_keys'] / $baseStats['total_keys']) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'locale_stats' => $localeStats,
                    'all_stats' => $allStats,
                    'file_info' => $fileInfo,
                    'completion_percentage' => $completionPercentage,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Example 10: Cache management
     *
     * Demonstrates cache warming and clearing operations.
     */
    public function cacheManagementExample(Request $request): JsonResponse
    {
        try {
            $operation = $request->input('operation', 'status'); // status, warm, clear

            $result = match ($operation) {
                'warm' => [
                    'operation' => 'warm_up',
                    'success' => $this->translationManager->warmUp(),
                    'message' => 'Translation cache warmed up for all locales',
                ],
                'clear' => [
                    'operation' => 'clear_cache',
                    'success' => $this->translationManager->clearCache(),
                    'message' => 'Translation cache cleared for all locales',
                ],
                'clear_specific' => [
                    'operation' => 'clear_specific',
                    'success' => $this->translationManager->clearCache(['en', 'es']),
                    'message' => 'Translation cache cleared for specified locales',
                ],
                default => [
                    'operation' => 'status',
                    'available_operations' => ['warm', 'clear', 'clear_specific'],
                    'message' => 'Cache management operations available',
                ],
            };

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}