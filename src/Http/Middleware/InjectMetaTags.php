<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webook\LaravelCMS\Helpers\MetadataHelper;

class InjectMetaTags
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only inject on HTML responses
        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        $content = $response->getContent();

        // Check if response has HTML content with a head tag
        if ($content && strpos($content, '</head>') !== false) {
            try {
                // Get meta tags for current page and locale
                $metaTags = MetadataHelper::renderMetaTags();

                // Only inject if we have meta tags to add
                if (!empty(trim($metaTags))) {
                    // Inject before </head> closing tag
                    $content = str_replace('</head>', $metaTags . "\n</head>", $content);
                    $response->setContent($content);
                }
            } catch (\Exception $e) {
                // Silently fail if metadata cannot be loaded
                // Log error if logger is available
                if (app()->bound('log')) {
                    logger()->warning('Failed to inject CMS meta tags: ' . $e->getMessage());
                }
            }
        }

        return $response;
    }
}
