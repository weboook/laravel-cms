<?php

namespace Webook\LaravelCMS\Console\Commands;

use Illuminate\Console\Command;

/**
 * CMS Install Command
 *
 * Handles initial installation of the CMS package
 */
class InstallCommand extends Command
{
    protected $signature = 'cms:install {--force : Force installation even if already installed}';
    protected $description = 'Install the Laravel CMS package';

    public function handle()
    {
        $this->info('Installing Laravel CMS...');

        // Publish configuration
        $this->call('vendor:publish', [
            '--provider' => 'Webook\\LaravelCMS\\CMSServiceProvider',
            '--tag' => 'cms-config',
            '--force' => $this->option('force')
        ]);

        // Publish assets
        $this->call('vendor:publish', [
            '--provider' => 'Webook\\LaravelCMS\\CMSServiceProvider',
            '--tag' => 'cms-assets',
            '--force' => $this->option('force')
        ]);

        // Run migrations
        $this->call('migrate');

        $this->info('Laravel CMS installed successfully!');
        $this->line('Run <comment>php artisan cms:setup</comment> to configure your CMS.');
    }
}