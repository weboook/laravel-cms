<?php

namespace Webook\LaravelCMS\Console\Commands;

use Illuminate\Console\Command;
use Webook\LaravelCMS\Contracts\BackupManagerInterface;

class RestoreCommand extends Command
{
    protected $signature = 'cms:restore {backup : Backup ID to restore from}';
    protected $description = 'Restore CMS content from backup';

    public function handle(BackupManagerInterface $backupManager)
    {
        $backupId = $this->argument('backup');
        
        if ($this->confirm("Are you sure you want to restore from backup {$backupId}?")) {
            $this->info('Restoring from backup...');
            
            if ($backupManager->restore($backupId)) {
                $this->info('Backup restored successfully!');
            } else {
                $this->error('Failed to restore backup.');
            }
        }
    }
}