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
        // Get excluded routes and paths from configuration AND saved settings
        $excludedRoutes = config('cms.auth.excluded_routes', []);
        $excludedPaths = config('cms.auth.excluded_paths', []);

        // Also get exclusions from saved settings file
        $settingsFile = storage_path('cms/settings.json');
        if (File::exists($settingsFile)) {
            $settings = json_decode(File::get($settingsFile), true);
            if (isset($settings['exclusions'])) {
                // Merge prefixes as paths (e.g., "admin" becomes "admin/*")
                if (!empty($settings['exclusions']['prefixes'])) {
                    foreach ($settings['exclusions']['prefixes'] as $prefix) {
                        $excludedPaths[] = $prefix . '/*';
                    }
                }
                // Merge route patterns
                if (!empty($settings['exclusions']['routes'])) {
                    $excludedPaths = array_merge($excludedPaths, $settings['exclusions']['routes']);
                }
                // Merge route names
                if (!empty($settings['exclusions']['names'])) {
                    $excludedRoutes = array_merge($excludedRoutes, $settings['exclusions']['names']);
                }
            }
        }

        // Remove duplicates
        $excludedRoutes = array_unique($excludedRoutes);
        $excludedPaths = array_unique($excludedPaths);

        $routes = collect(Route::getRoutes())->filter(function ($route) use ($excludedRoutes, $excludedPaths) {
            // Filter out vendor, api, and cms routes
            $uri = $route->uri();
            $methods = $route->methods();
            $routeName = $route->getName();

            // Check if route name is excluded
            if ($routeName) {
                foreach ($excludedRoutes as $pattern) {
                    if ($this->matchesPattern($routeName, $pattern)) {
                        return false;
                    }
                }
            }

            // Check if path is excluded
            foreach ($excludedPaths as $pattern) {
                if ($this->matchesPathPattern($uri, $pattern)) {
                    return false;
                }
            }

            // Only include routes that are GET/HEAD only (actual page routes)
            $allowedMethods = array_diff($methods, ['GET', 'HEAD']);
            $hasOnlyGetHead = empty($allowedMethods);

            // Check if route has parameters - but treat {locale} as non-template
            $hasNonLocaleParams = false;
            if (str_contains($uri, '{')) {
                // Extract all parameters
                preg_match_all('/\{([^}]+)\}/', $uri, $matches);
                $params = $matches[1] ?? [];
                // Filter out locale-related parameters
                $nonLocaleParams = array_filter($params, function($param) {
                    return !in_array($param, ['locale', 'lang', 'language']);
                });
                $hasNonLocaleParams = !empty($nonLocaleParams);
            }

            return $hasOnlyGetHead
                && !str_starts_with($uri, 'api/')
                && !str_starts_with($uri, 'cms/')
                && !str_starts_with($uri, '_')
                && !$hasNonLocaleParams // Only exclude routes with non-locale parameters
                && $uri !== 'sanctum/csrf-cookie'
                && $uri !== 'livewire/message'
                && !str_starts_with($uri, 'livewire/')
                && !str_starts_with($uri, 'log-viewer/')
                && !str_starts_with($uri, 'telescope/')
                && !str_starts_with($uri, 'horizon/');
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

        // Check for template routes (routes with non-locale parameters)
        $templateRoutes = collect(Route::getRoutes())->filter(function ($route) use ($excludedRoutes, $excludedPaths) {
            $uri = $route->uri();
            $methods = $route->methods();
            $routeName = $route->getName();

            // Check if route name is excluded
            if ($routeName) {
                foreach ($excludedRoutes as $pattern) {
                    if ($this->matchesPattern($routeName, $pattern)) {
                        return false;
                    }
                }
            }

            // Check if path is excluded (check base path without parameters)
            $basePath = preg_replace('/\{[^}]+\}/', '*', $uri);
            foreach ($excludedPaths as $pattern) {
                if ($this->matchesPathPattern($basePath, $pattern)) {
                    return false;
                }
            }

            // Only include routes that are GET/HEAD only (actual page routes)
            $allowedMethods = array_diff($methods, ['GET', 'HEAD']);
            $hasOnlyGetHead = empty($allowedMethods);

            // Check if route has non-locale parameters (template pages)
            $hasNonLocaleParams = false;
            if (str_contains($uri, '{')) {
                preg_match_all('/\{([^}]+)\}/', $uri, $matches);
                $params = $matches[1] ?? [];
                // Filter out locale-related parameters
                $nonLocaleParams = array_filter($params, function($param) {
                    return !in_array($param, ['locale', 'lang', 'language']);
                });
                $hasNonLocaleParams = !empty($nonLocaleParams);
            }

            return $hasOnlyGetHead
                && !str_starts_with($uri, 'api/')
                && !str_starts_with($uri, 'cms/')
                && !str_starts_with($uri, '_')
                && $hasNonLocaleParams // ONLY include routes with non-locale parameters
                && !str_contains($uri, 'sanctum')
                && !str_contains($uri, 'livewire')
                && !str_starts_with($uri, 'log-viewer/')
                && !str_starts_with($uri, 'telescope/')
                && !str_starts_with($uri, 'horizon/');
        })->map(function ($route) {
            $uri = $route->uri();
            preg_match_all('/\{([^}]+)\}/', $uri, $matches);
            $params = $matches[1] ?? [];
            // Filter out locale parameters from the list
            $nonLocaleParams = array_filter($params, function($param) {
                return !in_array($param, ['locale', 'lang', 'language']);
            });

            return [
                'name' => $route->getName() ?: $uri,
                'path' => '/' . ltrim($uri, '/'),
                'uri' => $uri,
                'title' => $this->getPageTitle($route),
                'is_template' => true,
                'parameters' => array_values($nonLocaleParams), // Only show non-locale parameters
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
     * Inspect a route/URL and return its view file
     */
    public function inspectRoute(Request $request)
    {
        $url = $request->input('url');

        if (!$url) {
            return response()->json([
                'success' => false,
                'error' => 'URL is required'
            ], 400);
        }

        // Parse the URL to get the path
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        $path = trim($path, '/');

        // Try to find the matching route
        try {
            $routes = Route::getRoutes();

            foreach ($routes as $route) {
                $routeUri = trim($route->uri(), '/');

                // Check for exact match OR parametric match
                $isMatch = false;

                // Exact match
                if ($routeUri === $path || ($path === '' && $routeUri === '/')) {
                    $isMatch = true;
                }

                // Parametric match (e.g., {locale}/about matches en/about)
                if (!$isMatch && str_contains($routeUri, '{')) {
                    $pattern = preg_replace('/\{[^\}]+\}/', '([^/]+)', $routeUri);
                    $pattern = '/^' . str_replace('/', '\\/', $pattern) . '$/';
                    if (preg_match($pattern, $path)) {
                        $isMatch = true;
                    }
                }

                if ($isMatch) {
                    $action = $route->getAction();

                    // Handle Livewire components
                    if (isset($action['controller']) && str_contains($action['controller'], 'Livewire')) {
                        $livewireClass = is_string($action['controller']) ?
                            explode('@', $action['controller'])[0] : $action['controller'];

                        $viewPath = $this->getLivewireViewPath($livewireClass);
                        if ($viewPath) {
                            $relativePath = str_replace(base_path() . '/', '', $viewPath);
                            return response()->json([
                                'success' => true,
                                'file_path' => $relativePath,
                                'route_uri' => $routeUri,
                                'route_name' => $route->getName(),
                                'is_livewire' => true
                            ]);
                        }
                    }

                    $action = $route->getAction();

                    // Check if it's a Route::view()
                    if (isset($action['view'])) {
                        $viewName = $action['view'];
                        $filePath = $this->viewNameToFilePath($viewName);

                        if ($filePath) {
                            // Return relative path from base_path
                            $relativePath = str_replace(base_path() . '/', '', $filePath);

                            return response()->json([
                                'success' => true,
                                'file_path' => $relativePath,
                                'view_name' => $viewName,
                                'route_uri' => $routeUri,
                                'route_name' => $route->getName()
                            ]);
                        }
                    }

                    // Check if it uses a controller
                    if (isset($action['controller'])) {
                        // Try to infer view from controller method
                        $viewPath = $this->inferViewFromController($action['controller'], $route);

                        if ($viewPath) {
                            $relativePath = str_replace(base_path() . '/', '', $viewPath);

                            return response()->json([
                                'success' => true,
                                'file_path' => $relativePath,
                                'route_uri' => $routeUri,
                                'route_name' => $route->getName(),
                                'inferred' => true
                            ]);
                        }
                    }

                    // If we found the route but couldn't determine the view
                    return response()->json([
                        'success' => false,
                        'error' => 'Route found but could not determine view file',
                        'route_uri' => $routeUri,
                        'route_name' => $route->getName()
                    ], 400);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'No matching route found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to inspect route: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert Laravel view name to file path
     */
    protected function viewNameToFilePath($viewName)
    {
        // Convert view name like 'pages.about' to 'pages/about.blade.php'
        $relativePath = str_replace('.', '/', $viewName) . '.blade.php';
        $filePath = resource_path('views/' . $relativePath);

        if (file_exists($filePath)) {
            return $filePath;
        }

        return null;
    }

    /**
     * Get Livewire component view path
     */
    protected function getLivewireViewPath($class)
    {
        try {
            if (!class_exists($class)) {
                return null;
            }

            // Instantiate the Livewire component
            $component = app($class);

            // Get the view name from the render method
            if (method_exists($component, 'render')) {
                $view = $component->render();
                if ($view && method_exists($view, 'name')) {
                    $viewName = $view->name();
                    return $this->viewNameToFilePath($viewName);
                }
            }

            // Fallback: Try to guess the view path from class name
            // App\Livewire\Public\AboutPage -> livewire.public.about-page
            $classPath = str_replace('App\\Livewire\\', '', $class);
            $classPath = str_replace('\\', '.', $classPath);
            $classPath = 'livewire.' . strtolower($classPath);
            $classPath = preg_replace('/([a-z])([A-Z])/', '$1-$2', $classPath);
            $classPath = strtolower($classPath);

            return $this->viewNameToFilePath($classPath);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Try to infer view from controller action
     */
    protected function inferViewFromController($controller, $route)
    {
        // Extract controller class and method
        if (is_string($controller)) {
            [$class, $method] = explode('@', $controller);
        } else {
            return null;
        }

        // Common patterns for view names based on controller
        $patterns = [];

        // Get route URI for pattern matching
        $uri = $route->uri();
        $segments = explode('/', trim($uri, '/'));

        // Pattern 1: Direct match with route URI (e.g., 'pages/about' -> 'pages.about')
        if (!empty($uri) && $uri !== '/') {
            $patterns[] = str_replace('/', '.', trim($uri, '/'));
        }

        // Pattern 2: Based on controller name
        // e.g., PagesController -> 'pages'
        if (preg_match('/(\w+)Controller$/', class_basename($class), $matches)) {
            $controllerBase = strtolower($matches[1]);

            // Pattern 2a: controller.method (e.g., 'pages.about')
            if ($method && $method !== 'index') {
                $patterns[] = $controllerBase . '.' . $method;
            }

            // Pattern 2b: just method (e.g., 'about')
            if ($method && $method !== 'index') {
                $patterns[] = $method;
            }

            // Pattern 2c: controller/method (e.g., 'pages/about')
            if ($method && $method !== 'index') {
                $patterns[] = $controllerBase . '/' . $method;
            }
        }

        // Pattern 3: Based on last segment of URI
        if (count($segments) > 0) {
            $lastSegment = end($segments);
            if ($lastSegment) {
                $patterns[] = $lastSegment;
            }
        }

        // Try each pattern
        foreach ($patterns as $pattern) {
            $viewPath = $this->viewNameToFilePath($pattern);
            if ($viewPath) {
                return $viewPath;
            }
        }

        return null;
    }

    /**
     * Get available languages/locales
     */
    public function getLanguages()
    {
        $currentLocale = App::getLocale();
        $availableLocales = [];
        $hasTranslationFiles = false;

        // Check if locale configuration exists
        $locales = config('app.locales', []);

        // Try to detect from lang directory
        // Try Laravel 11+ path first (base_path/lang), then fallback to Laravel 10 (resources/lang)
        $langPath = function_exists('lang_path') ? lang_path() : base_path('lang');
        if (!File::exists($langPath)) {
            $langPath = resource_path('lang');
        }

        if (File::exists($langPath)) {
            $hasTranslationFiles = true;

            if (empty($locales)) {
                // Get directories (each represents a locale)
                $directories = File::directories($langPath);
                foreach ($directories as $dir) {
                    $locale = basename($dir);
                    $availableLocales[] = [
                        'code' => $locale,
                        'name' => $this->getLocaleName($locale),
                        'native_name' => $this->getLocaleNativeName($locale),
                        'active' => $locale === $currentLocale,
                        'has_files' => true
                    ];
                }

                // Also check for JSON translation files
                $jsonFiles = File::glob($langPath . '/*.json');
                foreach ($jsonFiles as $file) {
                    $locale = basename($file, '.json');
                    // Check if already added
                    $exists = false;
                    foreach ($availableLocales as $existing) {
                        if ($existing['code'] === $locale) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $availableLocales[] = [
                            'code' => $locale,
                            'name' => $this->getLocaleName($locale),
                            'native_name' => $this->getLocaleNativeName($locale),
                            'active' => $locale === $currentLocale,
                            'has_files' => true
                        ];
                    }
                }
            } else {
                foreach ($locales as $code => $name) {
                    $localeCode = is_numeric($code) ? $name : $code;
                    $availableLocales[] = [
                        'code' => $localeCode,
                        'name' => is_numeric($code) ? $this->getLocaleName($name) : $name,
                        'native_name' => $this->getLocaleNativeName($localeCode),
                        'active' => $localeCode === $currentLocale,
                        'has_files' => File::exists($langPath . '/' . $localeCode)
                    ];
                }
            }
        } else if (!empty($locales)) {
            // Use configured locales even if lang directory doesn't exist
            foreach ($locales as $code => $name) {
                $localeCode = is_numeric($code) ? $name : $code;
                $availableLocales[] = [
                    'code' => $localeCode,
                    'name' => is_numeric($code) ? $this->getLocaleName($name) : $name,
                    'native_name' => $this->getLocaleNativeName($localeCode),
                    'active' => $localeCode === $currentLocale,
                    'has_files' => false
                ];
            }
        }

        // If no locales found, return default
        if (empty($availableLocales)) {
            $availableLocales[] = [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'active' => true,
                'has_files' => false
            ];
        }

        return response()->json([
            'current' => $currentLocale,
            'available' => $availableLocales,
            'multilingual' => count($availableLocales) > 1,
            'has_translation_system' => $hasTranslationFiles
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

    /**
     * Check if a route name matches a pattern (supports wildcards)
     */
    private function matchesPattern($routeName, $pattern)
    {
        // Convert wildcard pattern to regex
        $pattern = str_replace('.', '\.', $pattern);
        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '/^' . $pattern . '$/';

        return preg_match($pattern, $routeName);
    }

    /**
     * Check if a path matches a pattern (supports wildcards)
     */
    private function matchesPathPattern($path, $pattern)
    {
        // Remove leading slash from both for comparison
        $path = ltrim($path, '/');
        $pattern = ltrim($pattern, '/');

        // Handle wildcard patterns
        if (str_contains($pattern, '*')) {
            // Convert wildcard pattern to regex
            $pattern = str_replace('/', '\/', $pattern);
            $pattern = str_replace('*', '.*', $pattern);
            $pattern = '/^' . $pattern . '$/';

            return preg_match($pattern, $path);
        }

        // Exact match or prefix match
        return $path === $pattern || str_starts_with($path, $pattern . '/');
    }
}