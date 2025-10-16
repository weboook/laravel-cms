<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use DOMDocument;
use DOMXPath;

class InjectEditableMarkers
{
    protected $editableSelectors = [
        'p',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'span',
        'div[class*="content"]',
        'div[class*="text"]',
        'article',
        'section > p',
        'section > h1', 'section > h2', 'section > h3',
        'main p',
        'main h1', 'main h2', 'main h3',
        'li',
        'td',
        'blockquote',
        'figcaption',
        'a',
        'img'
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Skip if CMS is not enabled
        if (!config('cms.enabled', true)) {
            return $response;
        }

        // Check if current route should be excluded
        if ($this->shouldExclude($request)) {
            return $response;
        }

        // Skip non-HTML responses
        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        $content = $response->getContent();

        // Only process HTML responses with body tag
        if ($content && strpos($content, '</body>') !== false) {
            // Check if CMS toolbar is actually present in the page
            // Only inject editable markers if the toolbar exists
            if ($this->hasCmsToolbar($content)) {
                $content = $this->injectMarkers($content);
                $response->setContent($content);
            }
        }

        return $response;
    }

    protected function injectMarkers($html)
    {
        // Extract source markers if component source mapping is enabled
        $sourceMap = [];
        if (config('cms.features.component_source_mapping')) {
            $sourceMap = $this->extractSourceMapping($html);
            // Remove source markers from HTML for processing
            $html = app(\Webook\LaravelCMS\Services\BladeSourceTracker::class)->removeSourceMarkers($html);
        }

        // Only inject permanent CMS IDs if elements are missing them
        if ($this->needsCmsIdInjection($html)) {
            $html = $this->injectPermanentCmsIds($html);
        }

        // Add data attributes to editable elements
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);

        // Process each editable selector
        foreach ($this->editableSelectors as $selector) {
            $elements = $this->querySelectorAll($xpath, $selector);

            foreach ($elements as $element) {
                $debugInfo = [
                    'selector' => $selector,
                    'tag' => $element->tagName,
                    'text' => substr(trim($element->textContent), 0, 50),
                    'class' => $element->getAttribute('class')
                ];

                // Skip if already marked
                if ($element->hasAttribute('data-cms-editable') ||
                    $element->hasAttribute('data-cms-component')) {
                    $debugInfo['skip_reason'] = 'already_marked';
                    \Log::debug('CMS Skip', $debugInfo);
                    continue;
                }

                // Skip toolbar elements and CMS injected elements
                if ($this->isToolbarElement($element) || $this->isCMSInjectedElement($element)) {
                    $debugInfo['skip_reason'] = 'toolbar_or_injected';
                    \Log::debug('CMS Skip', $debugInfo);
                    continue;
                }

                // Skip dropdown elements
                if ($this->isDropdownElement($element)) {
                    $debugInfo['skip_reason'] = 'dropdown';
                    \Log::debug('CMS Skip', $debugInfo);
                    continue;
                }

                // Skip elements in header or footer
                if ($this->isInHeaderOrFooter($element)) {
                    $debugInfo['skip_reason'] = 'header_or_footer';
                    \Log::debug('CMS Skip', $debugInfo);
                    continue;
                }

                // Check if it's database content
                if ($this->isDatabaseContent($element)) {
                    $debugInfo['skip_reason'] = 'database_content';
                    \Log::debug('CMS Skip', $debugInfo);
                    // Mark as component with appropriate message
                    $element->setAttribute('data-cms-component', 'true');
                    $element->setAttribute('data-cms-type', 'database');

                    // Provide helpful context based on element type
                    $tagName = strtolower($element->tagName);
                    $message = 'Dynamic content - Edit in admin panel';

                    // Customize message based on content type
                    if ($element->hasAttribute('class')) {
                        $classes = $element->getAttribute('class');
                        if (strpos($classes, 'product') !== false) {
                            $message = 'Product data - Edit in admin panel';
                        } elseif (strpos($classes, 'post') !== false || strpos($classes, 'blog') !== false) {
                            $message = 'Blog post - Edit in admin panel';
                        } elseif (strpos($classes, 'user') !== false || strpos($classes, 'author') !== false) {
                            $message = 'User data - Edit in admin panel';
                        } elseif (strpos($classes, 'comment') !== false || strpos($classes, 'review') !== false) {
                            $message = 'User-generated content - Manage in admin';
                        }
                    }

                    $element->setAttribute('data-cms-message', $message);
                    continue;
                }

                // For links and images, don't skip if empty
                $tagName = strtolower($element->tagName);
                if (!in_array($tagName, ['a', 'img']) && trim($element->textContent) === '') {
                    continue;
                }

                // Skip if contains other editable elements (avoid nesting)
                if ($this->containsEditableChildren($element)) {
                    continue;
                }

                // Generate or use existing data-cms-id
                $cmsId = $this->ensureCmsId($element);

                // Add editable attributes
                $element->setAttribute('data-cms-editable', 'true');
                $element->setAttribute('data-cms-type', $this->getContentType($element));
                $element->setAttribute('data-cms-id', $cmsId);
                $element->setAttribute('data-cms-original', trim($element->textContent));

                $debugInfo['marked_as'] = 'editable';
                $debugInfo['cms_id'] = $cmsId;
                \Log::debug('CMS Marked', $debugInfo);

                // Add source mapping attributes if available
                if (!empty($sourceMap) && config('cms.features.component_source_mapping')) {
                    $this->addSourceAttributes($element, $sourceMap, $dom);
                }
            }
        }

        // Inject the editable styles and scripts
        $editableAssets = $this->getEditableAssets();
        $html = $dom->saveHTML();

        // Insert assets before closing body tag
        $html = str_replace('</body>', $editableAssets . '</body>', $html);

        return $html;
    }

    protected function querySelectorAll($xpath, $selector)
    {
        // Convert CSS selector to XPath
        $xpathQuery = $this->cssToXPath($selector);
        return $xpath->query($xpathQuery);
    }

    protected function cssToXPath($selector)
    {
        // Basic CSS to XPath conversion
        $selector = trim($selector);

        // Handle basic tag selectors
        if (preg_match('/^[a-z]+$/i', $selector)) {
            return "//{$selector}";
        }

        // Handle class selectors with contains
        if (strpos($selector, '[class*=') !== false) {
            preg_match('/([a-z]+)\[class\*="([^"]+)"\]/i', $selector, $matches);
            if ($matches) {
                return "//{$matches[1]}[contains(@class, '{$matches[2]}')]";
            }
        }

        // Handle parent > child
        if (strpos($selector, '>') !== false) {
            $parts = explode('>', $selector);
            $parent = trim($parts[0]);
            $child = trim($parts[1]);
            return "//{$parent}/{$child}";
        }

        // Handle descendant selectors
        if (strpos($selector, ' ') !== false) {
            $parts = explode(' ', $selector);
            $parent = trim($parts[0]);
            $child = trim($parts[1]);
            return "//{$parent}//{$child}";
        }

        return "//{$selector}";
    }

    protected function containsEditableChildren($element)
    {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                if ($child->hasAttribute('data-cms-editable')) {
                    return true;
                }
                // Check common editable tags
                $tagName = strtolower($child->tagName);
                if (in_array($tagName, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span'])) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function getContentType($element)
    {
        $tagName = strtolower($element->tagName);

        if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            return 'heading';
        }

        if ($tagName === 'img') {
            return 'image';
        }

        if ($tagName === 'a') {
            return 'link';
        }


        return 'text';
    }

    protected function generateContentId($element)
    {
        $tagName = strtolower($element->tagName);

        // For images, use src attribute for more stable ID
        if ($tagName === 'img') {
            $src = $element->getAttribute('src');
            if ($src) {
                // Extract filename from src
                $filename = basename(parse_url($src, PHP_URL_PATH));
                $filename = pathinfo($filename, PATHINFO_FILENAME);
                return 'img-' . substr(md5($filename . $src), 0, 16);
            }
        }

        // For links, use href for more stable ID
        if ($tagName === 'a') {
            $href = $element->getAttribute('href');
            $text = substr(trim($element->textContent), 0, 20);
            return 'link-' . substr(md5($href . $text), 0, 16);
        }

        // For other elements
        $text = substr(trim($element->textContent), 0, 30);
        $classes = $element->getAttribute('class');
        return $tagName . '-' . substr(md5($text . $classes), 0, 16);
    }

    /**
     * Ensure element has a data-cms-id, generate if missing
     */
    protected function ensureCmsId($element)
    {
        // Check if element already has data-cms-id
        if ($element->hasAttribute('data-cms-id')) {
            return $element->getAttribute('data-cms-id');
        }

        // Generate a stable, unique ID
        $cmsId = $this->generateContentId($element);

        // Store for potential Blade file update
        $this->trackGeneratedId($element, $cmsId);

        return $cmsId;
    }

    /**
     * Track generated IDs for potential Blade file updates
     */
    protected function trackGeneratedId($element, $cmsId)
    {
        // Store information about generated IDs
        // This could be used by a separate process to update Blade files
        if (!isset($GLOBALS['cms_generated_ids'])) {
            $GLOBALS['cms_generated_ids'] = [];
        }

        $tagName = strtolower($element->tagName);
        $elementInfo = [
            'id' => $cmsId,
            'tag' => $tagName,
            'html' => $element->ownerDocument->saveHTML($element)
        ];

        // For images and links, store additional identifying info
        if ($tagName === 'img') {
            $elementInfo['src'] = $element->getAttribute('src');
            $elementInfo['alt'] = $element->getAttribute('alt');
        } elseif ($tagName === 'a') {
            $elementInfo['href'] = $element->getAttribute('href');
            $elementInfo['text'] = trim($element->textContent);
        }

        $GLOBALS['cms_generated_ids'][] = $elementInfo;
    }

    protected function isCMSInjectedElement($element)
    {
        // Check if element is within a script or style tag that we injected
        $current = $element;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($current->tagName);

            // Skip if inside script or style tags
            if ($tagName === 'script' || $tagName === 'style') {
                // Check if it's a CMS-injected script/style
                if ($current->hasAttribute('id')) {
                    $id = $current->getAttribute('id');
                    if (strpos($id, 'cms-') === 0) {
                        return true;
                    }
                }
                return true; // Skip all scripts and styles to be safe
            }

            $current = $current->parentNode;
        }
        return false;
    }

    protected function isToolbarElement($element)
    {
        // Check if element or any parent has cms-toolbar related classes/ids
        $current = $element;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            // Check for toolbar-related IDs - be more comprehensive
            if ($current->hasAttribute('id')) {
                $id = $current->getAttribute('id');
                if (strpos($id, 'cms') === 0 || // Any ID starting with 'cms'
                    strpos($id, 'cms-') !== false ||
                    $id === 'cms-toolbar' ||
                    $id === 'cms-modal-container' ||
                    $id === 'cms-pages-list' ||
                    $id === 'cms-languages-list' ||
                    strpos($id, 'cms-editable-') === 0) {
                    return true;
                }
            }

            // Check for toolbar-related classes - be more comprehensive
            if ($current->hasAttribute('class')) {
                $class = $current->getAttribute('class');
                if (strpos($class, 'cms-') !== false || // Any class with 'cms-'
                    strpos($class, 'cms') === 0 || // Any class starting with 'cms'
                    strpos($class, 'cms-page-item') !== false ||
                    strpos($class, 'cms-language-item') !== false) {
                    return true;
                }
            }

            // Check for data attributes that indicate CMS elements
            if ($current->hasAttribute('data-modal') ||
                $current->hasAttribute('data-mode') ||
                $current->hasAttribute('data-tab') ||
                $current->hasAttribute('data-lang') ||
                $current->hasAttribute('data-path') ||
                $current->hasAttribute('data-template') ||
                $current->hasAttribute('data-cms-ignore')) {
                return true;
            }

            // Move up to parent
            $current = $current->parentNode;
        }
        return false;
    }

    protected function isDatabaseContent($element)
    {
        // Check element's content for Blade variables and directives
        $elementContent = $element->textContent;

        // Check for Blade variable syntax in the actual rendered content
        // If we see variable names or method calls, it's likely dynamic
        if (preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*/', $elementContent) ||
            preg_match('/->\\w+/', $elementContent) ||
            preg_match('/\[\s*[\'"]\\w+[\'"]\s*\]/', $elementContent)) {
            return true;
        }

        // Check if element has database-related data attributes
        $dbAttributes = [
            'data-id', 'data-model', 'data-entity', 'data-record',
            'data-model-id', 'data-product-id', 'data-post-id',
            'data-user-id', 'data-item-id', 'data-row-id'
        ];

        foreach ($dbAttributes as $attr) {
            if ($element->hasAttribute($attr)) {
                return true;
            }
        }

        // Check for common database content patterns in classes
        if ($element->hasAttribute('class')) {
            $classes = $element->getAttribute('class');
            $dbClassPatterns = [
                'product-', 'post-', 'article-', 'item-', 'record-',
                'blog-', 'news-', 'event-', 'listing-', 'entry-',
                'comment-', 'review-', 'user-', 'author-', 'meta-',
                'category-', 'tag-', 'taxonomy-'
            ];

            foreach ($dbClassPatterns as $pattern) {
                if (strpos($classes, $pattern) !== false) {
                    return true;
                }
            }
        }

        // Check if element is within a loop structure
        $parent = $element->parentNode;
        $depth = 0;
        while ($parent && $depth < 7) {
            if ($parent->nodeType === XML_ELEMENT_NODE) {
                // Check for loop indicators in parent classes
                if ($parent->hasAttribute('class')) {
                    $parentClasses = $parent->getAttribute('class');
                    if (preg_match('/(loop|list|items|results|entries|posts|products|grid|feed|stream)/i', $parentClasses)) {
                        // If parent is a list/loop container, this is likely database content
                        return true;
                    }
                }

                // Check for data attributes indicating collections
                if ($parent->hasAttribute('data-items') ||
                    $parent->hasAttribute('data-collection') ||
                    $parent->hasAttribute('data-results') ||
                    $parent->hasAttribute('data-posts') ||
                    $parent->hasAttribute('data-products')) {
                    return true;
                }

                // Check if parent is a common list container
                $parentTag = strtolower($parent->tagName);
                if (in_array($parentTag, ['tbody', 'ul', 'ol']) &&
                    $parent->childNodes->length > 3) {
                    // Multiple similar siblings likely means loop-generated content
                    $siblings = 0;
                    $currentTag = strtolower($element->tagName);
                    foreach ($parent->childNodes as $child) {
                        if ($child->nodeType === XML_ELEMENT_NODE &&
                            strtolower($child->tagName) === $currentTag) {
                            $siblings++;
                        }
                    }
                    if ($siblings > 3) {
                        return true;
                    }
                }
            }
            $parent = $parent->parentNode;
            $depth++;
        }

        // Check for AJAX/API endpoint indicators
        if ($element->hasAttribute('data-url') ||
            $element->hasAttribute('data-endpoint') ||
            $element->hasAttribute('data-api') ||
            $element->hasAttribute('data-source')) {
            return true;
        }

        // Check for pagination elements nearby (indicates list views)
        $xpath = new DOMXPath($element->ownerDocument);
        $pagination = $xpath->query(".//*[contains(@class, 'pagination') or contains(@class, 'pager')]",
                                   $element->parentNode);
        if ($pagination->length > 0) {
            return true;
        }

        // Check for templating engine markers that might have been rendered
        // Look for consistent patterns that indicate template loops
        if ($element->parentNode && $element->parentNode->childNodes->length > 1) {
            $firstChild = null;
            $hasIdenticalStructure = true;
            $structureCount = 0;

            foreach ($element->parentNode->childNodes as $sibling) {
                if ($sibling->nodeType === XML_ELEMENT_NODE) {
                    if (!$firstChild) {
                        $firstChild = $sibling;
                    } else if ($sibling->tagName === $firstChild->tagName) {
                        $structureCount++;
                    }
                }
            }

            // If there are multiple elements with same tag name, likely a loop
            if ($structureCount > 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if element is a dropdown element
     */
    protected function isDropdownElement($element)
    {
        // Check if element or any parent has dropdown-related classes/attributes
        $current = $element;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            // Check for dropdown-related classes
            if ($current->hasAttribute('class')) {
                $class = $current->getAttribute('class');
                $dropdownClasses = [
                    'dropdown', 'dropup', 'dropstart', 'dropend',
                    'dropdown-menu', 'dropdown-item', 'dropdown-toggle',
                    'nav-dropdown', 'menu-dropdown', 'select-dropdown',
                    'autocomplete', 'typeahead', 'combobox',
                    'datalist', 'select2', 'chosen'
                ];

                foreach ($dropdownClasses as $dropdownClass) {
                    if (strpos($class, $dropdownClass) !== false) {
                        return true;
                    }
                }
            }

            // Check for dropdown-related data attributes
            if ($current->hasAttribute('data-toggle') &&
                strpos($current->getAttribute('data-toggle'), 'dropdown') !== false) {
                return true;
            }

            if ($current->hasAttribute('data-bs-toggle') &&
                strpos($current->getAttribute('data-bs-toggle'), 'dropdown') !== false) {
                return true;
            }

            // Check for role attribute indicating dropdown
            if ($current->hasAttribute('role')) {
                $role = $current->getAttribute('role');
                if (in_array($role, ['menu', 'menubar', 'menuitem', 'listbox', 'option', 'combobox'])) {
                    return true;
                }
            }

            // Check if it's a select element or within one
            if (strtolower($current->tagName) === 'select' ||
                strtolower($current->tagName) === 'option' ||
                strtolower($current->tagName) === 'optgroup') {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }

    /**
     * Check if element is within header or footer
     */
    protected function isInHeaderOrFooter($element)
    {
        // Check if element or any parent is header/footer
        $current = $element;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($current->tagName);

            // Check for semantic HTML5 header/footer tags
            if (in_array($tagName, ['header', 'footer'])) {
                return true;
            }

            // Check for common header/footer classes
            if ($current->hasAttribute('class')) {
                $class = strtolower($current->getAttribute('class'));
                $headerFooterClasses = [
                    'header', 'footer', 'site-header', 'site-footer',
                    'page-header', 'page-footer', 'main-header', 'main-footer',
                    'navbar', 'nav-bar', 'navigation', 'nav-menu',
                    'topbar', 'top-bar', 'bottom-bar', 'footer-bar'
                ];

                foreach ($headerFooterClasses as $headerFooterClass) {
                    if (strpos($class, $headerFooterClass) !== false) {
                        return true;
                    }
                }
            }

            // Check for common header/footer IDs
            if ($current->hasAttribute('id')) {
                $id = strtolower($current->getAttribute('id'));
                $headerFooterIds = [
                    'header', 'footer', 'site-header', 'site-footer',
                    'page-header', 'page-footer', 'main-header', 'main-footer',
                    'navbar', 'navigation', 'nav', 'topbar', 'bottombar'
                ];

                foreach ($headerFooterIds as $headerFooterId) {
                    if (strpos($id, $headerFooterId) !== false) {
                        return true;
                    }
                }
            }

            // Check for role attribute indicating navigation
            if ($current->hasAttribute('role')) {
                $role = $current->getAttribute('role');
                if (in_array($role, ['banner', 'navigation', 'contentinfo'])) {
                    return true;
                }
            }

            $current = $current->parentNode;
        }

        return false;
    }

    /**
     * Check if CMS toolbar is present in the page content
     */
    protected function hasCmsToolbar($content)
    {
        // Check for various indicators that the CMS toolbar is loaded
        $toolbarIndicators = [
            'id="cms-toolbar"',           // Main toolbar element
            'class="cms-toolbar"',        // Toolbar with class
            'cms-toolbar-container',      // Toolbar container
            'window.CMS =',               // CMS JavaScript object
            'cms:modeChanged',           // CMS event listeners
            '<script id="cms-toolbar-script">', // Toolbar script
            'data-cms-toolbar',          // Toolbar data attribute
        ];

        foreach ($toolbarIndicators as $indicator) {
            if (strpos($content, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current request should be excluded from CMS
     */
    protected function shouldExclude($request)
    {
        // Check excluded routes (pattern matching)
        $excludedRoutes = config('cms.exclusions.routes', []);
        foreach ($excludedRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        // Check excluded prefixes
        $excludedPrefixes = config('cms.exclusions.prefixes', []);
        $path = $request->path();
        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
                return true;
            }
        }

        // Check excluded route names
        $routeName = $request->route()?->getName();
        if ($routeName) {
            $excludedNames = config('cms.exclusions.names', []);
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

    /**
     * Check if HTML needs CMS ID injection
     */
    protected function needsCmsIdInjection($html)
    {
        // Check if there are img or <a> tags without data-cms-id
        if (preg_match('/<img(?![^>]*data-cms-id)[^>]*>/i', $html)) {
            return true;
        }

        if (preg_match('/<a(?![^>]*data-cms-id)[^>]*>/i', $html)) {
            // Only for simple links, not complex ones
            if (preg_match('/<a(?![^>]*data-cms-id)([^>]*)>(?!.*<[^>]+>.*<\/a>)/i', $html)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inject permanent CMS IDs into HTML elements
     */
    protected function injectPermanentCmsIds($html)
    {
        // Skip if this looks like a Blade template with loops
        if (preg_match('/@(foreach|forelse|for|while)/', $html)) {
            return $html;
        }

        // Track if we made changes
        $changesMade = false;

        // Inject IDs for images
        $html = preg_replace_callback(
            '/<img(?![^>]*data-cms-id)([^>]*)>/i',
            function ($matches) use (&$changesMade) {
                $imgTag = $matches[0];
                $attributes = $matches[1];

                // Skip if it has Blade syntax
                if (strpos($imgTag, '{{') !== false || strpos($imgTag, '{!!') !== false) {
                    return $imgTag;
                }

                // Skip if marked as database component
                if (strpos($imgTag, 'data-cms-component') !== false) {
                    return $imgTag;
                }

                // Extract src for ID generation
                $src = '';
                if (preg_match('/src=["\']([^"\']+)["\']/', $imgTag, $srcMatch)) {
                    $src = $srcMatch[1];
                }

                // Generate stable ID
                $filename = basename(parse_url($src, PHP_URL_PATH));
                $filename = pathinfo($filename, PATHINFO_FILENAME);
                $cmsId = 'img-' . substr(md5($filename . $src), 0, 16);

                $changesMade = true;

                // Insert data-cms-id
                return '<img data-cms-id="' . $cmsId . '"' . $attributes . '>';
            },
            $html
        );

        // Inject IDs for simple links (avoid complex nested structures)
        $html = preg_replace_callback(
            '/<a(?![^>]*data-cms-id)([^>]*)>((?:[^<]|<(?!\/a>))*?)<\/a>/i',
            function ($matches) use (&$changesMade) {
                $fullTag = $matches[0];
                $attributes = $matches[1];
                $linkContent = $matches[2];

                // Skip if it has Blade syntax
                if (strpos($fullTag, '{{') !== false || strpos($fullTag, '{!!') !== false) {
                    return $fullTag;
                }

                // Skip if marked as database component
                if (strpos($fullTag, 'data-cms-component') !== false) {
                    return $fullTag;
                }

                // Skip if contains complex HTML (multiple nested tags)
                $tagCount = substr_count($linkContent, '<');
                if ($tagCount > 2) {
                    return $fullTag;
                }

                // Extract href for ID generation
                $href = '';
                if (preg_match('/href=["\']([^"\']+)["\']/', $attributes, $hrefMatch)) {
                    $href = $hrefMatch[1];
                }

                // Generate stable ID
                $text = strip_tags($linkContent);
                $text = substr(trim($text), 0, 20);
                $cmsId = 'link-' . substr(md5($href . $text), 0, 16);

                $changesMade = true;

                // Insert data-cms-id
                return '<a data-cms-id="' . $cmsId . '"' . $attributes . '>' . $linkContent . '</a>';
            },
            $html
        );

        // If we made changes, update the source file
        if ($changesMade) {
            $this->updateSourceFileWithIds($html);
        }

        return $html;
    }

    /**
     * Update the source Blade file with injected IDs
     */
    protected function updateSourceFileWithIds($html)
    {
        // Get the current view file being rendered
        $viewPath = $this->getCurrentViewPath();

        if (!$viewPath || !file_exists($viewPath)) {
            return;
        }

        // Read the original Blade file
        $bladeContent = file_get_contents($viewPath);

        // Only update if the file doesn't already have CMS IDs
        if (strpos($bladeContent, 'data-cms-id') !== false) {
            return;
        }

        // Extract just the IDs we added and apply them to the Blade file
        // This is complex because we need to preserve Blade syntax
        // For now, we'll log this for manual processing
        \Log::info('CMS IDs need to be added to: ' . $viewPath);
    }

    /**
     * Get the current view path being rendered
     */
    protected function getCurrentViewPath()
    {
        // Try to get the current view from Laravel's view factory
        try {
            $view = app('view')->getFinder()->find(app('view')->shared('__name', ''));
            return $view;
        } catch (\Exception $e) {
            // Could not determine current view
            return null;
        }
    }

    /**
     * Extract source mapping from HTML comment markers
     *
     * @param string $html
     * @return array
     */
    protected function extractSourceMapping($html)
    {
        $sourceTracker = app(\Webook\LaravelCMS\Services\BladeSourceTracker::class);
        return $sourceTracker->extractSourceMarkers($html);
    }

    /**
     * Add source attributes to an element based on its position in the document
     *
     * @param \DOMElement $element
     * @param array $sourceMap
     * @param \DOMDocument $dom
     */
    protected function addSourceAttributes($element, $sourceMap, $dom)
    {
        // Get the element's position in the original HTML
        // This is approximate since we're working with DOM after parsing
        $elementHtml = $dom->saveHTML($element);
        $fullHtml = $dom->saveHTML();

        // Find which source block this element belongs to
        foreach ($sourceMap as $marker) {
            // Check if element is within this source block's range
            // This is a simplified approach - in production you might want more sophisticated matching
            if (!empty($marker['source_path'])) {
                $sourcePath = $marker['source_path'];

                // Validate the source path
                $sourceTracker = app(\Webook\LaravelCMS\Services\BladeSourceTracker::class);
                if ($sourceTracker->isValidSourcePath($sourcePath)) {
                    $element->setAttribute('data-cms-source', $sourcePath);
                    // Note: Line number tracking would require more sophisticated parsing
                    // For now, we just track the file. Line numbers can be added in future iterations.
                    break;
                }
            }
        }
    }

    protected function getEditableAssets()
    {
        return <<<HTML
<style id="cms-editable-styles">
    [data-cms-editable] {
        position: relative;
        transition: all 0.2s ease;
    }

    /* Hide all editable indicators by default - only show in edit mode */
    body.cms-edit-mode [data-cms-editable]:not([data-cms-type="link"]) {
        outline: 2px dashed transparent;
        outline-offset: 4px;
        cursor: pointer;
        min-height: 20px;
    }

    body.cms-edit-mode [data-cms-editable]:not([data-cms-type="link"]):hover {
        outline-color: #0066ff;
        background-color: rgba(0, 102, 255, 0.05);
    }

    body.cms-edit-mode [data-cms-editable].cms-editing {
        outline: 2px solid #0066ff;
        background-color: rgba(0, 102, 255, 0.1);
    }

    /* Link and Image specific styles */
    body.cms-edit-mode [data-cms-type="link"] {
        position: relative;
    }

    body.cms-edit-mode [data-cms-type="image"] {
        position: relative;
        display: inline-block;
        outline: 2px dashed transparent;
        outline-offset: 4px;
        transition: all 0.2s ease;
    }

    body.cms-edit-mode [data-cms-type="image"]:hover {
        outline-color: #0066ff;
    }

    /* Image wrapper styles */
    .cms-image-wrapper {
        position: relative;
        display: inline-block;
    }

    .cms-image-wrapper img {
        display: block;
    }

    body.cms-edit-mode .cms-image-wrapper:hover img {
        outline: 2px dashed #0066ff;
        outline-offset: 4px;
    }

    .cms-link-wrapper {
        position: relative;
        display: inline-block;
    }

    .cms-link-gear {
        position: absolute;
        top: 50%;
        right: -35px;
        transform: translateY(-50%);
        width: 28px;
        height: 28px;
        background: #0066ff;
        border-radius: 4px;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: all 0.2s ease;
    }

    /* Adjust gear position for images */
    body.cms-edit-mode [data-cms-type="image"] .cms-link-gear,
    body.cms-edit-mode .cms-image-wrapper .cms-link-gear {
        top: 10px;
        right: 10px;
        transform: none;
    }

    .cms-link-gear:hover {
        background: #0052d4;
        transform: translateY(-50%) scale(1.1);
    }

    body.cms-edit-mode [data-cms-type="image"] .cms-link-gear:hover,
    body.cms-edit-mode .cms-image-wrapper .cms-link-gear:hover {
        transform: scale(1.1);
    }

    .cms-link-gear svg {
        width: 16px;
        height: 16px;
        fill: white;
    }

    /* Show gear on hover of link or image */
    body.cms-edit-mode [data-cms-type="link"]:hover .cms-link-gear,
    body.cms-edit-mode [data-cms-type="image"]:hover .cms-link-gear,
    body.cms-edit-mode .cms-image-wrapper:hover .cms-link-gear,
    body.cms-edit-mode .cms-link-gear:hover,
    body.cms-edit-mode .cms-link-gear.visible {
        display: flex;
    }

    /* Invisible bridge to maintain hover */
    body.cms-edit-mode [data-cms-type="link"]::after,
    body.cms-edit-mode [data-cms-type="image"]::after {
        content: '';
        position: absolute;
        top: 50%;
        right: -35px;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        z-index: 9999;
        pointer-events: none;
    }

    body.cms-edit-mode [data-cms-type="link"]:hover::after,
    body.cms-edit-mode [data-cms-type="image"]:hover::after {
        pointer-events: auto;
    }

    /* Type labels - only visible in edit mode */
    body.cms-edit-mode [data-cms-editable]::before {
        content: attr(data-cms-type);
        position: absolute;
        top: -24px;
        left: 0;
        background: #0066ff;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 2px;
        text-transform: uppercase;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
        z-index: 10000;
    }

    body.cms-edit-mode [data-cms-editable]:hover::before {
        opacity: 1;
    }

    .cms-inline-editor {
        border: none;
        outline: none;
        background: transparent;
        font: inherit;
        color: inherit;
        padding: 0;
        margin: 0;
        width: 100%;
        resize: none;
        overflow: hidden;
    }

    .cms-editor-toolbar {
        position: absolute;
        top: -48px;
        left: 0;
        background: #1a1a1a;
        border-radius: 4px;
        padding: 4px;
        display: flex;
        gap: 2px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 10001;
    }

    .cms-editor-toolbar button {
        background: transparent;
        border: none;
        color: #fff;
        padding: 6px 8px;
        cursor: pointer;
        border-radius: 2px;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 4px;
        min-width: 30px;
        justify-content: center;
    }

    .cms-editor-toolbar button:hover {
        background: #333;
    }

    .cms-editor-toolbar button.active {
        background: #0066ff;
    }

    .cms-editor-toolbar .separator {
        width: 1px;
        background: #444;
        margin: 4px 2px;
    }

    .cms-editor-toolbar button svg {
        width: 14px;
        height: 14px;
        fill: currentColor;
    }

    /* Database component styles */
    body.cms-edit-mode [data-cms-component="true"] {
        position: relative;
        outline: 2px dashed #ffa500;
        outline-offset: 4px;
        background-color: rgba(255, 165, 0, 0.03);
        cursor: not-allowed;
        opacity: 0.9;
    }

    body.cms-edit-mode [data-cms-component="true"]:hover {
        background-color: rgba(255, 165, 0, 0.08);
        opacity: 1;
    }

    body.cms-edit-mode [data-cms-component="true"]::after {
        content: attr(data-cms-message);
        position: absolute;
        top: -32px;
        left: 0;
        background: linear-gradient(135deg, #ff9500, #ffb347);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 4px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        opacity: 0;
        transition: all 0.3s ease;
        pointer-events: none;
        z-index: 10001;
        white-space: nowrap;
        box-shadow: 0 3px 12px rgba(255, 149, 0, 0.4);
        transform: translateY(2px);
    }

    body.cms-edit-mode [data-cms-component="true"]:hover::after {
        opacity: 1;
        transform: translateY(0);
    }

    /* Database icon indicator */
    body.cms-edit-mode [data-cms-component="true"]::before {
        content: '🔒';
        position: absolute;
        top: -2px;
        right: -2px;
        background: #ff9500;
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        z-index: 10000;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
</style>

<script id="cms-editable-script">
(function() {
    'use strict';

    // Wait for CMS toolbar to be ready
    document.addEventListener('DOMContentLoaded', function() {

        // Set initial mode
        if (window.CMS && window.CMS.mode === 'edit') {
            document.body.classList.add('cms-edit-mode');
        }

        // Listen for mode changes
        document.addEventListener('cms:modeChanged', function(e) {
            if (e.detail.mode === 'edit') {
                document.body.classList.add('cms-edit-mode');
                initializeEditableElements();
            } else {
                document.body.classList.remove('cms-edit-mode');
                closeAllEditors();
            }
        });

        // Initialize editable elements
        function initializeEditableElements() {
            const editables = document.querySelectorAll('[data-cms-editable]');
            const components = document.querySelectorAll('[data-cms-component]');

            // Handle database components - add visual indicators
            components.forEach(component => {
                // Remove any click handlers to prevent editing
                component.style.pointerEvents = 'none';
                component.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Show a toast message
                    const message = component.getAttribute('data-cms-message') || 'Database content cannot be edited directly';
                    if (window.CMS && window.CMS.showToast) {
                        window.CMS.showToast(message, 'warning');
                    }
                }, true);
            });

            editables.forEach(element => {
                const type = element.getAttribute('data-cms-type');

                // Handle links and images with gear icon
                if (type === 'link' || type === 'image') {
                    initializeLinkElement(element);
                } else {
                    // Remove any existing listeners
                    element.removeEventListener('click', handleEditableClick);
                    // Add click listener
                    element.addEventListener('click', handleEditableClick);
                }
            });
        }

        // Initialize link elements with gear icon
        function initializeLinkElement(element) {
            const type = element.getAttribute('data-cms-type');

            // For images, we need to wrap them in a container since images can't have children
            if (type === 'image') {
                // Check if already wrapped
                if (element.parentElement && element.parentElement.classList.contains('cms-image-wrapper')) {
                    // Already wrapped, just ensure gear is there
                    if (!element.parentElement.querySelector('.cms-link-gear')) {
                        createGearForImage(element.parentElement, element);
                    }
                    return;
                }

                // Create wrapper
                const wrapper = document.createElement('span');
                wrapper.className = 'cms-image-wrapper';
                wrapper.setAttribute('data-cms-ignore', 'true');

                // Copy data attributes to wrapper for styling
                wrapper.setAttribute('data-cms-type', 'image');

                // Insert wrapper before image and move image into it
                element.parentNode.insertBefore(wrapper, element);
                wrapper.appendChild(element);

                // Create gear for wrapper
                createGearForImage(wrapper, element);
                return;
            }

            // For links, handle normally
            // Remove existing gear if any
            const existingGear = element.querySelector('.cms-link-gear');
            if (existingGear) {
                existingGear.remove();
            }

            // Create gear icon
            const gear = document.createElement('div');
            gear.className = 'cms-link-gear';
            gear.setAttribute('data-cms-ignore', 'true'); // Mark to be ignored
            gear.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 8.666c-1.838 0-3.333 1.496-3.333 3.334s1.495 3.333 3.333 3.333 3.333-1.495 3.333-3.333-1.495-3.334-3.333-3.334zm0 5.334c-1.105 0-2-.896-2-2s.895-2 2-2 2 .896 2 2-.895 2-2 2zm7.04-2.404l1.313-.988c.185-.139.23-.386.119-.579l-1.24-2.148c-.11-.193-.359-.244-.534-.137l-1.540.617c-.449-.331-.949-.596-1.489-.784l-.234-1.644c-.038-.218-.237-.382-.456-.382h-2.478c-.219 0-.418.164-.456.382l-.234 1.644c-.540.188-1.04.453-1.489.784l-1.54-.617c-.175-.107-.424-.056-.534.137l-1.24 2.148c-.11.193-.066.44.119.579l1.313.988c-.05.261-.081.53-.081.809s.031.548.081.809l-1.313.988c-.185.139-.23.386-.119.579l1.24 2.148c.11.193.359.244.534.137l1.54-.617c.449.331.949.596 1.489.784l.234 1.644c.038.218.237.382.456.382h2.478c.219 0 .418-.164.456-.382l.234-1.644c.540-.188 1.04-.453 1.489-.784l1.54.617c.175.107.424.056.534-.137l1.24-2.148c.11-.193.066-.44-.119-.579l-1.313-.988c.05-.261.081-.53.081-.809s-.031-.548-.081-.809z"/></svg>';
            element.appendChild(gear);

            // Add hover timeout handling
            let hoverTimeout;
            let isHovering = false;

            // Show gear on element hover
            element.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                gear.classList.add('visible');
                isHovering = true;
            });

            element.addEventListener('mouseleave', function() {
                isHovering = false;
                // Delay hiding to allow moving to gear
                hoverTimeout = setTimeout(() => {
                    if (!gear.matches(':hover')) {
                        gear.classList.remove('visible');
                    }
                }, 200);
            });

            // Keep gear visible when hovering gear itself
            gear.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                gear.classList.add('visible');
            });

            gear.addEventListener('mouseleave', function() {
                if (!isHovering) {
                    hoverTimeout = setTimeout(() => {
                        gear.classList.remove('visible');
                    }, 200);
                }
            });

            // Add click handler to gear
            gear.addEventListener('click', function(e) {
                // Only allow editing when in edit mode
                if (!document.body.classList.contains('cms-edit-mode')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                const type = element.getAttribute('data-cms-type');
                if (type === 'image') {
                    openImageEditor(element);
                } else {
                    openLinkEditor(element);
                }
            });
        }

        // Create gear for image wrapper
        function createGearForImage(wrapper, imageElement) {
            // Create gear icon
            const gear = document.createElement('div');
            gear.className = 'cms-link-gear';
            gear.setAttribute('data-cms-ignore', 'true');
            gear.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 8.666c-1.838 0-3.333 1.496-3.333 3.334s1.495 3.333 3.333 3.333 3.333-1.495 3.333-3.333-1.495-3.334-3.333-3.334zm0 5.334c-1.105 0-2-.896-2-2s.895-2 2-2 2 .896 2 2-.895 2-2 2zm7.04-2.404l1.313-.988c.185-.139.23-.386.119-.579l-1.24-2.148c-.11-.193-.359-.244-.534-.137l-1.540.617c-.449-.331-.949-.596-1.489-.784l-.234-1.644c-.038-.218-.237-.382-.456-.382h-2.478c-.219 0-.418.164-.456.382l-.234 1.644c-.540.188-1.04.453-1.489.784l-1.54-.617c-.175-.107-.424-.056-.534.137l-1.24 2.148c-.11.193-.066.44.119.579l1.313.988c-.05.261-.081.53-.081.809s.031.548.081.809l-1.313.988c-.185.139-.23.386-.119.579l1.24 2.148c.11.193.359.244.534.137l1.54-.617c.449.331.949.596 1.489.784l.234 1.644c.038.218.237.382.456.382h2.478c.219 0 .418-.164.456-.382l.234-1.644c.540-.188 1.04-.453 1.489-.784l1.54.617c.175.107.424.056.534-.137l1.24-2.148c.11-.193.066-.44-.119-.579l-1.313-.988c.05-.261.081-.53.081-.809s-.031-.548-.081-.809z"/></svg>';
            wrapper.appendChild(gear);

            // Add hover handlers
            let hoverTimeout;

            wrapper.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                gear.classList.add('visible');
            });

            wrapper.addEventListener('mouseleave', function() {
                hoverTimeout = setTimeout(() => {
                    if (!gear.matches(':hover')) {
                        gear.classList.remove('visible');
                    }
                }, 200);
            });

            gear.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                gear.classList.add('visible');
            });

            gear.addEventListener('mouseleave', function() {
                hoverTimeout = setTimeout(() => {
                    gear.classList.remove('visible');
                }, 200);
            });

            // Add click handler to gear
            gear.addEventListener('click', function(e) {
                // Only allow editing when in edit mode
                if (!document.body.classList.contains('cms-edit-mode')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                openImageEditor(imageElement);
            });
        }

        // Handle click on editable element
        function handleEditableClick(e) {
            // Only allow editing when in edit mode
            if (!document.body.classList.contains('cms-edit-mode')) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const element = e.currentTarget;

            // Close other editors
            closeAllEditors();

            // Mark as editing
            element.classList.add('cms-editing');

            // Create inline editor
            createInlineEditor(element);
        }

        // Open link editor modal
        function openLinkEditor(element) {
            const linkText = element.textContent;
            const linkHref = element.getAttribute('href') || '';
            const linkTarget = element.getAttribute('target') === '_blank';

            // Store reference to current element
            window.CMS = window.CMS || {};
            window.CMS.currentLinkElement = element;

            // Dispatch event to open modal
            const event = new CustomEvent('cms:openLinkEditor', {
                detail: {
                    text: linkText,
                    href: linkHref,
                    newTab: linkTarget,
                    element: element
                }
            });
            document.dispatchEvent(event);
        }

        // Open image editor modal
        function openImageEditor(element) {
            const imageSrc = element.getAttribute('src') || '';
            const imageAlt = element.getAttribute('alt') || '';
            const imageTitle = element.getAttribute('title') || '';

            // Store reference to current element
            window.CMS = window.CMS || {};
            window.CMS.currentImageElement = element;

            // Dispatch event to open modal
            const event = new CustomEvent('cms:openImageEditor', {
                detail: {
                    src: imageSrc,
                    alt: imageAlt,
                    title: imageTitle,
                    element: element
                }
            });
            document.dispatchEvent(event);
        }

        // Open translation convert modal
        function openTranslationConvertModal(element) {
            const originalContent = element.textContent || element.innerHTML;

            // Store reference to current element
            window.CMS = window.CMS || {};
            window.CMS.currentTranslationElement = element;

            // Dispatch event to open modal
            const event = new CustomEvent('cms:openTranslationConvert', {
                detail: {
                    originalContent: originalContent,
                    element: element
                }
            });
            document.dispatchEvent(event);
        }

        // Create inline editor
        function createInlineEditor(element) {
            const type = element.getAttribute('data-cms-type');
            const originalContent = element.innerHTML;
            const originalText = element.textContent;

            // Store original content
            element.setAttribute('data-cms-original-html', originalContent);

            // Make it contenteditable
            element.contentEditable = true;
            element.focus();

            // Don't move cursor - let it stay where the user clicked
            // Only set cursor position if no selection exists
            const selection = window.getSelection();
            if (selection.rangeCount === 0 || selection.isCollapsed) {
                // Keep the cursor where it was clicked
                // Don't manipulate the selection
            }

            // Create toolbar
            const toolbar = createEditorToolbar(element);
            toolbar.setAttribute('data-cms-ignore', 'true'); // Mark to be ignored
            element.parentElement.appendChild(toolbar);

            // Handle blur
            element.addEventListener('blur', function onBlur(e) {
                // Don't close if clicking toolbar
                if (toolbar.contains(e.relatedTarget)) {
                    element.focus();
                    return;
                }

                setTimeout(() => {
                    if (!toolbar.contains(document.activeElement)) {
                        saveAndCloseEditor(element, toolbar);
                    }
                }, 100);
            });

            // Handle Enter key for headings and paragraphs
            element.addEventListener('keydown', function onKeydown(e) {
                if (e.key === 'Enter' && type === 'heading') {
                    e.preventDefault();
                    saveAndCloseEditor(element, toolbar);
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    // Restore original content
                    element.innerHTML = originalContent;
                    saveAndCloseEditor(element, toolbar);
                }
            });
        }

        // Create editor toolbar
        function createEditorToolbar(element) {
            const toolbar = document.createElement('div');
            toolbar.className = 'cms-editor-toolbar';

            // Format buttons
            const formatButtons = [
                { command: 'bold', icon: 'B', title: 'Bold' },
                { command: 'italic', icon: 'I', title: 'Italic' },
                { command: 'underline', icon: 'U', title: 'Underline' },
                { separator: true },
                { command: 'createLink', icon: '🔗', title: 'Link', needsInput: true },
                { command: 'unlink', icon: '⛓️‍💥', title: 'Unlink' },
                { separator: true },
                { command: 'formatBlock', value: 'h1', icon: 'H1', title: 'Heading 1' },
                { command: 'formatBlock', value: 'h2', icon: 'H2', title: 'Heading 2' },
                { command: 'formatBlock', value: 'p', icon: 'P', title: 'Paragraph' },
                { separator: true },
                { command: 'insertOrderedList', icon: '1.', title: 'Ordered List' },
                { command: 'insertUnorderedList', icon: '•', title: 'Unordered List' },
                { separator: true },
                { command: 'convertTranslation', icon: '🌐', title: 'Convert to Translation', custom: true }
            ];

            // Add format buttons
            formatButtons.forEach(btn => {
                if (btn.separator) {
                    const sep = document.createElement('div');
                    sep.className = 'separator';
                    toolbar.appendChild(sep);
                } else {
                    const button = document.createElement('button');
                    button.innerHTML = btn.icon;
                    button.title = btn.title;
                    button.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        // Handle custom commands
                        if (btn.custom) {
                            if (btn.command === 'convertTranslation') {
                                openTranslationConvertModal(element);
                                return;
                            }
                        }

                        if (btn.needsInput && btn.command === 'createLink') {
                            const url = prompt('Enter URL:');
                            if (url) {
                                document.execCommand(btn.command, false, url);
                            }
                        } else if (btn.value) {
                            document.execCommand(btn.command, false, btn.value);
                        } else {
                            document.execCommand(btn.command, false, null);
                        }

                        // Check if button should be active
                        updateToolbarState();
                        element.focus();
                    };
                    toolbar.appendChild(button);
                }
            });

            // Save button
            const saveBtn = document.createElement('button');
            saveBtn.innerHTML = '✓ Save';
            saveBtn.style.background = '#0066ff';
            saveBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                saveAndCloseEditor(element, toolbar);
            };

            // Cancel button
            const cancelBtn = document.createElement('button');
            cancelBtn.innerHTML = '✕';
            cancelBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                element.innerHTML = element.getAttribute('data-cms-original-html');
                saveAndCloseEditor(element, toolbar);
            };

            toolbar.appendChild(saveBtn);
            toolbar.appendChild(cancelBtn);

            // Update toolbar state function
            function updateToolbarState() {
                toolbar.querySelectorAll('button').forEach(btn => {
                    const command = btn.dataset.command;
                    if (command && document.queryCommandState(command)) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
            }

            // Listen for selection changes
            document.addEventListener('selectionchange', updateToolbarState);

            // Position toolbar
            const rect = element.getBoundingClientRect();
            toolbar.style.left = '0';

            return toolbar;
        }

        // Save and close editor
        function saveAndCloseEditor(element, toolbar) {
            const contentId = element.getAttribute('data-cms-id');
            const type = element.getAttribute('data-cms-type') || 'text';

            // For translations, use text content only (not HTML)
            // For other types, use innerHTML to preserve formatting
            const newContent = (type === 'translation') ? element.textContent : element.innerHTML;

            // Debug logging for translations
            if (type === 'translation') {
                console.log('Translation save debug:', {
                    contentId: contentId,
                    textContent: element.textContent,
                    innerHTML: element.innerHTML,
                    newContent: newContent,
                    originalAttr: element.getAttribute('data-cms-original'),
                    translationKey: element.getAttribute('data-translation-key')
                });
            }

            // Remove contenteditable
            element.contentEditable = false;
            element.classList.remove('cms-editing');

            // Remove toolbar
            if (toolbar && toolbar.parentElement) {
                toolbar.parentElement.removeChild(toolbar);
            }

            // Get original content
            const originalContent = element.getAttribute('data-cms-original') || '';

            // Build event detail with all necessary attributes
            const eventDetail = {
                id: contentId,
                content: newContent,
                originalContent: originalContent,
                type: type,
                element: element
            };

            // If this is a translation element, include translation-specific data
            if (type === 'translation') {
                const translationKey = element.getAttribute('data-translation-key');
                const translationFile = element.getAttribute('data-translation-file');

                if (translationKey) {
                    eventDetail.translation_key = translationKey;
                }
                if (translationFile) {
                    eventDetail.translation_file = translationFile;
                }
            }

            // Trigger save event (to be handled by save functionality)
            const event = new CustomEvent('cms:contentChanged', {
                detail: eventDetail
            });
            document.dispatchEvent(event);
        }

        // Close all editors
        function closeAllEditors() {
            document.querySelectorAll('.cms-editing').forEach(element => {
                element.contentEditable = false;
                element.classList.remove('cms-editing');
            });

            document.querySelectorAll('.cms-editor-toolbar').forEach(toolbar => {
                toolbar.remove();
            });
        }

        // Initialize if already in edit mode
        if (document.body.classList.contains('cms-edit-mode')) {
            initializeEditableElements();
        }
    });
})();
</script>
HTML;
    }
}