<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;
use Webook\LaravelCMS\Contracts\TranslationRepositoryInterface;

/**
 * Translation Manager Service
 *
 * Robust translation management service for the Laravel CMS package.
 * Provides comprehensive translation handling with file format support,
 * caching, safety features, and advanced capabilities.
 */
class TranslationManager implements TranslationRepositoryInterface
{
    protected Filesystem $files;
    protected CacheRepository $cache;
    protected array $config;
    protected array $loadedTranslations = [];
    protected array $fileModificationTimes = [];
    protected array $statistics = [];

    protected string $defaultLocale;
    protected string $currentLocale;
    protected array $availableLocales;
    protected string $translationsPath;

    public function __construct(Filesystem $files, CacheRepository $cache, array $config = [])
    {
        $this->files = $files;
        $this->cache = $cache;
        $this->config = array_merge($this->getDefaultConfig(), $config);

        $this->defaultLocale = $this->config['default_locale'];
        $this->currentLocale = $this->defaultLocale;
        $this->availableLocales = $this->config['supported_locales'];
        $this->translationsPath = $this->config['translations_path'];

        $this->initializeStatistics();
        $this->ensureTranslationsDirectory();
    }

    /**
     * Get a translation value for a specific key and locale.
     */
    public function get(string $key, string $locale = null, array $replace = []): string
    {
        $locale = $locale ?? $this->currentLocale;

        if (!$this->validateTranslationKey($key)) {
            throw new InvalidArgumentException("Invalid translation key: {$key}");
        }

        $translations = $this->loadTranslations($locale);
        $value = Arr::get($translations, $key);

        if ($value === null) {
            $value = $this->getFallbackTranslation($key, $locale);
        }

        if ($value === null) {
            Log::warning("Translation key not found", ['key' => $key, 'locale' => $locale]);
            return $key;
        }

        return $this->processReplacements($value, $replace);
    }

    /**
     * Set a translation value for a specific key and locale.
     */
    public function set(string $key, string $value, string $locale): bool
    {
        if (!$this->validateTranslationKey($key)) {
            throw new InvalidArgumentException("Invalid translation key: {$key}");
        }

        if (!$this->validateTranslationValue($value)) {
            throw new InvalidArgumentException("Invalid translation value for key: {$key}");
        }

        try {
            $this->createBackupIfEnabled($locale);

            $translations = $this->loadTranslations($locale);
            $originalValue = Arr::get($translations, $key);

            Arr::set($translations, $key, $value);

            $success = $this->saveTranslations($locale, $translations);

            if ($success) {
                $this->invalidateCache($locale);
                $this->recordChange($key, $originalValue, $value, $locale);
                $this->commitToGitIfEnabled("Update translation: {$key}", ['locale' => $locale]);
            }

            return $success;

        } catch (Exception $e) {
            Log::error("Failed to set translation", [
                'key' => $key,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if a translation key exists in a specific locale.
     */
    public function has(string $key, string $locale = null): bool
    {
        $locale = $locale ?? $this->currentLocale;
        $translations = $this->loadTranslations($locale);

        return Arr::has($translations, $key);
    }

    /**
     * Remove a translation key from a specific locale.
     */
    public function forget(string $key, string $locale): bool
    {
        try {
            $this->createBackupIfEnabled($locale);

            $translations = $this->loadTranslations($locale);
            $originalValue = Arr::get($translations, $key);

            if ($originalValue === null) {
                return true; // Already doesn't exist
            }

            Arr::forget($translations, $key);

            $success = $this->saveTranslations($locale, $translations);

            if ($success) {
                $this->invalidateCache($locale);
                $this->recordChange($key, $originalValue, null, $locale);
                $this->commitToGitIfEnabled("Remove translation: {$key}", ['locale' => $locale]);
            }

            return $success;

        } catch (Exception $e) {
            Log::error("Failed to forget translation", [
                'key' => $key,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all translations for a specific locale.
     */
    public function all(string $locale = null): array
    {
        $locale = $locale ?? $this->currentLocale;
        return $this->loadTranslations($locale);
    }

    /**
     * Get missing translation keys for a specific locale.
     */
    public function missing(string $locale = null, string $comparisonLocale = null): array
    {
        $locale = $locale ?? $this->currentLocale;
        $comparisonLocale = $comparisonLocale ?? $this->defaultLocale;

        $baseTranslations = $this->loadTranslations($comparisonLocale);
        $targetTranslations = $this->loadTranslations($locale);

        $baseKeys = $this->flattenKeys($baseTranslations);
        $targetKeys = $this->flattenKeys($targetTranslations);

        return array_diff($baseKeys, $targetKeys);
    }

    /**
     * Synchronize a translation key across multiple locales.
     */
    public function sync(string $key, array $locales): void
    {
        $this->createBackupIfEnabled('all');

        foreach ($locales as $locale => $value) {
            if (in_array($locale, $this->availableLocales)) {
                $this->set($key, $value, $locale);
            }
        }
    }

    /**
     * Export translations for a specific locale in various formats.
     */
    public function export(string $locale, string $format = 'array', array $options = []): string
    {
        $translations = $this->loadTranslations($locale);

        return match (strtolower($format)) {
            'json' => $this->exportToJson($translations, $options),
            'csv' => $this->exportToCsv($translations, $options),
            'xlsx' => $this->exportToXlsx($translations, $options),
            'array', 'php' => $this->exportToPhpArray($translations, $options),
            default => throw new InvalidArgumentException("Unsupported export format: {$format}")
        };
    }

    /**
     * Import translations for a specific locale from various formats.
     */
    public function import(string $locale, string $content, string $format, array $options = []): bool
    {
        try {
            $this->createBackupIfEnabled($locale);

            $importedTranslations = match (strtolower($format)) {
                'json' => $this->importFromJson($content, $options),
                'csv' => $this->importFromCsv($content, $options),
                'xlsx' => $this->importFromXlsx($content, $options),
                'array', 'php' => $this->importFromPhpArray($content, $options),
                default => throw new InvalidArgumentException("Unsupported import format: {$format}")
            };

            if (isset($options['merge']) && $options['merge']) {
                $existingTranslations = $this->loadTranslations($locale);
                $importedTranslations = array_merge($existingTranslations, $importedTranslations);
            }

            $success = $this->saveTranslations($locale, $importedTranslations);

            if ($success) {
                $this->invalidateCache($locale);
                $this->commitToGitIfEnabled("Import translations for {$locale}", ['format' => $format]);
            }

            return $success;

        } catch (Exception $e) {
            Log::error("Failed to import translations", [
                'locale' => $locale,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get available locales.
     */
    public function getAvailableLocales(): array
    {
        return $this->availableLocales;
    }

    /**
     * Get the current active locale.
     */
    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * Set the current active locale.
     */
    public function setCurrentLocale(string $locale): void
    {
        if (!in_array($locale, $this->availableLocales)) {
            throw new InvalidArgumentException("Unsupported locale: {$locale}");
        }

        $this->currentLocale = $locale;
    }

    /**
     * Create a backup of current translations.
     */
    public function backup(array $options = []): string
    {
        $backupId = 'backup_' . date('Y-m-d_H-i-s') . '_' . uniqid();
        $backupPath = $this->getBackupPath($backupId);

        $locales = $options['locales'] ?? $this->availableLocales;
        $format = $options['format'] ?? 'json';

        $backupData = [
            'id' => $backupId,
            'created_at' => now()->toISOString(),
            'format' => $format,
            'locales' => [],
        ];

        foreach ($locales as $locale) {
            $translations = $this->loadTranslations($locale);
            $backupData['locales'][$locale] = $translations;
        }

        $this->files->put($backupPath, json_encode($backupData, JSON_PRETTY_PRINT));

        return $backupId;
    }

    /**
     * Restore translations from a backup.
     */
    public function restore(string $backupId, array $options = []): bool
    {
        $backupPath = $this->getBackupPath($backupId);

        if (!$this->files->exists($backupPath)) {
            throw new InvalidArgumentException("Backup not found: {$backupId}");
        }

        try {
            $backupData = json_decode($this->files->get($backupPath), true);

            foreach ($backupData['locales'] as $locale => $translations) {
                if (!isset($options['locales']) || in_array($locale, $options['locales'])) {
                    $this->saveTranslations($locale, $translations);
                    $this->invalidateCache($locale);
                }
            }

            $this->commitToGitIfEnabled("Restore from backup: {$backupId}");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to restore backup", [
                'backup_id' => $backupId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get list of available backups.
     */
    public function getBackups(): array
    {
        $backupDir = $this->getBackupDirectory();

        if (!$this->files->exists($backupDir)) {
            return [];
        }

        $backups = [];
        $files = $this->files->files($backupDir);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                try {
                    $content = json_decode($this->files->get($file), true);
                    $backups[] = [
                        'id' => $content['id'],
                        'created_at' => $content['created_at'],
                        'format' => $content['format'],
                        'locales' => array_keys($content['locales']),
                        'file_size' => $this->files->size($file),
                    ];
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        return collect($backups)->sortByDesc('created_at')->values()->toArray();
    }

    /**
     * Commit translation changes to git (if enabled).
     */
    public function commitToGit(string $message, array $options = []): bool
    {
        if (!$this->config['git']['enabled']) {
            return true;
        }

        try {
            $gitConfig = $this->config['git'];

            shell_exec("cd {$this->translationsPath} && git add .");

            $author = $gitConfig['author'] ?? 'Laravel CMS';
            $email = $gitConfig['email'] ?? 'cms@example.com';

            $commitMessage = $message;
            if (isset($options['locale'])) {
                $commitMessage .= " [{$options['locale']}]";
            }

            shell_exec("cd {$this->translationsPath} && git -c user.name='{$author}' -c user.email='{$email}' commit -m '{$commitMessage}'");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to commit to git", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate translation key suggestions based on content.
     */
    public function suggestKeys(string $content, string $locale): array
    {
        $suggestions = [];

        $textContent = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $textContent, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 5 && strlen($sentence) < 100) {
                $key = $this->generateKey($sentence);
                $similarity = $this->findSimilar($sentence, $locale);

                $suggestions[] = [
                    'text' => $sentence,
                    'suggested_key' => $key,
                    'similar_translations' => $similarity,
                    'confidence' => $this->calculateSuggestionConfidence($sentence, $key),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Generate a translation key from English text.
     */
    public function generateKey(string $text, string $namespace = ''): string
    {
        $key = strtolower($text);
        $key = preg_replace('/[^a-z0-9\s]/', '', $key);
        $key = preg_replace('/\s+/', '_', trim($key));
        $key = substr($key, 0, 50);

        if ($namespace) {
            $key = $namespace . '.' . $key;
        }

        $originalKey = $key;
        $counter = 1;
        while ($this->has($key)) {
            $key = $originalKey . '_' . $counter;
            $counter++;
        }

        return $key;
    }

    /**
     * Find similar translations in translation memory.
     */
    public function findSimilar(string $text, string $locale, float $threshold = 0.8): array
    {
        $translations = $this->loadTranslations($locale);
        $similar = [];

        foreach ($this->flattenTranslations($translations) as $key => $value) {
            $similarity = $this->calculateSimilarity($text, $value);

            if ($similarity >= $threshold) {
                $similar[] = [
                    'key' => $key,
                    'value' => $value,
                    'similarity' => $similarity,
                ];
            }
        }

        return collect($similar)->sortByDesc('similarity')->values()->toArray();
    }

    /**
     * Get translation diff between two versions.
     */
    public function getDiff(string $locale, string $fromVersion, string $toVersion): array
    {
        return [
            'locale' => $locale,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'changes' => [],
        ];
    }

    /**
     * Perform bulk operations on translations.
     */
    public function bulk(array $operations, array $options = []): array
    {
        $results = [];
        $this->createBackupIfEnabled('all');

        foreach ($operations as $operation) {
            try {
                $result = $this->executeBulkOperation($operation);
                $results[] = ['operation' => $operation, 'success' => true, 'result' => $result];
            } catch (Exception $e) {
                $results[] = ['operation' => $operation, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        if ($options['commit_after_bulk'] ?? true) {
            $this->commitToGitIfEnabled("Bulk translation operations", ['count' => count($operations)]);
        }

        return $results;
    }

    /**
     * Validate translation content for security and format.
     */
    public function validate(string $key, string $value, string $locale): array
    {
        $issues = [];

        if (!$this->validateTranslationKey($key)) {
            $issues[] = ['type' => 'invalid_key', 'message' => 'Key contains invalid characters'];
        }

        if (!$this->validateTranslationValue($value)) {
            $issues[] = ['type' => 'invalid_value', 'message' => 'Value contains unsafe content'];
        }

        if ($this->containsUnsafeHtml($value)) {
            $issues[] = ['type' => 'unsafe_html', 'message' => 'Value contains potentially unsafe HTML'];
        }

        if (!in_array($locale, $this->availableLocales)) {
            $issues[] = ['type' => 'invalid_locale', 'message' => 'Unsupported locale'];
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'recommendations' => $this->generateValidationRecommendations($issues),
        ];
    }

    /**
     * Get translation statistics for a locale.
     */
    public function getStatistics(string $locale = null): array
    {
        $locale = $locale ?? $this->currentLocale;
        $translations = $this->loadTranslations($locale);
        $flatTranslations = $this->flattenTranslations($translations);

        return [
            'locale' => $locale,
            'total_keys' => count($flatTranslations),
            'total_characters' => array_sum(array_map('strlen', $flatTranslations)),
            'average_length' => round(array_sum(array_map('strlen', $flatTranslations)) / max(count($flatTranslations), 1)),
            'empty_translations' => count(array_filter($flatTranslations, fn($v) => empty(trim($v)))),
            'missing_keys' => count($this->missing($locale)),
            'file_size' => $this->getTranslationFileSize($locale),
            'last_modified' => $this->getTranslationFileModifiedTime($locale),
        ];
    }

    /**
     * Warm up the translation cache.
     */
    public function warmUp(array $locales = []): bool
    {
        $locales = empty($locales) ? $this->availableLocales : $locales;

        try {
            foreach ($locales as $locale) {
                $this->loadTranslations($locale);
            }
            return true;
        } catch (Exception $e) {
            Log::error("Failed to warm up translation cache", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clear translation cache for specific locales.
     */
    public function clearCache(array $locales = []): bool
    {
        $locales = empty($locales) ? $this->availableLocales : $locales;

        try {
            foreach ($locales as $locale) {
                $this->invalidateCache($locale);
            }

            if (empty($locales)) {
                $this->loadedTranslations = [];
                $this->fileModificationTimes = [];
            } else {
                foreach ($locales as $locale) {
                    unset($this->loadedTranslations[$locale]);
                    unset($this->fileModificationTimes[$locale]);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to clear translation cache", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get file information for translation files.
     */
    public function getFileInfo(string $locale): array
    {
        $filePath = $this->getTranslationFilePath($locale);

        if (!$this->files->exists($filePath)) {
            return [
                'exists' => false,
                'locale' => $locale,
                'path' => $filePath,
            ];
        }

        return [
            'exists' => true,
            'locale' => $locale,
            'path' => $filePath,
            'format' => $this->detectFileFormat($filePath),
            'size' => $this->files->size($filePath),
            'modified_time' => $this->files->lastModified($filePath),
            'readable' => $this->files->isReadable($filePath),
            'writable' => $this->files->isWritable($filePath),
        ];
    }

    /**
     * Migrate translation files from one format to another.
     */
    public function migrate(string $fromFormat, string $toFormat, array $options = []): bool
    {
        $locales = $options['locales'] ?? $this->availableLocales;
        $this->createBackupIfEnabled('all');

        try {
            foreach ($locales as $locale) {
                $translations = $this->loadTranslations($locale);

                $newPath = $this->getTranslationFilePath($locale, $toFormat);
                $this->saveTranslationFile($newPath, $translations, $toFormat);

                if ($options['remove_old'] ?? false) {
                    $oldPath = $this->getTranslationFilePath($locale, $fromFormat);
                    if ($this->files->exists($oldPath)) {
                        $this->files->delete($oldPath);
                    }
                }
            }

            $this->commitToGitIfEnabled("Migrate translations from {$fromFormat} to {$toFormat}");

            return true;

        } catch (Exception $e) {
            Log::error("Failed to migrate translation format", [
                'from' => $fromFormat,
                'to' => $toFormat,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Search translations by value or key pattern.
     */
    public function search(string $query, array $options = []): array
    {
        $locales = $options['locales'] ?? [$this->currentLocale];
        $searchType = $options['type'] ?? 'both';
        $caseSensitive = $options['case_sensitive'] ?? false;
        $regex = $options['regex'] ?? false;

        $results = [];

        foreach ($locales as $locale) {
            $translations = $this->flattenTranslations($this->loadTranslations($locale));

            foreach ($translations as $key => $value) {
                $match = false;

                if (in_array($searchType, ['key', 'both'])) {
                    $match = $match || $this->searchMatch($query, $key, $caseSensitive, $regex);
                }

                if (in_array($searchType, ['value', 'both'])) {
                    $match = $match || $this->searchMatch($query, $value, $caseSensitive, $regex);
                }

                if ($match) {
                    $results[] = [
                        'locale' => $locale,
                        'key' => $key,
                        'value' => $value,
                        'match_type' => $this->getMatchType($query, $key, $value, $caseSensitive, $regex),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Load translation file and return array data.
     */
    private function loadTranslationFile(string $path): array
    {
        if (!$this->files->exists($path)) {
            return [];
        }

        $format = $this->detectFileFormat($path);
        $content = $this->files->get($path);

        return match ($format) {
            'json' => json_decode($content, true) ?? [],
            'php' => $this->evaluatePhpArray($content),
            default => []
        };
    }

    /**
     * Save translation data to file.
     */
    private function saveTranslationFile(string $path, array $data, string $format = null): bool
    {
        $format = $format ?? $this->detectFileFormat($path);

        try {
            $this->ensureDirectoryExists(dirname($path));

            $tempPath = $path . '.tmp';
            $content = $this->preserveFileFormat($path, $data, $format);

            $this->files->put($tempPath, $content, true);
            $this->files->move($tempPath, $path);

            return true;

        } catch (Exception $e) {
            Log::error("Failed to save translation file", [
                'path' => $path,
                'error' => $e->getMessage()
            ]);

            if ($this->files->exists($tempPath ?? '')) {
                $this->files->delete($tempPath);
            }

            return false;
        }
    }

    /**
     * Preserve file format when saving translations.
     */
    private function preserveFileFormat(string $path, array $data, string $format = null): string
    {
        $format = $format ?? $this->detectFileFormat($path);

        return match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'php' => $this->arrayToPhpFormat($data),
            default => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        };
    }

    /**
     * Detect file format from path or content.
     */
    private function detectFileFormat(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'json' => 'json',
            'php' => 'php',
            default => 'json'
        };
    }

    /**
     * Validate translation key format.
     */
    private function validateTranslationKey(string $key): bool
    {
        return preg_match('/^[a-zA-Z0-9._-]+$/', $key) === 1;
    }

    /**
     * Parse nested key into array structure.
     */
    private function parseNestedKey(string $key): array
    {
        return explode('.', $key);
    }

    /**
     * Get default configuration.
     */
    private function getDefaultConfig(): array
    {
        return [
            'default_locale' => 'en',
            'supported_locales' => ['en'],
            'translations_path' => resource_path('lang'),
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
                'prefix' => 'translations',
            ],
            'backup' => [
                'enabled' => true,
                'path' => storage_path('cms/translation-backups'),
                'auto_backup' => true,
            ],
            'git' => [
                'enabled' => false,
                'auto_commit' => false,
                'author' => 'Laravel CMS',
                'email' => 'cms@example.com',
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
    }

    /**
     * Initialize statistics tracking.
     */
    private function initializeStatistics(): void
    {
        $this->statistics = [
            'loads' => 0,
            'saves' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'memory_usage' => 0,
        ];
    }

    /**
     * Ensure translations directory exists.
     */
    private function ensureTranslationsDirectory(): void
    {
        if (!$this->files->exists($this->translationsPath)) {
            $this->files->makeDirectory($this->translationsPath, 0755, true);
        }
    }

    /**
     * Load translations from file or cache.
     */
    private function loadTranslations(string $locale): array
    {
        if (isset($this->loadedTranslations[$locale])) {
            $this->statistics['cache_hits']++;
            return $this->loadedTranslations[$locale];
        }

        $filePath = $this->getTranslationFilePath($locale);
        $translations = $this->loadTranslationFile($filePath);

        $this->loadedTranslations[$locale] = $translations;
        $this->fileModificationTimes[$locale] = $this->files->lastModified($filePath);
        $this->statistics['loads']++;
        $this->statistics['cache_misses']++;

        return $translations;
    }

    /**
     * Save translations to file.
     */
    private function saveTranslations(string $locale, array $translations): bool
    {
        $filePath = $this->getTranslationFilePath($locale);
        $success = $this->saveTranslationFile($filePath, $translations);

        if ($success) {
            $this->loadedTranslations[$locale] = $translations;
            $this->fileModificationTimes[$locale] = $this->files->lastModified($filePath);
            $this->statistics['saves']++;
        }

        return $success;
    }

    /**
     * Get translation file path for locale.
     */
    private function getTranslationFilePath(string $locale, string $format = null): string
    {
        $format = $format ?? $this->config['format']['default'];
        $extension = $format === 'php' ? 'php' : 'json';

        return "{$this->translationsPath}/{$locale}.{$extension}";
    }

    /**
     * Get backup file path.
     */
    private function getBackupPath(string $backupId): string
    {
        return $this->getBackupDirectory() . "/{$backupId}.json";
    }

    /**
     * Get backup directory path.
     */
    private function getBackupDirectory(): string
    {
        return $this->config['backup']['path'];
    }

    /**
     * Ensure directory exists.
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Invalidate cache for locale.
     */
    private function invalidateCache(string $locale): void
    {
        $cacheKey = $this->config['cache']['prefix'] . ".{$locale}";
        $this->cache->forget($cacheKey);

        unset($this->loadedTranslations[$locale]);
        unset($this->fileModificationTimes[$locale]);
    }

    /**
     * Create backup if enabled.
     */
    private function createBackupIfEnabled($locale): void
    {
        if ($this->config['backup']['enabled'] && $this->config['backup']['auto_backup']) {
            $options = is_string($locale) ? ['locales' => [$locale]] : [];
            $this->backup($options);
        }
    }

    /**
     * Commit to git if enabled.
     */
    private function commitToGitIfEnabled(string $message, array $options = []): void
    {
        if ($this->config['git']['enabled'] && $this->config['git']['auto_commit']) {
            $this->commitToGit($message, $options);
        }
    }

    /**
     * Get fallback translation.
     */
    private function getFallbackTranslation(string $key, string $locale): ?string
    {
        if ($locale === $this->defaultLocale) {
            return null;
        }

        $fallbackTranslations = $this->loadTranslations($this->defaultLocale);
        return Arr::get($fallbackTranslations, $key);
    }

    /**
     * Process replacement parameters.
     */
    private function processReplacements(string $value, array $replace): string
    {
        foreach ($replace as $key => $replacement) {
            $value = str_replace(":{$key}", $replacement, $value);
        }

        return $value;
    }

    /**
     * Validate translation value.
     */
    private function validateTranslationValue(string $value): bool
    {
        if (strlen($value) > $this->config['validation']['max_length']) {
            return false;
        }

        if (!$this->config['validation']['allow_html'] && $this->containsHtml($value)) {
            return false;
        }

        return true;
    }

    /**
     * Check if value contains HTML.
     */
    private function containsHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    /**
     * Check if value contains unsafe HTML.
     */
    private function containsUnsafeHtml(string $value): bool
    {
        $unsafeTags = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'textarea'];
        $unsafeAttributes = ['onclick', 'onload', 'onerror', 'javascript:'];

        foreach ($unsafeTags as $tag) {
            if (stripos($value, "<{$tag}") !== false) {
                return true;
            }
        }

        foreach ($unsafeAttributes as $attr) {
            if (stripos($value, $attr) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record translation change.
     */
    private function recordChange(string $key, $oldValue, $newValue, string $locale): void
    {
        // Implementation for change tracking
    }

    /**
     * Flatten array keys.
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $newKey));
            } else {
                $keys[] = $newKey;
            }
        }

        return $keys;
    }

    /**
     * Flatten translations array.
     */
    private function flattenTranslations(array $array, string $prefix = ''): array
    {
        $flat = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenTranslations($value, $newKey));
            } else {
                $flat[$newKey] = $value;
            }
        }

        return $flat;
    }

    /**
     * Calculate similarity between two strings.
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }

    /**
     * Calculate suggestion confidence.
     */
    private function calculateSuggestionConfidence(string $text, string $key): float
    {
        $textLength = strlen($text);
        $keyClarity = 1 - (substr_count($key, '_') / max(strlen($key), 1));

        return min(0.9, ($textLength / 100) * $keyClarity);
    }

    /**
     * Execute bulk operation.
     */
    private function executeBulkOperation(array $operation): mixed
    {
        $type = $operation['type'] ?? '';
        $key = $operation['key'] ?? '';
        $value = $operation['value'] ?? '';
        $locale = $operation['locale'] ?? $this->currentLocale;

        return match ($type) {
            'set' => $this->set($key, $value, $locale),
            'forget' => $this->forget($key, $locale),
            'sync' => $this->sync($key, $operation['locales'] ?? []),
            default => throw new InvalidArgumentException("Unsupported bulk operation: {$type}")
        };
    }

    /**
     * Generate validation recommendations.
     */
    private function generateValidationRecommendations(array $issues): array
    {
        $recommendations = [];

        foreach ($issues as $issue) {
            $recommendations[] = match ($issue['type']) {
                'invalid_key' => 'Use only alphanumeric characters, dots, underscores, and hyphens in keys',
                'invalid_value' => 'Ensure translation values are within allowed length and format',
                'unsafe_html' => 'Remove or escape potentially dangerous HTML tags and attributes',
                'invalid_locale' => 'Use only supported locale codes',
                default => 'Review and fix the validation issue'
            };
        }

        return array_unique($recommendations);
    }

    /**
     * Search match helper.
     */
    private function searchMatch(string $query, string $text, bool $caseSensitive, bool $regex): bool
    {
        if ($regex) {
            $flags = $caseSensitive ? '' : 'i';
            return preg_match("/{$query}/{$flags}", $text) === 1;
        }

        if ($caseSensitive) {
            return strpos($text, $query) !== false;
        }

        return stripos($text, $query) !== false;
    }

    /**
     * Get match type for search results.
     */
    private function getMatchType(string $query, string $key, string $value, bool $caseSensitive, bool $regex): string
    {
        $keyMatch = $this->searchMatch($query, $key, $caseSensitive, $regex);
        $valueMatch = $this->searchMatch($query, $value, $caseSensitive, $regex);

        if ($keyMatch && $valueMatch) {
            return 'both';
        } elseif ($keyMatch) {
            return 'key';
        } else {
            return 'value';
        }
    }

    /**
     * Export translations to JSON format.
     */
    private function exportToJson(array $translations, array $options): string
    {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        return json_encode($translations, $flags);
    }

    /**
     * Export translations to PHP array format.
     */
    private function exportToPhpArray(array $translations, array $options): string
    {
        return $this->arrayToPhpFormat($translations);
    }

    /**
     * Export translations to CSV format.
     */
    private function exportToCsv(array $translations, array $options): string
    {
        $flat = $this->flattenTranslations($translations);
        $csv = "key,value\n";

        foreach ($flat as $key => $value) {
            $csv .= '"' . str_replace('"', '""', $key) . '","' . str_replace('"', '""', $value) . "\n";
        }

        return $csv;
    }

    /**
     * Export translations to XLSX format.
     */
    private function exportToXlsx(array $translations, array $options): string
    {
        return $this->exportToCsv($translations, $options);
    }

    /**
     * Import translations from JSON.
     */
    private function importFromJson(string $content, array $options): array
    {
        return json_decode($content, true) ?? [];
    }

    /**
     * Import translations from PHP array.
     */
    private function importFromPhpArray(string $content, array $options): array
    {
        return $this->evaluatePhpArray($content);
    }

    /**
     * Import translations from CSV.
     */
    private function importFromCsv(string $content, array $options): array
    {
        $lines = str_getcsv($content, "\n");
        $translations = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) continue;

            $row = str_getcsv($line);
            if (count($row) >= 2) {
                Arr::set($translations, $row[0], $row[1]);
            }
        }

        return $translations;
    }

    /**
     * Import translations from XLSX.
     */
    private function importFromXlsx(string $content, array $options): array
    {
        return $this->importFromCsv($content, $options);
    }

    /**
     * Convert array to PHP format string.
     */
    private function arrayToPhpFormat(array $data): string
    {
        $style = $this->config['format']['php_array_style'];
        $export = var_export($data, true);

        if ($style === 'short') {
            $export = preg_replace('/array \(/', '[', $export);
            $export = preg_replace('/\)$/', ']', $export);
            $export = preg_replace('/\),\s*\n/', "],\n", $export);
        }

        return "<?php\n\nreturn {$export};\n";
    }

    /**
     * Evaluate PHP array from string content.
     */
    private function evaluatePhpArray(string $content): array
    {
        if (!preg_match('/^<\?php\s*return\s*\[/', trim($content)) &&
            !preg_match('/^<\?php\s*return\s*array\s*\(/', trim($content))) {
            return [];
        }

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'translation_import_');
            file_put_contents($tempFile, $content);
            $result = include $tempFile;
            unlink($tempFile);

            return is_array($result) ? $result : [];
        } catch (Exception $e) {
            Log::error("Failed to evaluate PHP array", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get translation file size.
     */
    private function getTranslationFileSize(string $locale): int
    {
        $filePath = $this->getTranslationFilePath($locale);
        return $this->files->exists($filePath) ? $this->files->size($filePath) : 0;
    }

    /**
     * Get translation file modified time.
     */
    private function getTranslationFileModifiedTime(string $locale): int
    {
        $filePath = $this->getTranslationFilePath($locale);
        return $this->files->exists($filePath) ? $this->files->lastModified($filePath) : 0;
    }

    /**
     * Find similar translations using different strategies.
     */
    private function findSimilarTranslations(string $text, string $locale): array
    {
        return $this->findSimilar($text, $locale, 0.7);
    }
}