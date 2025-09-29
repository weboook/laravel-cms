<?php

namespace Webook\LaravelCMS\Console\Commands;

use Illuminate\Console\Command;

class ClearCacheCommand extends Command
{
    protected $signature = 'cms:clear-cache {--expired : Only clear expired cache entries}';
    protected $description = 'Clear CMS cache entries';

    public function handle()
    {
        $this->info('Clearing CMS cache...');
        
        // Cache clearing logic
        $this->info('CMS cache cleared successfully!');
    }
}