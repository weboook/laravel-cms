<?php

namespace Webook\LaravelCMS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Webook\LaravelCMS\Services\TranslationManager;

/**
 * Migration Command for Translation Files
 *
 * Console command to migrate translation files between different formats
 * (PHP arrays to JSON, JSON to PHP arrays, etc.)
 */
class MigrateTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:translations:migrate
                           {from : Source format (php|json)}
                           {to : Target format (php|json)}
                           {--locales=* : Specific locales to migrate (optional)}
                           {--remove-old : Remove old format files after migration}
                           {--backup : Create backup before migration}
                           {--force : Force migration without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate translation files from one format to another';

    protected TranslationManager $translationManager;
    protected Filesystem $files;

    /**
     * Create a new command instance.
     */
    public function __construct(TranslationManager $translationManager, Filesystem $files)
    {
        parent::__construct();
        $this->translationManager = $translationManager;
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromFormat = $this->argument('from');
        $toFormat = $this->argument('to');
        $locales = $this->option('locales');
        $removeOld = $this->option('remove-old');
        $backup = $this->option('backup');
        $force = $this->option('force');

        // Validate formats
        if (!in_array($fromFormat, ['php', 'json'])) {
            $this->error("Invalid source format: {$fromFormat}. Must be 'php' or 'json'.");
            return 1;
        }

        if (!in_array($toFormat, ['php', 'json'])) {
            $this->error("Invalid target format: {$toFormat}. Must be 'php' or 'json'.");
            return 1;
        }

        if ($fromFormat === $toFormat) {
            $this->error('Source and target formats cannot be the same.');
            return 1;
        }

        // Get available locales if not specified
        if (empty($locales)) {
            $locales = $this->getAvailableLocales($fromFormat);
        }

        if (empty($locales)) {
            $this->error("No translation files found for format: {$fromFormat}");
            return 1;
        }

        // Display migration summary
        $this->displayMigrationSummary($fromFormat, $toFormat, $locales, $removeOld, $backup);

        // Confirm migration
        if (!$force && !$this->confirm('Do you want to proceed with the migration?')) {
            $this->info('Migration cancelled.');
            return 0;
        }

        // Create backup if requested
        if ($backup) {
            $this->info('Creating backup...');
            try {
                $backupId = $this->translationManager->backup(['locales' => $locales]);
                $this->info("Backup created with ID: {$backupId}");
            } catch (\Exception $e) {
                $this->error("Failed to create backup: {$e->getMessage()}");
                return 1;
            }
        }

        // Perform migration
        $this->info('Starting migration...');
        $progressBar = $this->output->createProgressBar(count($locales));
        $progressBar->start();

        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($locales as $locale) {
            try {
                $this->migrateLocale($locale, $fromFormat, $toFormat, $removeOld);
                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Locale '{$locale}': {$e->getMessage()}";
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayMigrationResults($successful, $failed, $errors);

        // Clear translation cache
        $this->info('Clearing translation cache...');
        $this->translationManager->clearCache();

        return $failed === 0 ? 0 : 1;
    }

    /**
     * Get available locales for a specific format.
     */
    protected function getAvailableLocales(string $format): array
    {
        $availableLocales = $this->translationManager->getAvailableLocales();
        $existingLocales = [];

        foreach ($availableLocales as $locale) {
            $fileInfo = $this->translationManager->getFileInfo($locale);
            if ($fileInfo['exists'] && $fileInfo['format'] === $format) {
                $existingLocales[] = $locale;
            }
        }

        return $existingLocales;
    }

    /**
     * Display migration summary.
     */
    protected function displayMigrationSummary(string $from, string $to, array $locales, bool $removeOld, bool $backup): void
    {
        $this->info('Migration Summary:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Source Format', strtoupper($from)],
                ['Target Format', strtoupper($to)],
                ['Locales', implode(', ', $locales)],
                ['Remove Old Files', $removeOld ? 'Yes' : 'No'],
                ['Create Backup', $backup ? 'Yes' : 'No'],
            ]
        );
    }

    /**
     * Migrate a single locale.
     */
    protected function migrateLocale(string $locale, string $fromFormat, string $toFormat, bool $removeOld): void
    {
        // Load translations from source format
        $sourceFile = $this->getTranslationFilePath($locale, $fromFormat);

        if (!$this->files->exists($sourceFile)) {
            throw new \Exception("Source file not found: {$sourceFile}");
        }

        $translations = $this->loadTranslationsFromFile($sourceFile, $fromFormat);

        if (empty($translations)) {
            throw new \Exception("No translations found in source file");
        }

        // Save translations in target format
        $targetFile = $this->getTranslationFilePath($locale, $toFormat);
        $this->saveTranslationsToFile($targetFile, $translations, $toFormat);

        // Remove old file if requested
        if ($removeOld) {
            $this->files->delete($sourceFile);
        }
    }

    /**
     * Get translation file path for locale and format.
     */
    protected function getTranslationFilePath(string $locale, string $format): string
    {
        $extension = $format === 'php' ? 'php' : 'json';
        $translationsPath = resource_path('lang');

        return "{$translationsPath}/{$locale}.{$extension}";
    }

    /**
     * Load translations from file.
     */
    protected function loadTranslationsFromFile(string $filePath, string $format): array
    {
        $content = $this->files->get($filePath);

        return match ($format) {
            'json' => json_decode($content, true) ?? [],
            'php' => $this->evaluatePhpArray($content),
            default => []
        };
    }

    /**
     * Save translations to file.
     */
    protected function saveTranslationsToFile(string $filePath, array $translations, string $format): void
    {
        $content = match ($format) {
            'json' => json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'php' => $this->arrayToPhpFormat($translations),
            default => throw new \Exception("Unsupported format: {$format}")
        };

        // Ensure directory exists
        $directory = dirname($filePath);
        if (!$this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($filePath, $content);
    }

    /**
     * Evaluate PHP array from string content.
     */
    protected function evaluatePhpArray(string $content): array
    {
        // Security check: Only evaluate if content looks like a safe PHP array return
        if (!preg_match('/^<\?php\s*return\s*\[/', trim($content)) &&
            !preg_match('/^<\?php\s*return\s*array\s*\(/', trim($content))) {
            return [];
        }

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'translation_migration_');
            file_put_contents($tempFile, $content);
            $result = include $tempFile;
            unlink($tempFile);

            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            throw new \Exception("Failed to evaluate PHP array: {$e->getMessage()}");
        }
    }

    /**
     * Convert array to PHP format string.
     */
    protected function arrayToPhpFormat(array $data): string
    {
        $export = var_export($data, true);

        // Use short array syntax
        $export = preg_replace('/array \(/', '[', $export);
        $export = preg_replace('/\)$/', ']', $export);
        $export = preg_replace('/\),\s*\n/', "],\n", $export);

        return "<?php\n\nreturn {$export};\n";
    }

    /**
     * Display migration results.
     */
    protected function displayMigrationResults(int $successful, int $failed, array $errors): void
    {
        if ($successful > 0) {
            $this->info("Successfully migrated {$successful} locale(s).");
        }

        if ($failed > 0) {
            $this->error("Failed to migrate {$failed} locale(s):");
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        if ($successful === 0 && $failed === 0) {
            $this->warn('No locales were processed.');
        }
    }
}