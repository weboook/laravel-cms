<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webook\LaravelCMS\Services\CMSAuthHandler;
use Webook\LaravelCMS\Services\TranslationWrapper;

class WrapTranslations
{
    protected $authHandler;
    protected $translationWrapper;

    public function __construct(CMSAuthHandler $authHandler, TranslationWrapper $translationWrapper)
    {
        $this->authHandler = $authHandler;
        $this->translationWrapper = $translationWrapper;
    }

    public function handle(Request $request, Closure $next)
    {
        // Check if CMS should be active for this request
        if (!config('cms.enabled', true)) {
            return $next($request);
        }

        // Check if user has permission to access CMS
        if (!$this->authHandler->shouldShowOnRoute($request)) {
            return $next($request);
        }

        // Check if current route should be excluded
        if ($this->shouldExclude($request)) {
            return $next($request);
        }

        // Don't wrap for AJAX or JSON requests
        if ($request->ajax() || $request->wantsJson()) {
            return $next($request);
        }

        // Enable translation wrapping for this request
        $this->translationWrapper->enable();

        // Process the request
        $response = $next($request);

        // Translation wrapper is automatically used during view rendering
        // No need to disable it as the request is ending

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
