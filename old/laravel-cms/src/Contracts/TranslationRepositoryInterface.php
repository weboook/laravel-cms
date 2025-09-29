<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * Translation Repository Interface
 *
 * Defines the contract for managing translations in the Laravel CMS.
 * Provides comprehensive translation management including file format support,
 * advanced features like backup/restore, git integration, and bulk operations.
 */
interface TranslationRepositoryInterface
{
    /**
     * Get a translation value for a specific key and locale.
     *
     * @param string $key Translation key (supports dot notation)
     * @param string|null $locale Locale code (defaults to current locale)
     * @param array $replace Replacement parameters for placeholders
     * @return string The translated string or key if not found
     */
    public function get(string $key, string $locale = null, array $replace = []): string;

    /**
     * Set a translation value for a specific key and locale.
     *
     * @param string $key Translation key (supports dot notation)
     * @param string $value Translation value
     * @param string $locale Locale code
     * @return bool Success status
     */
    public function set(string $key, string $value, string $locale): bool;

    /**
     * Check if a translation key exists in a specific locale.
     *
     * @param string $key Translation key
     * @param string|null $locale Locale code (defaults to current locale)
     * @return bool True if translation exists
     */
    public function has(string $key, string $locale = null): bool;

    /**
     * Remove a translation key from a specific locale.
     *
     * @param string $key Translation key
     * @param string $locale Locale code
     * @return bool Success status
     */
    public function forget(string $key, string $locale): bool;

    /**
     * Get all translations for a specific locale.
     *
     * @param string|null $locale Locale code (defaults to current locale)
     * @return array All translations for the locale
     */
    public function all(string $locale = null): array;

    /**
     * Get missing translation keys for a specific locale.
     *
     * @param string|null $locale Locale code (defaults to current locale)
     * @param string|null $comparisonLocale Base locale for comparison (defaults to default locale)
     * @return array Missing translation keys
     */
    public function missing(string $locale = null, string $comparisonLocale = null): array;

    /**
     * Synchronize a translation key across multiple locales.
     *
     * @param string $key Translation key
     * @param array $locales Array of locale => value pairs
     * @return void
     */
    public function sync(string $key, array $locales): void;

    /**
     * Export translations for a specific locale in various formats.
     *
     * @param string $locale Locale code
     * @param string $format Export format (array, json, csv, xlsx)
     * @param array $options Export options
     * @return string Exported content
     */
    public function export(string $locale, string $format = 'array', array $options = []): string;

    /**
     * Import translations for a specific locale from various formats.
     *
     * @param string $locale Locale code
     * @param string $content Content to import
     * @param string $format Import format (array, json, csv, xlsx)
     * @param array $options Import options
     * @return bool Success status
     */
    public function import(string $locale, string $content, string $format, array $options = []): bool;

    /**
     * Get available locales.
     *
     * @return array List of available locale codes
     */
    public function getAvailableLocales(): array;

    /**
     * Get the current active locale.
     *
     * @return string Current locale code
     */
    public function getCurrentLocale(): string;

    /**
     * Set the current active locale.
     *
     * @param string $locale Locale code
     * @return void
     */
    public function setCurrentLocale(string $locale): void;

    /**
     * Create a backup of current translations.
     *
     * @param array $options Backup options (locales, format, etc.)
     * @return string Backup identifier
     */
    public function backup(array $options = []): string;

    /**
     * Restore translations from a backup.
     *
     * @param string $backupId Backup identifier
     * @param array $options Restore options
     * @return bool Success status
     */
    public function restore(string $backupId, array $options = []): bool;

    /**
     * Get list of available backups.
     *
     * @return array List of backup identifiers with metadata
     */
    public function getBackups(): array;

    /**
     * Commit translation changes to git (if enabled).
     *
     * @param string $message Commit message
     * @param array $options Commit options
     * @return bool Success status
     */
    public function commitToGit(string $message, array $options = []): bool;

    /**
     * Generate translation key suggestions based on content.
     *
     * @param string $content Content to analyze
     * @param string $locale Target locale
     * @return array Suggested translation keys
     */
    public function suggestKeys(string $content, string $locale): array;

    /**
     * Generate a translation key from English text.
     *
     * @param string $text English text
     * @param string $namespace Optional namespace
     * @return string Generated key
     */
    public function generateKey(string $text, string $namespace = ''): string;

    /**
     * Find similar translations in translation memory.
     *
     * @param string $text Text to find similar translations for
     * @param string $locale Target locale
     * @param float $threshold Similarity threshold (0.0 to 1.0)
     * @return array Similar translations
     */
    public function findSimilar(string $text, string $locale, float $threshold = 0.8): array;

    /**
     * Get translation diff between two versions.
     *
     * @param string $locale Locale code
     * @param string $fromVersion From version identifier
     * @param string $toVersion To version identifier
     * @return array Translation differences
     */
    public function getDiff(string $locale, string $fromVersion, string $toVersion): array;

    /**
     * Perform bulk operations on translations.
     *
     * @param array $operations Array of operations to perform
     * @param array $options Bulk operation options
     * @return array Results of bulk operations
     */
    public function bulk(array $operations, array $options = []): array;

    /**
     * Validate translation content for security and format.
     *
     * @param string $key Translation key
     * @param string $value Translation value
     * @param string $locale Locale code
     * @return array Validation results
     */
    public function validate(string $key, string $value, string $locale): array;

    /**
     * Get translation statistics for a locale.
     *
     * @param string|null $locale Locale code
     * @return array Translation statistics
     */
    public function getStatistics(string $locale = null): array;

    /**
     * Warm up the translation cache.
     *
     * @param array $locales Locales to warm up (all if empty)
     * @return bool Success status
     */
    public function warmUp(array $locales = []): bool;

    /**
     * Clear translation cache for specific locales.
     *
     * @param array $locales Locales to clear (all if empty)
     * @return bool Success status
     */
    public function clearCache(array $locales = []): bool;

    /**
     * Get file information for translation files.
     *
     * @param string $locale Locale code
     * @return array File information
     */
    public function getFileInfo(string $locale): array;

    /**
     * Migrate translation files from one format to another.
     *
     * @param string $fromFormat Source format
     * @param string $toFormat Target format
     * @param array $options Migration options
     * @return bool Success status
     */
    public function migrate(string $fromFormat, string $toFormat, array $options = []): bool;

    /**
     * Search translations by value or key pattern.
     *
     * @param string $query Search query
     * @param array $options Search options
     * @return array Search results
     */
    public function search(string $query, array $options = []): array;
}