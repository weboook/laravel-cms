<?php

namespace Webook\LaravelCMS\Console\Commands;

use Illuminate\Console\Command;
use Webook\LaravelCMS\Contracts\BackupManagerInterface;

class BackupCommand extends Command
{
    protected $signature = 'cms:backup {--cleanup : Clean up old backups} {--days=30 : Days to keep backups}';
    protected $description = 'Create a backup of CMS content';

    public function handle(BackupManagerInterface $backupManager)
    {
        $this->info('Creating CMS backup...');
        
        $backupId = $backupManager->create([
            resource_path('views'),
            app_path(),
        ]);
        
        $this->info("Backup created with ID: {$backupId}");
        
        if ($this->option('cleanup')) {
            $this->info('Cleaning up old backups...');
            // Cleanup logic
        }
    }
}