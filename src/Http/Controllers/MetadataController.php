<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use Webook\LaravelCMS\Services\CMSLogger;

class MetadataController extends Controller
{
    protected $logger;

    public function __construct(CMSLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get metadata for a specific page and locale
     */
    public function getMetadata(Request $request)
    {
        $validated = $request->validate([
            'page_url' => 'required|string',
            'locale' => 'nullable|string'
        ]);

        $pageUrl = $this->normalizeUrl($validated['page_url']);
        $locale = $validated['locale'] ?? App::getLocale();

        $metadataPath = $this->getMetadataPath($pageUrl, $locale);

        if (!File::exists($metadataPath)) {
            // Return default empty metadata
            return response()->json([
                'success' => true,
                'metadata' => [
                    'meta_title' => '',
                    'meta_description' => '',
                    'social_image' => ''
                ],
                'locale' => $locale,
                'page_url' => $pageUrl
            ]);
        }

        $metadata = json_decode(File::get($metadataPath), true);

        return response()->json([
            'success' => true,
            'metadata' => $metadata,
            'locale' => $locale,
            'page_url' => $pageUrl
        ]);
    }

    /**
     * Save metadata for a specific page and locale
     */
    public function saveMetadata(Request $request)
    {
        $validated = $request->validate([
            'page_url' => 'required|string',
            'locale' => 'required|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'social_image' => 'nullable|string'
        ]);

        $pageUrl = $this->normalizeUrl($validated['page_url']);
        $locale = $validated['locale'];

        $metadata = [
            'meta_title' => $validated['meta_title'] ?? '',
            'meta_description' => $validated['meta_description'] ?? '',
            'social_image' => $validated['social_image'] ?? '',
            'updated_at' => now()->toIso8601String()
        ];

        $metadataPath = $this->getMetadataPath($pageUrl, $locale);
        $metadataDir = dirname($metadataPath);

        // Ensure directory exists
        if (!File::exists($metadataDir)) {
            File::makeDirectory($metadataDir, 0755, true);
        }

        // Create backup before saving
        if (File::exists($metadataPath)) {
            $this->createBackup($metadataPath, $locale);
        }

        // Save metadata
        File::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Log the change
        $this->logger->info('Page metadata updated', [
            'page_url' => $pageUrl,
            'locale' => $locale,
            'file' => $metadataPath
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Metadata saved successfully',
            'metadata' => $metadata
        ]);
    }

    /**
     * Get all available locales from the translation system
     */
    public function getAvailableLocales()
    {
        $langPath = resource_path('lang');
        $locales = ['en']; // Default

        if (File::exists($langPath)) {
            $directories = File::directories($langPath);
            $locales = array_map(fn($dir) => basename($dir), $directories);

            // Also check for JSON translation files
            $jsonFiles = File::glob($langPath . '/*.json');
            foreach ($jsonFiles as $file) {
                $locale = basename($file, '.json');
                if (!in_array($locale, $locales)) {
                    $locales[] = $locale;
                }
            }
        }

        return response()->json([
            'success' => true,
            'locales' => $locales,
            'current' => App::getLocale()
        ]);
    }

    /**
     * Normalize URL for consistent storage
     */
    protected function normalizeUrl($url)
    {
        // Remove protocol and domain
        $url = parse_url($url, PHP_URL_PATH) ?? '/';

        // Remove trailing slash except for root
        if ($url !== '/' && str_ends_with($url, '/')) {
            $url = rtrim($url, '/');
        }

        // Remove leading slash for storage
        $url = ltrim($url, '/');

        // Use 'home' for root/empty path
        if (empty($url)) {
            $url = 'home';
        }

        return $url;
    }

    /**
     * Get metadata file path for a page and locale
     */
    protected function getMetadataPath($pageUrl, $locale)
    {
        $basePath = storage_path('cms/metadata');

        // Convert URL to safe filename
        $safeName = str_replace(['/', '\\'], '-', $pageUrl);

        return "{$basePath}/{$locale}/{$safeName}.json";
    }

    /**
     * Create backup of metadata file
     */
    protected function createBackup($filePath, $locale)
    {
        $backupPath = storage_path('cms/backups/metadata/' . $locale);

        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = basename($filePath, '.json');
        $backupFile = $backupPath . '/' . $timestamp . '_' . $filename . '.json';

        File::copy($filePath, $backupFile);

        $this->logger->logBackup($filePath, $backupFile);
    }
}
