<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;

class ToolbarController extends Controller
{
    /**
     * Get all available pages/routes
     */
    public function getPages()
    {
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            // Filter out vendor, api, and cms routes
            $uri = $route->uri();
            $methods = $route->methods();

            return in_array('GET', $methods)
                && !str_starts_with($uri, 'api/')
                && !str_starts_with($uri, 'cms/')
                && !str_starts_with($uri, '_')
                && !str_contains($uri, '{') // Exclude routes with parameters for now
                && $uri !== 'sanctum/csrf-cookie'
                && $uri !== 'livewire/message';
        })->map(function ($route) {
            $uri = $route->uri();
            return [
                'name' => $route->getName() ?: $uri,
                'path' => '/' . ltrim($uri, '/'),
                'uri' => $uri,
                'title' => $this->getPageTitle($route),
                'is_template' => false,
                'parameters' => []
            ];
        })->values();

        // Check for template routes (routes with parameters)
        $templateRoutes = collect(Route::getRoutes())->filter(function ($route) {
            $uri = $route->uri();
            $methods = $route->methods();

            return in_array('GET', $methods)
                && !str_starts_with($uri, 'api/')
                && !str_starts_with($uri, 'cms/')
                && !str_starts_with($uri, '_')
                && str_contains($uri, '{')
                && !str_contains($uri, 'sanctum')
                && !str_contains($uri, 'livewire');
        })->map(function ($route) {
            $uri = $route->uri();
            preg_match_all('/\{([^}]+)\}/', $uri, $matches);

            return [
                'name' => $route->getName() ?: $uri,
                'path' => '/' . ltrim($uri, '/'),
                'uri' => $uri,
                'title' => $this->getPageTitle($route),
                'is_template' => true,
                'parameters' => $matches[1] ?? [],
                'sample_items' => $this->getSampleItems($route)
            ];
        })->values();

        return response()->json([
            'pages' => $routes,
            'templates' => $templateRoutes,
            'current_path' => request()->path(),
            'current_url' => request()->url()
        ]);
    }

    /**
     * Get available languages/locales
     */
    public function getLanguages()
    {
        $currentLocale = App::getLocale();
        $availableLocales = [];

        // Check if locale configuration exists
        $locales = config('app.locales', []);

        if (empty($locales)) {
            // Try to detect from lang directory
            $langPath = resource_path('lang');
            if (File::exists($langPath)) {
                $directories = File::directories($langPath);
                foreach ($directories as $dir) {
                    $locale = basename($dir);
                    $availableLocales[] = [
                        'code' => $locale,
                        'name' => $this->getLocaleName($locale),
                        'native_name' => $this->getLocaleNativeName($locale),
                        'active' => $locale === $currentLocale
                    ];
                }
            }
        } else {
            foreach ($locales as $code => $name) {
                $availableLocales[] = [
                    'code' => is_numeric($code) ? $name : $code,
                    'name' => is_numeric($code) ? $this->getLocaleName($name) : $name,
                    'native_name' => $this->getLocaleNativeName(is_numeric($code) ? $name : $code),
                    'active' => (is_numeric($code) ? $name : $code) === $currentLocale
                ];
            }
        }

        // If no locales found, return default
        if (empty($availableLocales)) {
            $availableLocales[] = [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'active' => true
            ];
        }

        return response()->json([
            'current' => $currentLocale,
            'available' => $availableLocales,
            'multilingual' => count($availableLocales) > 1
        ]);
    }

    /**
     * Get CMS settings
     */
    public function getSettings()
    {
        return response()->json([
            'enabled' => config('cms.enabled', true),
            'toolbar' => config('cms.toolbar', []),
            'editor' => config('cms.editor', []),
            'media' => config('cms.media', []),
            'cache' => config('cms.cache', [])
        ]);
    }

    /**
     * Update CMS settings
     */
    public function updateSettings(Request $request)
    {
        // This would typically update configuration or database settings
        // For now, return success
        return response()->json(['success' => true]);
    }

    /**
     * Get template items (e.g., blog posts, products)
     */
    public function getTemplateItems(Request $request)
    {
        $routeName = $request->input('route');
        $model = $request->input('model');

        // This is a placeholder - implement based on your models
        $items = [];

        // Example for blog posts
        if (str_contains($routeName, 'blog') || str_contains($routeName, 'post')) {
            // Would fetch from Post model
            $items = [
                ['id' => 1, 'title' => 'Sample Blog Post 1', 'slug' => 'sample-post-1'],
                ['id' => 2, 'title' => 'Sample Blog Post 2', 'slug' => 'sample-post-2'],
            ];
        }

        return response()->json(['items' => $items]);
    }

    private function getPageTitle($route)
    {
        $name = $route->getName();
        $uri = $route->uri();

        if ($name) {
            return ucwords(str_replace(['.', '-', '_'], ' ', $name));
        }

        if ($uri === '/' || $uri === '') {
            return 'Home';
        }

        return ucwords(str_replace(['-', '_', '/'], ' ', $uri));
    }

    private function getSampleItems($route)
    {
        // This would be customized based on your application
        $uri = $route->uri();

        if (str_contains($uri, 'blog') || str_contains($uri, 'post')) {
            return [
                ['id' => 1, 'title' => 'Sample Blog Post 1', 'value' => 'sample-post-1'],
                ['id' => 2, 'title' => 'Sample Blog Post 2', 'value' => 'sample-post-2'],
            ];
        }

        if (str_contains($uri, 'product')) {
            return [
                ['id' => 1, 'title' => 'Product 1', 'value' => 'product-1'],
                ['id' => 2, 'title' => 'Product 2', 'value' => 'product-2'],
            ];
        }

        return [];
    }

    private function getLocaleName($code)
    {
        $names = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ar' => 'Arabic',
        ];

        return $names[$code] ?? ucfirst($code);
    }

    private function getLocaleNativeName($code)
    {
        $names = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ru' => 'Русский',
            'zh' => '中文',
            'ja' => '日本語',
            'ar' => 'العربية',
        ];

        return $names[$code] ?? ucfirst($code);
    }
}