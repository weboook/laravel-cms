<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Webook\LaravelCMS\Services\SettingsManager;

/**
 * CMS Settings Controller
 *
 * Handles the configuration and management of CMS settings including
 * route restrictions, user access controls, and system preferences.
 */
class SettingsController extends Controller
{
    protected $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * Display the CMS settings interface
     */
    public function index(): View
    {
        $settings = $this->settingsManager->all();

        return view('cms::settings.index', [
            'settings' => $settings,
            'title' => 'CMS Settings'
        ]);
    }

    /**
     * Update CMS settings
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'cms.enabled' => 'boolean',
            'cms.auto_inject' => 'boolean',
            'cms.debug_mode' => 'boolean',
            'cms.excluded_routes' => 'array',
            'cms.excluded_routes.*' => 'string',
            'cms.allowed_ips' => 'array',
            'cms.allowed_ips.*' => 'ip',
            'cms.blocked_ips' => 'array',
            'cms.blocked_ips.*' => 'ip',
            'cms.allowed_user_groups' => 'array',
            'cms.allowed_user_groups.*' => 'string',
            'assets.storage_path' => 'string',
            'assets.thumbnails_enabled' => 'boolean',
            'assets.optimization_enabled' => 'boolean',
            'assets.webp_generation' => 'boolean',
            'content.backup_on_save' => 'boolean',
            'content.version_history' => 'boolean',
            'content.max_versions' => 'integer|min:1|max:1000',
            'content.auto_save_interval' => 'integer|min:5000|max:300000'
        ]);

        $settings = $request->all();

        // Clean up array inputs (remove empty values)
        if (isset($settings['cms']['excluded_routes'])) {
            $settings['cms']['excluded_routes'] = array_filter($settings['cms']['excluded_routes']);
        }

        if (isset($settings['cms']['allowed_ips'])) {
            $settings['cms']['allowed_ips'] = array_filter($settings['cms']['allowed_ips']);
        }

        if (isset($settings['cms']['blocked_ips'])) {
            $settings['cms']['blocked_ips'] = array_filter($settings['cms']['blocked_ips']);
        }

        if (isset($settings['cms']['allowed_user_groups'])) {
            $settings['cms']['allowed_user_groups'] = array_filter($settings['cms']['allowed_user_groups']);
        }

        $success = $this->settingsManager->update($settings);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Settings updated successfully' : 'Failed to update settings',
                'settings' => $this->settingsManager->all()
            ]);
        }

        if ($success) {
            return redirect()->back()->with('success', 'Settings updated successfully');
        }

        return redirect()->back()->with('error', 'Failed to update settings');
    }

    /**
     * Reset settings to defaults
     */
    public function reset(): JsonResponse|RedirectResponse
    {
        $success = $this->settingsManager->resetToDefaults();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Settings reset to defaults' : 'Failed to reset settings',
                'settings' => $this->settingsManager->all()
            ]);
        }

        if ($success) {
            return redirect()->back()->with('success', 'Settings reset to defaults');
        }

        return redirect()->back()->with('error', 'Failed to reset settings');
    }

    /**
     * Get current settings as JSON
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->settingsManager->all()
        ]);
    }

    /**
     * Add excluded route
     */
    public function addExcludedRoute(Request $request): JsonResponse
    {
        $request->validate([
            'route' => 'required|string'
        ]);

        $success = $this->settingsManager->addExcludedRoute($request->route);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Route excluded successfully' : 'Failed to exclude route',
            'excluded_routes' => $this->settingsManager->getExcludedRoutes()
        ]);
    }

    /**
     * Remove excluded route
     */
    public function removeExcludedRoute(Request $request): JsonResponse
    {
        $request->validate([
            'route' => 'required|string'
        ]);

        $success = $this->settingsManager->removeExcludedRoute($request->route);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Route inclusion restored' : 'Failed to restore route',
            'excluded_routes' => $this->settingsManager->getExcludedRoutes()
        ]);
    }

    /**
     * Test route accessibility
     */
    public function testRoute(Request $request): JsonResponse
    {
        $request->validate([
            'route' => 'required|string'
        ]);

        $route = $request->route;
        $excludedRoutes = $this->settingsManager->getExcludedRoutes();
        $isExcluded = false;

        foreach ($excludedRoutes as $pattern) {
            if (fnmatch($pattern, $route)) {
                $isExcluded = true;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'route' => $route,
            'is_excluded' => $isExcluded,
            'cms_available' => !$isExcluded,
            'message' => $isExcluded
                ? 'CMS is not available on this route'
                : 'CMS is available on this route'
        ]);
    }

    /**
     * Get system information
     */
    public function systemInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'cms_version' => '1.0.0', // You can make this dynamic
                'storage_path' => storage_path(),
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'queue_driver' => config('queue.default'),
                'environment' => app()->environment(),
                'debug_mode' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale')
            ]
        ]);
    }
}