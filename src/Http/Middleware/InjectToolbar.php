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

        if ($request->is('cms/*') || $request->is('api/*')) {
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
}