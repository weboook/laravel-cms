<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

/**
 * Settings Manager for Laravel CMS
 *
 * Handles storage and retrieval of CMS configuration settings
 * including route restrictions, user permissions, and access controls.
 */
class SettingsManager
{
    protected $configFile = 'cms-settings.json';
    protected $cachePrefix = 'cms_settings_';
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Get a setting value
     */
    public function get(string $key, $default = null)
    {
        $cacheKey = $this->cachePrefix . str_replace('.', '_', $key);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($key, $default) {
            $settings = $this->loadSettings();
            return data_get($settings, $key, $default);
        });
    }

    /**
     * Set a setting value
     */
    public function set(string $key, $value): bool
    {
        $settings = $this->loadSettings();
        data_set($settings, $key, $value);

        $success = $this->saveSettings($settings);

        if ($success) {
            // Clear cache for this key
            $cacheKey = $this->cachePrefix . str_replace('.', '_', $key);
            Cache::forget($cacheKey);
        }

        return $success;
    }

    /**
     * Get all settings
     */
    public function all(): array
    {
        return $this->loadSettings();
    }

    /**
     * Update multiple settings at once
     */
    public function update(array $settings): bool
    {
        $currentSettings = $this->loadSettings();

        foreach ($settings as $key => $value) {
            data_set($currentSettings, $key, $value);
        }

        $success = $this->saveSettings($currentSettings);

        if ($success) {
            // Clear all cached settings
            $this->clearCache();
        }

        return $success;
    }

    /**
     * Delete a setting
     */
    public function forget(string $key): bool
    {
        $settings = $this->loadSettings();

        if (data_get($settings, $key) !== null) {
            data_forget($settings, $key);

            $success = $this->saveSettings($settings);

            if ($success) {
                $cacheKey = $this->cachePrefix . str_replace('.', '_', $key);
                Cache::forget($cacheKey);
            }

            return $success;
        }

        return true;
    }

    /**
     * Clear all settings cache
     */
    public function clearCache(): void
    {
        $settings = $this->loadSettings();
        $this->clearCacheRecursive($settings);
    }

    /**
     * Get default settings
     */
    public function getDefaults(): array
    {
        return [
            'cms' => [
                'enabled' => true,
                'excluded_routes' => [
                    'admin/*',
                    'api/*',
                    'cms/*',
                    'login',
                    'logout',
                    'register',
                    'password/*'
                ],
                'allowed_ips' => [],
                'blocked_ips' => [],
                'allowed_user_groups' => [
                    'admin',
                    'editor',
                    'content-manager'
                ],
                'auto_inject' => true,
                'debug_mode' => false,
                'backup_enabled' => true,
                'max_file_size' => 10485760, // 10MB
                'allowed_file_types' => [
                    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
                    'video' => ['mp4', 'webm', 'ogg'],
                    'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf']
                ]
            ],
            'assets' => [
                'storage_path' => 'cms/assets',
                'thumbnails_enabled' => true,
                'thumbnail_sizes' => [
                    'small' => [150, 150],
                    'medium' => [300, 300],
                    'large' => [600, 600]
                ],
                'optimization_enabled' => true,
                'webp_generation' => true
            ],
            'content' => [
                'backup_on_save' => true,
                'version_history' => true,
                'max_versions' => 50,
                'auto_save_interval' => 30000 // 30 seconds
            ]
        ];
    }

    /**
     * Reset settings to defaults
     */
    public function resetToDefaults(): bool
    {
        $success = $this->saveSettings($this->getDefaults());

        if ($success) {
            $this->clearCache();
        }

        return $success;
    }

    /**
     * Load settings from storage
     */
    protected function loadSettings(): array
    {
        try {
            if (Storage::exists($this->configFile)) {
                $content = Storage::get($this->configFile);
                $settings = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return array_merge_recursive($this->getDefaults(), $settings);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to load CMS settings: ' . $e->getMessage());
        }

        return $this->getDefaults();
    }

    /**
     * Save settings to storage
     */
    protected function saveSettings(array $settings): bool
    {
        try {
            $content = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return Storage::put($this->configFile, $content);
        } catch (\Exception $e) {
            \Log::error('Failed to save CMS settings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Recursively clear cache for nested settings
     */
    protected function clearCacheRecursive(array $settings, string $prefix = ''): void
    {
        foreach ($settings as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            $cacheKey = $this->cachePrefix . str_replace('.', '_', $fullKey);
            Cache::forget($cacheKey);

            if (is_array($value)) {
                $this->clearCacheRecursive($value, $fullKey);
            }
        }
    }

    /**
     * Check if CMS is enabled
     */
    public function isEnabled(): bool
    {
        return $this->get('cms.enabled', true);
    }

    /**
     * Check if auto-injection is enabled
     */
    public function isAutoInjectEnabled(): bool
    {
        return $this->get('cms.auto_inject', true);
    }

    /**
     * Get excluded routes
     */
    public function getExcludedRoutes(): array
    {
        return $this->get('cms.excluded_routes', []);
    }

    /**
     * Add excluded route
     */
    public function addExcludedRoute(string $route): bool
    {
        $routes = $this->getExcludedRoutes();

        if (!in_array($route, $routes)) {
            $routes[] = $route;
            return $this->set('cms.excluded_routes', $routes);
        }

        return true;
    }

    /**
     * Remove excluded route
     */
    public function removeExcludedRoute(string $route): bool
    {
        $routes = $this->getExcludedRoutes();
        $key = array_search($route, $routes);

        if ($key !== false) {
            unset($routes[$key]);
            return $this->set('cms.excluded_routes', array_values($routes));
        }

        return true;
    }

    /**
     * Get allowed user groups
     */
    public function getAllowedUserGroups(): array
    {
        return $this->get('cms.allowed_user_groups', []);
    }

    /**
     * Get allowed IPs
     */
    public function getAllowedIPs(): array
    {
        return $this->get('cms.allowed_ips', []);
    }

    /**
     * Get blocked IPs
     */
    public function getBlockedIPs(): array
    {
        return $this->get('cms.blocked_ips', []);
    }
}