<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Support\Facades\View;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Support\Facades\Blade;

/**
 * Tracks the source file and line number for Blade components and partials
 * to enable precise editing of component content in the CMS.
 */
class BladeSourceTracker
{
    /**
     * Stack to track nested component rendering
     *
     * @var array
     */
    protected $viewStack = [];

    /**
     * Register the source tracking hooks
     */
    public function register()
    {
        // Hook into view composition to track source files
        View::composer('*', function ($view) {
            $this->pushView($view);
        });

        // Register custom Blade directives for source tracking
        $this->registerSourceDirectives();
    }

    /**
     * Push a view onto the stack when it starts rendering
     *
     * @param \Illuminate\View\View $view
     */
    protected function pushView($view)
    {
        $path = $this->getRelativePath($view->getPath());

        $this->viewStack[] = [
            'path' => $path,
            'name' => $view->getName(),
            'data' => $view->getData(),
        ];
    }

    /**
     * Pop a view from the stack when it finishes rendering
     */
    protected function popView()
    {
        return array_pop($this->viewStack);
    }

    /**
     * Get the current view being rendered
     *
     * @return array|null
     */
    public function getCurrentView()
    {
        return end($this->viewStack) ?: null;
    }

    /**
     * Convert absolute path to relative path from project root
     *
     * @param string $path
     * @return string
     */
    protected function getRelativePath($path)
    {
        $basePath = base_path();

        if (strpos($path, $basePath) === 0) {
            return str_replace($basePath . '/', '', $path);
        }

        return $path;
    }

    /**
     * Register Blade directives for source tracking
     */
    protected function registerSourceDirectives()
    {
        // @cmsSourceStart directive - marks the beginning of a trackable section
        // Usage: @cmsSourceStart
        Blade::directive('cmsSourceStart', function ($expression) {
            return '<?php if (config("cms.features.component_source_mapping")) {
                $__cmsSourceTracker = app(\Webook\LaravelCMS\Services\BladeSourceTracker::class);
                $__currentView = $__cmsSourceTracker->getCurrentView();
                if ($__currentView) {
                    echo "<!--[CMS:source:" . e($__currentView["path"]) . "]-->";
                }
            } ?>';
        });

        // @cmsSourceEnd directive - marks the end of a trackable section
        // Usage: @cmsSourceEnd
        Blade::directive('cmsSourceEnd', function ($expression) {
            return '<?php if (config("cms.features.component_source_mapping")) {
                echo "<!--[CMS:source:end]-->";
            } ?>';
        });

        // @cmsSource directive - wraps content with source markers
        // Usage: @cmsSource($slot) or @cmsSource('content here')
        Blade::directive('cmsSource', function ($expression) {
            return '<?php if (config("cms.features.component_source_mapping")) {
                $__cmsSourceTracker = app(\Webook\LaravelCMS\Services\BladeSourceTracker::class);
                $__currentView = $__cmsSourceTracker->getCurrentView();
                if ($__currentView) {
                    echo "<!--[CMS:source:" . e($__currentView["path"]) . "]-->";
                }
            }
            echo ' . $expression . ';
            if (config("cms.features.component_source_mapping")) {
                echo "<!--[CMS:source:end]-->";
            } ?>';
        });
    }

    /**
     * Wrap component rendering with source markers
     *
     * This method can be called from component classes to automatically
     * inject source tracking around their rendered output.
     *
     * @param string $componentPath
     * @param callable $renderCallback
     * @return string
     */
    public function wrapComponentRender($componentPath, callable $renderCallback)
    {
        if (!config('cms.features.component_source_mapping')) {
            return $renderCallback();
        }

        $relativePath = $this->getRelativePath($componentPath);

        $output = "<!--[CMS:source:{$relativePath}]-->";
        $output .= $renderCallback();
        $output .= "<!--[CMS:source:end]-->";

        return $output;
    }

    /**
     * Extract source metadata from HTML comments
     *
     * @param string $html
     * @return array Array of [start_pos, end_pos, source_path]
     */
    public function extractSourceMarkers($html)
    {
        $markers = [];
        $pattern = '/<!--\[CMS:source:(.*?)\]-->(.*?)<!--\[CMS:source:end\]-->/s';

        if (preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $sourcePath = $matches[1][$index][0];
                $contentStart = $matches[1][$index][1] + strlen($matches[1][$index][0]) + strlen('<!--[CMS:source:]-->');
                $contentEnd = $match[1] + strlen($match[0]) - strlen('<!--[CMS:source:end]-->');

                $markers[] = [
                    'source_path' => $sourcePath,
                    'start_pos' => $contentStart,
                    'end_pos' => $contentEnd,
                    'marker_start' => $match[1],
                    'marker_end' => $match[1] + strlen($match[0]),
                ];
            }
        }

        return $markers;
    }

    /**
     * Remove source markers from HTML
     *
     * @param string $html
     * @return string
     */
    public function removeSourceMarkers($html)
    {
        return preg_replace('/<!--\[CMS:source:.*?\]-->|<!--\[CMS:source:end\]-->/', '', $html);
    }

    /**
     * Validate that a source path is safe and within the project
     *
     * @param string $path
     * @return bool
     */
    public function isValidSourcePath($path)
    {
        // Must not contain path traversal attempts
        if (strpos($path, '..') !== false) {
            return false;
        }

        // Must be within project root
        $fullPath = base_path($path);
        $realPath = realpath($fullPath);

        if (!$realPath) {
            return false;
        }

        // Ensure it's within base path
        $baseRealPath = realpath(base_path());
        if (strpos($realPath, $baseRealPath) !== 0) {
            return false;
        }

        // Must be a .blade.php file
        if (!preg_match('/\.blade\.php$/', $path)) {
            return false;
        }

        return true;
    }
}
