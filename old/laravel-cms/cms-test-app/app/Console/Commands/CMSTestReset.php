<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Exception;

class CMSTestReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:test-reset
                            {--fresh : Perform a fresh reset removing all data}
                            {--seed : Run database seeders after reset}
                            {--cache : Clear all caches}
                            {--files : Reset file permissions and clean uploads}
                            {--config : Reset configuration to defaults}
                            {--logs : Clear all log files}
                            {--all : Perform all reset operations}
                            {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset CMS test environment to a clean state';

    /**
     * Progress tracking
     */
    protected $totalSteps = 0;
    protected $currentStep = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        // Calculate total steps based on options
        $this->calculateSteps();

        // Confirm destructive operations
        if (!$this->confirmReset()) {
            $this->warn('Reset operation cancelled.');
            return self::FAILURE;
        }

        $startTime = microtime(true);

        try {
            $this->info('🚀 Starting CMS test environment reset...');
            $this->newLine();

            // Perform reset operations based on options
            if ($this->option('all') || $this->option('cache')) {
                $this->clearCaches();
            }

            if ($this->option('all') || $this->option('fresh')) {
                $this->resetDatabase();
            }

            if ($this->option('all') || $this->option('files')) {
                $this->resetFiles();
            }

            if ($this->option('all') || $this->option('config')) {
                $this->resetConfiguration();
            }

            if ($this->option('all') || $this->option('logs')) {
                $this->clearLogs();
            }

            if ($this->option('seed') || $this->option('all')) {
                $this->seedDatabase();
            }

            // Final steps
            $this->optimizeApplication();
            $this->validateEnvironment();

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info("✅ CMS test environment reset completed successfully!");
            $this->info("⏱️  Total time: {$duration} seconds");

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Reset failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Display command header with ASCII art
     */
    protected function displayHeader(): void
    {
        $this->newLine();
        $this->line('  ╔══════════════════════════════════════════════════════════════╗');
        $this->line('  ║                    CMS Test Reset Tool                       ║');
        $this->line('  ║              Laravel CMS Package Testing                     ║');
        $this->line('  ╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    /**
     * Calculate total steps for progress tracking
     */
    protected function calculateSteps(): void
    {
        $this->totalSteps = 0;

        if ($this->option('all') || $this->option('cache')) $this->totalSteps += 3;
        if ($this->option('all') || $this->option('fresh')) $this->totalSteps += 2;
        if ($this->option('all') || $this->option('files')) $this->totalSteps += 4;
        if ($this->option('all') || $this->option('config')) $this->totalSteps += 2;
        if ($this->option('all') || $this->option('logs')) $this->totalSteps += 2;
        if ($this->option('seed') || $this->option('all')) $this->totalSteps += 1;

        $this->totalSteps += 2; // optimize and validate
    }

    /**
     * Confirm destructive operations
     */
    protected function confirmReset(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $operations = [];
        if ($this->option('all') || $this->option('fresh')) $operations[] = 'Database reset';
        if ($this->option('all') || $this->option('cache')) $operations[] = 'Cache clearing';
        if ($this->option('all') || $this->option('files')) $operations[] = 'File cleanup';
        if ($this->option('all') || $this->option('config')) $operations[] = 'Configuration reset';
        if ($this->option('all') || $this->option('logs')) $operations[] = 'Log file clearing';

        if (empty($operations)) {
            $this->warn('No reset operations specified. Use --help to see available options.');
            return false;
        }

        $this->warn('The following operations will be performed:');
        foreach ($operations as $operation) {
            $this->line("  • {$operation}");
        }

        return $this->confirm('Do you want to continue?', false);
    }

    /**
     * Update progress bar
     */
    protected function updateProgress(string $message): void
    {
        $this->currentStep++;
        $percentage = round(($this->currentStep / $this->totalSteps) * 100);
        $this->line(sprintf('[%d/%d] %s (%d%%)', $this->currentStep, $this->totalSteps, $message, $percentage));
    }

    /**
     * Clear all caches
     */
    protected function clearCaches(): void
    {
        $this->updateProgress('Clearing application cache...');
        Artisan::call('cache:clear');

        $this->updateProgress('Clearing configuration cache...');
        Artisan::call('config:clear');

        $this->updateProgress('Clearing route cache...');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Clear additional caches
        if (Cache::getStore() instanceof \Illuminate\Cache\FileStore) {
            $cacheDir = storage_path('framework/cache');
            if (File::exists($cacheDir)) {
                File::cleanDirectory($cacheDir);
            }
        }

        $this->line('  ✓ All caches cleared successfully');
    }

    /**
     * Reset database
     */
    protected function resetDatabase(): void
    {
        $this->updateProgress('Resetting database...');

        try {
            // Drop all tables
            if (config('database.default') === 'sqlite') {
                $dbPath = database_path('database.sqlite');
                if (File::exists($dbPath)) {
                    File::delete($dbPath);
                    File::put($dbPath, '');
                }
            } else {
                Artisan::call('migrate:reset', ['--force' => true]);
            }

            $this->updateProgress('Running migrations...');
            Artisan::call('migrate', ['--force' => true]);

            $this->line('  ✓ Database reset completed');

        } catch (Exception $e) {
            $this->error("  ❌ Database reset failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reset files and permissions
     */
    protected function resetFiles(): void
    {
        $this->updateProgress('Cleaning storage directories...');

        // Clear storage directories
        $storageDirs = [
            'app/backups',
            'app/uploads',
            'app/exports',
            'app/imports',
            'framework/cache',
            'framework/sessions',
            'framework/views'
        ];

        foreach ($storageDirs as $dir) {
            $fullPath = storage_path($dir);
            if (File::exists($fullPath)) {
                File::cleanDirectory($fullPath);
            } else {
                File::makeDirectory($fullPath, 0755, true);
            }
        }

        $this->updateProgress('Cleaning public directories...');

        // Clear public directories
        $publicDirs = [
            'css',
            'js',
            'images/uploads'
        ];

        foreach ($publicDirs as $dir) {
            $fullPath = public_path($dir);
            if (File::exists($fullPath)) {
                File::cleanDirectory($fullPath);
            }
        }

        $this->updateProgress('Resetting file permissions...');

        // Reset permissions
        $this->resetPermissions();

        $this->updateProgress('Creating test directories...');

        // Recreate necessary directories
        $this->createTestDirectories();

        $this->line('  ✓ File system reset completed');
    }

    /**
     * Reset configuration files
     */
    protected function resetConfiguration(): void
    {
        $this->updateProgress('Resetting configuration...');

        // Reset test configuration to defaults
        $configFile = config_path('cms-test.php');
        if (File::exists($configFile)) {
            $defaultConfig = $this->getDefaultTestConfig();
            File::put($configFile, $defaultConfig);
        }

        $this->updateProgress('Clearing configuration cache...');
        Artisan::call('config:clear');

        $this->line('  ✓ Configuration reset completed');
    }

    /**
     * Clear log files
     */
    protected function clearLogs(): void
    {
        $this->updateProgress('Clearing log files...');

        $logDir = storage_path('logs');
        if (File::exists($logDir)) {
            $logFiles = File::files($logDir);
            foreach ($logFiles as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    File::delete($file);
                }
            }
        }

        $this->updateProgress('Clearing test reports...');

        // Clear test reports
        $reportDirs = [
            storage_path('test-reports'),
            base_path('tests/Browser/screenshots'),
            base_path('tests/coverage')
        ];

        foreach ($reportDirs as $dir) {
            if (File::exists($dir)) {
                File::cleanDirectory($dir);
            }
        }

        $this->line('  ✓ Log files cleared');
    }

    /**
     * Seed database with test data
     */
    protected function seedDatabase(): void
    {
        $this->updateProgress('Seeding test data...');

        try {
            Artisan::call('db:seed', [
                '--class' => 'CMSTestSeeder',
                '--force' => true
            ]);

            $this->line('  ✓ Test data seeded successfully');

        } catch (Exception $e) {
            $this->warn("  ⚠️  Seeding failed: " . $e->getMessage());
            $this->warn("  Continuing without test data...");
        }
    }

    /**
     * Optimize application
     */
    protected function optimizeApplication(): void
    {
        $this->updateProgress('Optimizing application...');

        if (app()->environment('production')) {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        }

        // Generate IDE helper files if available
        if (class_exists('Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider')) {
            try {
                Artisan::call('ide-helper:generate');
                Artisan::call('ide-helper:models', ['--nowrite' => true]);
            } catch (Exception $e) {
                // Ignore IDE helper failures
            }
        }

        $this->line('  ✓ Application optimized');
    }

    /**
     * Validate environment
     */
    protected function validateEnvironment(): void
    {
        $this->updateProgress('Validating environment...');

        $issues = [];

        // Check database connection
        try {
            DB::connection()->getPdo();
        } catch (Exception $e) {
            $issues[] = "Database connection failed: " . $e->getMessage();
        }

        // Check required directories
        $requiredDirs = [
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            public_path('storage')
        ];

        foreach ($requiredDirs as $dir) {
            if (!File::exists($dir)) {
                $issues[] = "Missing directory: {$dir}";
            } elseif (!File::isWritable($dir)) {
                $issues[] = "Directory not writable: {$dir}";
            }
        }

        // Check environment variables
        $requiredEnvVars = ['APP_KEY', 'APP_ENV'];
        foreach ($requiredEnvVars as $var) {
            if (empty(env($var))) {
                $issues[] = "Missing environment variable: {$var}";
            }
        }

        if (empty($issues)) {
            $this->line('  ✓ Environment validation passed');
        } else {
            $this->warn('  ⚠️  Environment validation found issues:');
            foreach ($issues as $issue) {
                $this->warn("    • {$issue}");
            }
        }
    }

    /**
     * Reset file permissions
     */
    protected function resetPermissions(): void
    {
        $directories = [
            storage_path(),
            bootstrap_path('cache'),
            public_path('storage')
        ];

        foreach ($directories as $dir) {
            if (File::exists($dir)) {
                // Set directory permissions to 755
                chmod($dir, 0755);

                // Recursively set permissions
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        chmod($item->getRealPath(), 0755);
                    } else {
                        chmod($item->getRealPath(), 0644);
                    }
                }
            }
        }
    }

    /**
     * Create test directories
     */
    protected function createTestDirectories(): void
    {
        $directories = [
            storage_path('app/backups'),
            storage_path('app/uploads'),
            storage_path('app/exports'),
            storage_path('app/imports'),
            storage_path('test-reports'),
            public_path('images/test'),
            public_path('css'),
            public_path('js'),
            base_path('tests/fixtures'),
            base_path('tests/Browser/screenshots'),
            base_path('tests/coverage')
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // Create .gitkeep for empty directories
            $gitkeepPath = $dir . '/.gitkeep';
            if (!File::exists($gitkeepPath)) {
                File::put($gitkeepPath, '');
            }
        }
    }

    /**
     * Get default test configuration
     */
    protected function getDefaultTestConfig(): string
    {
        return <<<'PHP'
<?php

return [
    'enabled' => true,
    'supported_locales' => ['en', 'es', 'fr'],
    'features' => [
        'analytics' => true,
        'auto_save' => true,
        'version_control' => true,
        'performance_monitoring' => true,
    ],
    'test_data' => [
        'users' => ['count' => 10],
        'content' => ['pages' => 5, 'posts' => 20],
    ],
];
PHP;
    }

    /**
     * Display summary of reset operations
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('📋 Reset Summary:');

        $operations = [
            'cache' => 'Cache cleared',
            'fresh' => 'Database reset',
            'files' => 'Files cleaned',
            'config' => 'Configuration reset',
            'logs' => 'Logs cleared',
            'seed' => 'Test data seeded'
        ];

        foreach ($operations as $option => $description) {
            if ($this->option('all') || $this->option($option)) {
                $this->line("  ✓ {$description}");
            }
        }

        $this->newLine();
        $this->info('🎉 Your CMS test environment is now ready!');
        $this->newLine();

        // Display helpful next steps
        $this->comment('Next steps:');
        $this->line('  • Run tests: php artisan test');
        $this->line('  • Start server: php artisan serve');
        $this->line('  • Visit test pages: http://localhost:8000/test/simple');
        $this->newLine();
    }
}