<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class InjectToolbar
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!config('cms.enabled', true) || !config('cms.toolbar.enabled', true)) {
            return $response;
        }

        if (!config('cms.toolbar.auto_inject', true)) {
            return $response;
        }

        // Check if current route should be excluded
        if ($this->shouldExclude($request)) {
            return $response;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        $content = $response->getContent();

        if ($content && strpos($content, '</body>') !== false) {
            $toolbar = View::make('cms::toolbar', [
                'position' => config('cms.toolbar.position', 'bottom'),
                'theme' => config('cms.toolbar.theme', 'dark'),
            ])->render();
            $content = str_replace('</body>', $toolbar . '</body>', $content);
            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Check if the current request should be excluded from CMS
     */
    protected function shouldExclude(Request $request)
    {
        // Load runtime settings
        $settingsFile = storage_path('cms/settings.json');
        $runtimeExclusions = [];
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            $runtimeExclusions = $settings['exclusions'] ?? [];
        }

        // Merge config and runtime exclusions
        $excludedRoutes = array_unique(array_merge(
            config('cms.exclusions.routes', []),
            $runtimeExclusions['routes'] ?? []
        ));

        $excludedPrefixes = array_unique(array_merge(
            config('cms.exclusions.prefixes', []),
            $runtimeExclusions['prefixes'] ?? []
        ));

        $excludedNames = array_unique(array_merge(
            config('cms.exclusions.names', []),
            $runtimeExclusions['names'] ?? []
        ));

        // Check excluded routes (pattern matching)
        foreach ($excludedRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        // Check excluded prefixes
        $path = $request->path();
        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
                return true;
            }
        }

        // Check excluded route names
        $routeName = $request->route()?->getName();
        if ($routeName) {
            if (in_array($routeName, $excludedNames)) {
                return true;
            }
        }

        // Check excluded middleware groups
        $route = $request->route();
        if ($route) {
            $excludedMiddlewares = config('cms.exclusions.middlewares', []);
            $routeMiddlewares = $route->gatherMiddleware();

            foreach ($excludedMiddlewares as $middleware) {
                if (in_array($middleware, $routeMiddlewares)) {
                    return true;
                }
            }
        }

        return false;
    }
}