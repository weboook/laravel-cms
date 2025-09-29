<?php

namespace Webook\LaravelCMS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class InstallCmsCommand extends Command
{
    protected $signature = 'cms:install
                            {--force : Overwrite existing files}
                            {--no-migrate : Skip running migrations}
                            {--no-seed : Skip running seeders}
                            {--no-npm : Skip npm installation}
                            {--no-build : Skip building assets}';

    protected $description = 'Install Laravel CMS with all components';

    protected $progressBar;

    public function handle()
    {
        $this->info('🚀 Installing Laravel CMS...');
        $this->newLine();

        $steps = [
            'Publishing configuration files',
            'Publishing assets and views',
            'Creating storage directories',
            'Running database migrations',
            'Seeding default data',
            'Installing npm dependencies',
            'Building frontend assets',
            'Setting up environment variables',
            'Creating symbolic links',
            'Setting file permissions',
            'Validating installation',
        ];

        if ($this->option('no-migrate')) {
            $steps = array_filter($steps, fn($step) => $step !== 'Running database migrations');
        }

        if ($this->option('no-seed')) {
            $steps = array_filter($steps, fn($step) => $step !== 'Seeding default data');
        }

        if ($this->option('no-npm')) {
            $steps = array_filter($steps, fn($step) => !in_array($step, ['Installing npm dependencies', 'Building frontend assets']));
        }

        if ($this->option('no-build')) {
            $steps = array_filter($steps, fn($step) => $step !== 'Building frontend assets');
        }

        $this->progressBar = $this->output->createProgressBar(count($steps));
        $this->progressBar->setFormat('verbose');

        try {
            $this->publishConfiguration();
            $this->progressBar->advance();

            $this->publishAssets();
            $this->progressBar->advance();

            $this->createStorageDirectories();
            $this->progressBar->advance();

            if (!$this->option('no-migrate')) {
                $this->runMigrations();
                $this->progressBar->advance();
            }

            if (!$this->option('no-seed')) {
                $this->seedData();
                $this->progressBar->advance();
            }

            if (!$this->option('no-npm')) {
                $this->installNpmDependencies();
                $this->progressBar->advance();

                if (!$this->option('no-build')) {
                    $this->buildAssets();
                    $this->progressBar->advance();
                }
            }

            $this->setupEnvironment();
            $this->progressBar->advance();

            $this->createSymlinks();
            $this->progressBar->advance();

            $this->setPermissions();
            $this->progressBar->advance();

            $this->validateInstallation();
            $this->progressBar->advance();

            $this->progressBar->finish();
            $this->newLine(2);

            $this->displaySuccessMessage();

        } catch (\Exception $e) {
            $this->progressBar->finish();
            $this->newLine(2);
            $this->error('❌ Installation failed: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    protected function publishConfiguration()
    {
        $this->line('📋 Publishing configuration files...');

        $force = $this->option('force') ? '--force' : '';

        // Publish main CMS config
        Artisan::call('vendor:publish', [
            '--provider' => 'Webook\LaravelCMS\CMSServiceProvider',
            '--tag' => 'cms-config',
            '--force' => $this->option('force'),
        ]);

        // Publish asset config
        Artisan::call('vendor:publish', [
            '--provider' => 'Webook\LaravelCMS\CMSServiceProvider',
            '--tag' => 'cms-assets-config',
            '--force' => $this->option('force'),
        ]);

        // Publish database config
        Artisan::call('vendor:publish', [
            '--provider' => 'Webook\LaravelCMS\CMSServiceProvider',
            '--tag' => 'cms-database-config',
            '--force' => $this->option('force'),
        ]);

        $this->line('   ✅ Configuration files published');
    }

    protected function publishAssets()
    {
        $this->line('📦 Publishing assets and views...');

        // Publish views
        Artisan::call('vendor:publish', [
            '--provider' => 'Webook\LaravelCMS\CMSServiceProvider',
            '--tag' => 'cms-views',
            '--force' => $this->option('force'),
        ]);

        // Publish JavaScript assets
        Artisan::call('vendor:publish', [
            '--provider' => 'Webook\LaravelCMS\CMSServiceProvider',
            '--tag' => 'cms-assets',
            '--force' => $this->option('force'),
        ]);

        // Publish CSS assets
        Artisan::call('vendor:publish', [
            '--provider' => 'Webook\LaravelCMS\CMSServiceProvider',
            '--tag' => 'cms-styles',
            '--force' => $this->option('force'),
        ]);

        $this->line('   ✅ Assets and views published');
    }

    protected function createStorageDirectories()
    {
        $this->line('📁 Creating storage directories...');

        $directories = [
            storage_path('app/cms'),
            storage_path('app/cms/assets'),
            storage_path('app/cms/temp'),
            storage_path('app/cms/backups'),
            storage_path('app/cms/cache'),
            storage_path('app/cms/logs'),
            storage_path('app/public/cms-assets'),
            storage_path('app/public/cms-assets/thumbnails'),
            storage_path('app/public/cms-assets/originals'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->line("   📁 Created {$directory}");
            }
        }

        $this->line('   ✅ Storage directories created');
    }

    protected function runMigrations()
    {
        $this->line('🗄️  Running database migrations...');

        Artisan::call('migrate', [
            '--path' => 'vendor/webook/laravel-cms/database/migrations',
            '--force' => true,
        ]);

        $this->line('   ✅ Database migrations completed');
    }

    protected function seedData()
    {
        $this->line('🌱 Seeding default data...');

        if (class_exists('Webook\LaravelCMS\Database\Seeders\CMSSeeder')) {
            Artisan::call('db:seed', [
                '--class' => 'Webook\LaravelCMS\Database\Seeders\CMSSeeder',
                '--force' => true,
            ]);
        }

        $this->line('   ✅ Default data seeded');
    }

    protected function installNpmDependencies()
    {
        $this->line('📦 Installing npm dependencies...');

        $packageJsonPath = base_path('package.json');
        $packageJson = json_decode(File::get($packageJsonPath), true);

        $dependencies = [
            'alpinejs' => '^3.13.0',
            'axios' => '^1.5.0',
            'sortablejs' => '^1.15.0',
            'cropperjs' => '^1.6.0',
            'trix' => '^2.0.0',
            'file-drop-element' => '^0.2.0',
        ];

        $devDependencies = [
            '@tailwindcss/forms' => '^0.5.6',
            '@tailwindcss/typography' => '^0.5.10',
            'tailwindcss' => '^3.3.0',
            'autoprefixer' => '^10.4.16',
            'postcss' => '^8.4.31',
        ];

        $packageJson['dependencies'] = array_merge($packageJson['dependencies'] ?? [], $dependencies);
        $packageJson['devDependencies'] = array_merge($packageJson['devDependencies'] ?? [], $devDependencies);

        File::put($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($this->confirm('Run npm install now?', true)) {
            $this->line('   Running npm install...');
            exec('npm install', $output, $returnCode);

            if ($returnCode === 0) {
                $this->line('   ✅ npm dependencies installed');
            } else {
                $this->warn('   ⚠️  npm install completed with warnings');
            }
        } else {
            $this->line('   ⏭️  Skipped npm install (run manually later)');
        }
    }

    protected function buildAssets()
    {
        $this->line('🔨 Building frontend assets...');

        if (File::exists(base_path('vite.config.js'))) {
            $this->line('   Using Vite...');
            exec('npm run build', $output, $returnCode);
        } elseif (File::exists(base_path('webpack.mix.js'))) {
            $this->line('   Using Laravel Mix...');
            exec('npm run prod', $output, $returnCode);
        } else {
            $this->warn('   ⚠️  No build tool detected, skipping asset build');
            return;
        }

        if ($returnCode === 0) {
            $this->line('   ✅ Assets built successfully');
        } else {
            $this->warn('   ⚠️  Asset build completed with warnings');
        }
    }

    protected function setupEnvironment()
    {
        $this->line('⚙️  Setting up environment variables...');

        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (!File::exists($envPath)) {
            $this->error('   ❌ .env file not found');
            return;
        }

        $envContent = File::get($envPath);
        $newVars = [
            'CMS_ENABLED=true',
            'CMS_AUTH_REQUIRED=true',
            'CMS_AUTH_GUARD=web',
            'CMS_ROUTE_PREFIX=cms',
            'CMS_ASSETS_DISK=public',
            'CMS_ASSETS_PATH=cms-assets',
            'CMS_UPLOAD_MAX_SIZE=10485760',
            'CMS_GENERATE_THUMBNAILS=true',
            'CMS_IMAGE_QUALITY=90',
            'CMS_DB_DETECTION_ENABLED=true',
            'CMS_DB_AUTO_SAVE=true',
            'CMS_DB_AUTO_SAVE_INTERVAL=30',
            'CMS_DB_VERSIONING=true',
            'CMS_DB_CACHE_ENABLED=true',
        ];

        foreach ($newVars as $var) {
            [$key, $value] = explode('=', $var, 2);
            if (!Str::contains($envContent, $key . '=')) {
                $envContent .= "\n" . $var;
            }
        }

        File::put($envPath, $envContent);

        // Update .env.example if it exists
        if (File::exists($envExamplePath)) {
            $envExampleContent = File::get($envExamplePath);
            foreach ($newVars as $var) {
                [$key, $value] = explode('=', $var, 2);
                if (!Str::contains($envExampleContent, $key . '=')) {
                    $envExampleContent .= "\n" . $var;
                }
            }
            File::put($envExamplePath, $envExampleContent);
        }

        $this->line('   ✅ Environment variables configured');
    }

    protected function createSymlinks()
    {
        $this->line('🔗 Creating symbolic links...');

        if (!File::exists(public_path('storage'))) {
            Artisan::call('storage:link');
            $this->line('   ✅ Storage link created');
        } else {
            $this->line('   ⏭️  Storage link already exists');
        }
    }

    protected function setPermissions()
    {
        $this->line('🔐 Setting file permissions...');

        $directories = [
            storage_path('app/cms'),
            storage_path('app/public/cms-assets'),
            bootstrap_path('cache'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($directories as $directory) {
            if (File::exists($directory)) {
                chmod($directory, 0755);
            }
        }

        $this->line('   ✅ File permissions set');
    }

    protected function validateInstallation()
    {
        $this->line('✅ Validating installation...');

        $checks = [
            'Configuration files' => $this->checkConfigFiles(),
            'Storage directories' => $this->checkStorageDirectories(),
            'Database tables' => $this->checkDatabaseTables(),
            'Asset publishing' => $this->checkAssets(),
            'Environment variables' => $this->checkEnvironment(),
        ];

        $allPassed = true;
        foreach ($checks as $check => $passed) {
            if ($passed) {
                $this->line("   ✅ {$check}");
            } else {
                $this->line("   ❌ {$check}");
                $allPassed = false;
            }
        }

        if (!$allPassed) {
            throw new \Exception('Installation validation failed');
        }

        $this->line('   ✅ All validation checks passed');
    }

    protected function checkConfigFiles(): bool
    {
        return File::exists(config_path('cms.php')) &&
               File::exists(config_path('cms-assets.php')) &&
               File::exists(config_path('cms-database.php'));
    }

    protected function checkStorageDirectories(): bool
    {
        $directories = [
            storage_path('app/cms'),
            storage_path('app/public/cms-assets'),
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                return false;
            }
        }

        return true;
    }

    protected function checkDatabaseTables(): bool
    {
        try {
            return \Schema::hasTable('cms_assets') && \Schema::hasTable('cms_asset_folders');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function checkAssets(): bool
    {
        return File::exists(resource_path('js/cms-asset-library.js')) &&
               File::exists(resource_path('css/cms-asset-library.css'));
    }

    protected function checkEnvironment(): bool
    {
        $envContent = File::get(base_path('.env'));
        return Str::contains($envContent, 'CMS_ENABLED=');
    }

    protected function displaySuccessMessage()
    {
        $this->info('🎉 Laravel CMS installation completed successfully!');
        $this->newLine();

        $this->line('📖 <fg=yellow>Next Steps:</fg=yellow>');
        $this->line('   1. Configure your models in config/cms-database.php');
        $this->line('   2. Set up user permissions for CMS access');
        $this->line('   3. Configure asset storage in config/cms-assets.php');
        $this->line('   4. Add @cms directives to your Blade templates');
        $this->line('   5. Visit /cms to access the CMS interface');
        $this->newLine();

        $this->line('📚 <fg=yellow>Documentation:</fg=yellow>');
        $this->line('   • Configuration: /docs/configuration.md');
        $this->line('   • Usage Guide: /docs/usage.md');
        $this->line('   • API Reference: /docs/api.md');
        $this->newLine();

        $this->line('🐛 <fg=yellow>Troubleshooting:</fg=yellow>');
        $this->line('   • Clear cache: php artisan cache:clear');
        $this->line('   • Rebuild assets: npm run build');
        $this->line('   • Check logs: storage/logs/laravel.log');
        $this->newLine();

        $this->info('Happy editing! 🚀');
    }
}