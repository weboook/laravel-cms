<?php

namespace Webook\LaravelCMS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use DOMDocument;
use DOMXPath;

class InjectCmsIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:inject-ids {path?} {--dry-run : Preview changes without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inject data-cms-id attributes into Blade templates for images and links';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $path = $this->argument('path') ?: resource_path('views');
        $dryRun = $this->option('dry-run');

        if (!File::exists($path)) {
            $this->error("Path does not exist: {$path}");
            return 1;
        }

        $this->info($dryRun ? 'DRY RUN - No files will be modified' : 'Injecting CMS IDs into Blade templates...');
        $this->line('');

        if (File::isDirectory($path)) {
            $this->processDirectory($path, $dryRun);
        } else {
            $this->processFile($path, $dryRun);
        }

        $this->line('');
        $this->info('Process completed!');

        return 0;
    }

    /**
     * Process all Blade files in a directory
     */
    protected function processDirectory($path, $dryRun)
    {
        $files = File::glob($path . '/**/*.blade.php');

        if (empty($files)) {
            $this->warn("No Blade files found in: {$path}");
            return;
        }

        $this->info("Found " . count($files) . " Blade files to process");
        $this->line('');

        foreach ($files as $file) {
            $this->processFile($file, $dryRun);
        }
    }

    /**
     * Process a single Blade file
     */
    protected function processFile($file, $dryRun)
    {
        $relativePath = str_replace(base_path() . '/', '', $file);
        $this->line("Processing: {$relativePath}");

        $content = File::get($file);
        $originalContent = $content;

        // Skip if file contains Blade loops (likely database content)
        if ($this->containsDatabaseDirectives($content)) {
            $this->line("  ⚠️  Skipping - contains database directives");
            return;
        }

        $modifiedContent = $this->injectIds($content);

        if ($modifiedContent === $originalContent) {
            $this->line("  ✓ No changes needed");
            return;
        }

        if ($dryRun) {
            $this->info("  🔍 Would inject IDs (dry run)");
            $this->showChanges($originalContent, $modifiedContent);
        } else {
            File::put($file, $modifiedContent);
            $this->info("  ✅ IDs injected successfully");
        }
    }

    /**
     * Check if content contains database directives
     */
    protected function containsDatabaseDirectives($content)
    {
        $patterns = [
            '/@foreach/',
            '/@forelse/',
            '/@for/',
            '/@while/',
            '/\$loop->/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inject CMS IDs into content
     */
    protected function injectIds($content)
    {
        // Process images
        $content = $this->injectImageIds($content);

        // Process links
        $content = $this->injectLinkIds($content);

        return $content;
    }

    /**
     * Inject IDs into img tags
     */
    protected function injectImageIds($content)
    {
        // Find all img tags without data-cms-id
        $pattern = '/<img(?![^>]*data-cms-id)([^>]*)>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $imgTag = $matches[0];
            $attributes = $matches[1];

            // Skip if it's within a loop or has Blade syntax
            if (strpos($imgTag, '{{') !== false || strpos($imgTag, '@') !== false) {
                return $imgTag;
            }

            // Skip if it has data-cms-component (database content)
            if (strpos($imgTag, 'data-cms-component') !== false) {
                return $imgTag;
            }

            // Extract src for ID generation
            $src = '';
            if (preg_match('/src=["\']([^"\']+)["\']/', $imgTag, $srcMatch)) {
                $src = $srcMatch[1];
            }

            // Generate stable ID based on src
            $filename = basename(parse_url($src, PHP_URL_PATH));
            $filename = pathinfo($filename, PATHINFO_FILENAME);
            $cmsId = 'img-' . substr(md5($filename . $src), 0, 16);

            // Insert data-cms-id after <img
            return '<img data-cms-id="' . $cmsId . '"' . $attributes . '>';
        }, $content);
    }

    /**
     * Inject IDs into anchor tags
     */
    protected function injectLinkIds($content)
    {
        // Find all <a> tags without data-cms-id
        $pattern = '/<a(?![^>]*data-cms-id)([^>]*)>(.*?)<\/a>/is';

        return preg_replace_callback($pattern, function ($matches) {
            $fullTag = $matches[0];
            $attributes = $matches[1];
            $linkContent = $matches[2];

            // Skip if it's within a loop or has Blade syntax
            if (strpos($fullTag, '{{') !== false || strpos($fullTag, '@') !== false) {
                return $fullTag;
            }

            // Skip if it has data-cms-component (database content)
            if (strpos($fullTag, 'data-cms-component') !== false) {
                return $fullTag;
            }

            // Skip if it contains nested HTML (complex content)
            if (preg_match('/<[^>]+>/', $linkContent)) {
                // Contains HTML tags inside, might be complex
                if (!preg_match('/^<(i|span|strong|em|b)[^>]*>.*<\/\1>$/', $linkContent)) {
                    // Not a simple icon or span, skip
                    return $fullTag;
                }
            }

            // Extract href for ID generation
            $href = '';
            if (preg_match('/href=["\']([^"\']+)["\']/', $attributes, $hrefMatch)) {
                $href = $hrefMatch[1];
            }

            // Generate stable ID
            $text = strip_tags($linkContent);
            $text = substr(trim($text), 0, 20);
            $cmsId = 'link-' . substr(md5($href . $text), 0, 16);

            // Insert data-cms-id
            return '<a data-cms-id="' . $cmsId . '"' . $attributes . '>' . $linkContent . '</a>';
        }, $content);
    }

    /**
     * Show changes in a diff-like format
     */
    protected function showChanges($original, $modified)
    {
        // Simple diff display - show lines with img or <a tags
        $originalLines = explode("\n", $original);
        $modifiedLines = explode("\n", $modified);

        for ($i = 0; $i < count($originalLines); $i++) {
            if (isset($modifiedLines[$i]) && $originalLines[$i] !== $modifiedLines[$i]) {
                if (strpos($modifiedLines[$i], '<img') !== false || strpos($modifiedLines[$i], '<a') !== false) {
                    $this->line("  Line " . ($i + 1) . ":");
                    $this->line("    - " . trim($originalLines[$i]), 'comment');
                    $this->line("    + " . trim($modifiedLines[$i]), 'info');
                }
            }
        }
    }
}