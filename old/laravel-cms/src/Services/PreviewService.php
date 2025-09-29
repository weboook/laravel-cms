<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Preview Service
 *
 * Handles fetching content from URLs and injecting CMS editing capabilities
 * for preview and editing purposes.
 *
 * @package Webook\LaravelCMS\Services
 */
class PreviewService
{
    protected int $timeout = 30;
    protected int $maxRedirects = 5;
    protected array $defaultHeaders = [
        'User-Agent' => 'Laravel CMS Preview/1.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept-Encoding' => 'gzip, deflate',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
    ];

    /**
     * Fetch content from a URL with authentication and locale support.
     *
     * @param string $url The URL to fetch
     * @param array $options Additional options for the request
     * @return string The fetched content
     * @throws Exception If the request fails
     */
    public function fetchContent(string $url, array $options = []): string
    {
        try {
            // Prepare request headers
            $headers = array_merge($this->defaultHeaders, $options['headers'] ?? []);

            // Add locale-specific headers if provided
            if (isset($options['locale'])) {
                $headers['Accept-Language'] = $this->getAcceptLanguageHeader($options['locale']);
            }

            // Add user context if available
            if (isset($options['user'])) {
                $headers['X-CMS-User'] = $options['user']->id;
                $headers['X-CMS-Preview'] = 'true';
            }

            Log::info('Fetching preview content', [
                'url' => $url,
                'locale' => $options['locale'] ?? 'default',
                'user_id' => $options['user']->id ?? null,
            ]);

            // Make the HTTP request
            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => $this->maxRedirects,
                        'strict' => true,
                        'referer' => true,
                        'protocols' => ['http', 'https'],
                    ],
                    'verify' => config('cms.security.verify_ssl', true),
                ])
                ->get($url);

            if (!$response->successful()) {
                throw new Exception("Failed to fetch content: HTTP {$response->status()}");
            }

            $content = $response->body();

            // Validate content is HTML
            if (!$this->isHtmlContent($content)) {
                throw new Exception('Retrieved content is not valid HTML');
            }

            Log::info('Preview content fetched successfully', [
                'url' => $url,
                'content_length' => strlen($content),
                'status' => $response->status(),
            ]);

            return $content;

        } catch (Exception $e) {
            Log::error('Failed to fetch preview content', [
                'url' => $url,
                'error' => $e->getMessage(),
                'user_id' => $options['user']->id ?? null,
            ]);

            throw new Exception("Preview fetch failed: {$e->getMessage()}");
        }
    }

    /**
     * Inject editing markers into HTML content.
     *
     * @param string $content The HTML content to modify
     * @param array $options Configuration options for injection
     * @return string The modified content with editing markers
     */
    public function injectEditingMarkers(string $content, array $options = []): string
    {
        try {
            // Don't inject if not in edit mode
            if (!($options['edit_mode'] ?? false)) {
                return $content;
            }

            $url = $options['url'] ?? '';
            $locale = $options['locale'] ?? 'en';

            Log::info('Injecting editing markers', [
                'url' => $url,
                'locale' => $locale,
                'content_length' => strlen($content),
            ]);

            // Add CMS toolbar and scripts
            $content = $this->injectCMSToolbar($content, $options);

            // Add editable markers to content elements
            $content = $this->addEditableMarkers($content, $options);

            // Add CMS JavaScript and CSS
            $content = $this->injectCMSAssets($content, $options);

            return $content;

        } catch (Exception $e) {
            Log::error('Failed to inject editing markers', [
                'error' => $e->getMessage(),
                'url' => $options['url'] ?? 'unknown',
            ]);

            // Return original content if injection fails
            return $content;
        }
    }

    /**
     * Get the Accept-Language header for a locale.
     *
     * @param string $locale
     * @return string
     */
    protected function getAcceptLanguageHeader(string $locale): string
    {
        $localeMap = [
            'en' => 'en-US,en;q=0.9',
            'es' => 'es-ES,es;q=0.9,en;q=0.8',
            'fr' => 'fr-FR,fr;q=0.9,en;q=0.8',
            'de' => 'de-DE,de;q=0.9,en;q=0.8',
        ];

        return $localeMap[$locale] ?? "en-US,en;q=0.9";
    }

    /**
     * Check if content is valid HTML.
     *
     * @param string $content
     * @return bool
     */
    protected function isHtmlContent(string $content): bool
    {
        // Check for basic HTML structure
        return str_contains($content, '<html') ||
               str_contains($content, '<!DOCTYPE') ||
               str_contains($content, '<head') ||
               str_contains($content, '<body');
    }

    /**
     * Inject CMS toolbar into the content.
     *
     * @param string $content
     * @param array $options
     * @return string
     */
    protected function injectCMSToolbar(string $content, array $options): string
    {
        $toolbar = $this->generateToolbarHtml($options);

        // Try to inject before closing body tag
        if (str_contains($content, '</body>')) {
            $content = str_replace('</body>', $toolbar . '</body>', $content);
        } else {
            // Append to end if no body tag found
            $content .= $toolbar;
        }

        return $content;
    }

    /**
     * Generate the CMS toolbar HTML.
     *
     * @param array $options
     * @return string
     */
    protected function generateToolbarHtml(array $options): string
    {
        $locale = $options['locale'] ?? 'en';
        $url = $options['url'] ?? '';

        return '
        <div class="cms-toolbar" id="cms-toolbar" style="display: none;">
            <div class="cms-toolbar-left">
                <div class="webflow-brand">CMS Editor</div>
                <div class="cms-toolbar-divider"></div>
                <button class="webflow-btn" id="cms-edit-toggle">
                    <i class="fas fa-edit"></i> Edit Mode
                </button>
                <button class="webflow-btn" id="cms-save-btn" style="display: none;">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>

            <div class="cms-toolbar-center">
                <div class="webflow-toggle">
                    <div class="webflow-toggle-option active" data-mode="preview">Preview</div>
                    <div class="webflow-toggle-option" data-mode="edit">Edit</div>
                </div>
            </div>

            <div class="cms-toolbar-right">
                <button class="webflow-btn" id="cms-media-btn">
                    <i class="fas fa-images"></i> Media
                </button>
                <div class="cms-toolbar-divider"></div>
                <button class="webflow-btn" id="cms-close-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>

        <style>
        .cms-toolbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 10000;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-top: 1px solid #333;
            padding: 12px 20px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cms-toolbar-left,
        .cms-toolbar-center,
        .cms-toolbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cms-toolbar-center {
            flex: 1;
            justify-content: center;
        }

        .webflow-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            padding: 8px 16px;
            color: #ffffff;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .webflow-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .webflow-btn.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }

        .webflow-toggle {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 4px;
            display: flex;
            align-items: center;
        }

        .webflow-toggle-option {
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .webflow-toggle-option.active {
            background: #007bff;
            color: white;
        }

        .webflow-brand {
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .webflow-brand::before {
            content: "🎨";
            font-size: 16px;
        }

        .cms-toolbar-divider {
            width: 1px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            margin: 0 8px;
        }

        body.cms-toolbar-active {
            padding-bottom: 70px;
        }
        </style>
        ';
    }

    /**
     * Add editable markers to content elements.
     *
     * @param string $content
     * @param array $options
     * @return string
     */
    protected function addEditableMarkers(string $content, array $options): string
    {
        // Add CMS editable class to common elements
        $patterns = [
            // Headings
            '/<(h[1-6])([^>]*?)>(.*?)<\/\1>/i' => '<$1$2 class="cms-editable" data-field="heading">$3</$1>',

            // Paragraphs
            '/<(p)([^>]*?)>(.*?)<\/\1>/i' => '<$1$2 class="cms-editable" data-field="paragraph">$3</$1>',

            // Divs with content
            '/<(div)([^>]*?)>((?:[^<]|<(?!\/div>))*?)<\/\1>/i' => '<$1$2 class="cms-editable" data-field="content">$3</$1>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Inject CMS JavaScript and CSS assets.
     *
     * @param string $content
     * @param array $options
     * @return string
     */
    protected function injectCMSAssets(string $content, array $options): string
    {
        // CSS assets
        $css = '
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="' . asset('vendor/cms/css/editor.css') . '" rel="stylesheet">
        ';

        // JavaScript assets
        $js = '
        <script src="' . asset('vendor/cms/js/editor.js') . '"></script>
        <script>
        window.cms = window.cms || {};
        window.cms.token = "' . csrf_token() . '";
        window.cms.locale = "' . ($options['locale'] ?? 'en') . '";
        window.cms.url = "' . ($options['url'] ?? '') . '";
        window.cms.editMode = ' . (($options['edit_mode'] ?? false) ? 'true' : 'false') . ';

        document.addEventListener("DOMContentLoaded", function() {
            // Initialize CMS editor
            if (window.CMSEditor) {
                window.cmsEditor = new window.CMSEditor();
            }

            // Show toolbar
            const toolbar = document.getElementById("cms-toolbar");
            if (toolbar) {
                toolbar.style.display = "flex";
                document.body.classList.add("cms-toolbar-active");
            }
        });
        </script>
        ';

        // Inject CSS into head
        if (str_contains($content, '</head>')) {
            $content = str_replace('</head>', $css . '</head>', $content);
        } else {
            $content = $css . $content;
        }

        // Inject JS before closing body
        if (str_contains($content, '</body>')) {
            $content = str_replace('</body>', $js . '</body>', $content);
        } else {
            $content .= $js;
        }

        return $content;
    }

    /**
     * Cache content for faster subsequent requests.
     *
     * @param string $key
     * @param string $content
     * @param int $minutes
     */
    public function cacheContent(string $key, string $content, int $minutes = 5): void
    {
        Cache::put("preview:{$key}", $content, now()->addMinutes($minutes));
    }

    /**
     * Get cached content.
     *
     * @param string $key
     * @return string|null
     */
    public function getCachedContent(string $key): ?string
    {
        return Cache::get("preview:{$key}");
    }

    /**
     * Clear preview cache.
     *
     * @param string|null $key
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget("preview:{$key}");
        } else {
            Cache::flush(); // This is aggressive - in production you'd want to be more selective
        }
    }
}