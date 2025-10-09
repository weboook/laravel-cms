<?php

namespace Webook\LaravelCMS\Helpers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;

class MetadataHelper
{
    /**
     * Get metadata for the current page and locale
     */
    public static function getMetadata($pageUrl = null, $locale = null)
    {
        $pageUrl = $pageUrl ?? request()->path();
        $locale = $locale ?? App::getLocale();

        // Normalize URL
        $pageUrl = self::normalizeUrl($pageUrl);

        // Get metadata file path
        $metadataPath = self::getMetadataPath($pageUrl, $locale);

        if (!File::exists($metadataPath)) {
            return [
                'meta_title' => '',
                'meta_description' => '',
                'social_image' => ''
            ];
        }

        return json_decode(File::get($metadataPath), true);
    }

    /**
     * Render meta tags HTML
     */
    public static function renderMetaTags($pageUrl = null, $locale = null)
    {
        $metadata = self::getMetadata($pageUrl, $locale);
        $html = '';

        if (!empty($metadata['meta_title'])) {
            $html .= '<title>' . e($metadata['meta_title']) . '</title>' . "\n";
            $html .= '<meta property="og:title" content="' . e($metadata['meta_title']) . '">' . "\n";
            $html .= '<meta name="twitter:title" content="' . e($metadata['meta_title']) . '">' . "\n";
        }

        if (!empty($metadata['meta_description'])) {
            $html .= '<meta name="description" content="' . e($metadata['meta_description']) . '">' . "\n";
            $html .= '<meta property="og:description" content="' . e($metadata['meta_description']) . '">' . "\n";
            $html .= '<meta name="twitter:description" content="' . e($metadata['meta_description']) . '">' . "\n";
        }

        if (!empty($metadata['social_image'])) {
            $html .= '<meta property="og:image" content="' . e($metadata['social_image']) . '">' . "\n";
            $html .= '<meta name="twitter:image" content="' . e($metadata['social_image']) . '">' . "\n";
            $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        }

        return $html;
    }

    /**
     * Normalize URL for consistent storage
     */
    protected static function normalizeUrl($url)
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
    protected static function getMetadataPath($pageUrl, $locale)
    {
        $basePath = storage_path('cms/metadata');

        // Convert URL to safe filename
        $safeName = str_replace(['/', '\\'], '-', $pageUrl);

        return "{$basePath}/{$locale}/{$safeName}.json";
    }
}
