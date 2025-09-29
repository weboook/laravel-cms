<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class SettingsController
{
    protected $settingsFile;

    public function __construct()
    {
        $this->settingsFile = storage_path('cms/settings.json');
    }

    /**
     * Get exclusion settings
     */
    public function getExclusions(): JsonResponse
    {
        $settings = $this->loadSettings();

        return response()->json([
            'prefixes' => $settings['exclusions']['prefixes'] ?? config('cms.exclusions.prefixes', []),
            'routes' => $settings['exclusions']['routes'] ?? config('cms.exclusions.routes', []),
            'names' => $settings['exclusions']['names'] ?? config('cms.exclusions.names', []),
        ]);
    }

    /**
     * Save all settings
     */
    public function save(Request $request): JsonResponse
    {
        try {
            $settings = $this->loadSettings();

            // Update general settings
            if ($request->has('enabled')) {
                $settings['enabled'] = (bool) $request->input('enabled');
            }

            // Update toolbar settings
            if ($request->has('toolbar')) {
                $settings['toolbar'] = array_merge(
                    $settings['toolbar'] ?? [],
                    $request->input('toolbar', [])
                );
            }

            // Update exclusion settings
            if ($request->has('exclusions')) {
                $settings['exclusions'] = [
                    'prefixes' => $request->input('exclusions.prefixes', []),
                    'routes' => $request->input('exclusions.routes', []),
                    'names' => $request->input('exclusions.names', []),
                ];
            }

            $this->saveSettings($settings);

            // Clear any cached views
            if (function_exists('artisan')) {
                \Artisan::call('view:clear');
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load settings from storage
     */
    protected function loadSettings(): array
    {
        if (!File::exists($this->settingsFile)) {
            // Create directory if it doesn't exist
            $dir = dirname($this->settingsFile);
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // Return default settings
            return [
                'enabled' => true,
                'toolbar' => [
                    'enabled' => true,
                    'position' => 'bottom',
                    'theme' => 'dark'
                ],
                'exclusions' => [
                    'prefixes' => config('cms.exclusions.prefixes', []),
                    'routes' => config('cms.exclusions.routes', []),
                    'names' => config('cms.exclusions.names', []),
                ]
            ];
        }

        $content = File::get($this->settingsFile);
        return json_decode($content, true) ?? [];
    }

    /**
     * Save settings to storage
     */
    protected function saveSettings(array $settings): void
    {
        File::put($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    }

    /**
     * Get all settings (for general settings endpoint)
     */
    public function index(): JsonResponse
    {
        $settings = $this->loadSettings();

        return response()->json($settings);
    }
}