# Extending Laravel CMS

Learn how to extend Laravel CMS with custom content types, plugins, event hooks, and integrations.

## 🔌 Plugin Architecture

Laravel CMS features a powerful plugin system that allows you to extend functionality without modifying core code.

### Plugin Structure

```
your-plugin/
├── composer.json
├── README.md
├── src/
│   ├── YourPlugin.php
│   ├── ContentTypes/
│   │   └── CustomContentType.php
│   ├── Controllers/
│   │   └── PluginController.php
│   ├── Models/
│   │   └── PluginModel.php
│   ├── Middleware/
│   │   └── PluginMiddleware.php
│   └── Commands/
│       └── PluginCommand.php
├── resources/
│   ├── views/
│   │   └── plugin-view.blade.php
│   ├── js/
│   │   └── plugin.js
│   └── css/
│       └── plugin.css
├── config/
│   └── plugin-config.php
├── database/
│   ├── migrations/
│   └── seeders/
└── tests/
    ├── Unit/
    └── Feature/
```

## 🎨 Creating Custom Content Types

### Basic Content Type

Create a custom content type for special functionality:

```php
<?php

namespace YourNamespace\ContentTypes;

use Webook\LaravelCMS\ContentTypes\BaseContentType;
use Illuminate\Http\Request;

class VideoContentType extends BaseContentType
{
    /**
     * Content type identifier
     */
    public function getType(): string
    {
        return 'video';
    }

    /**
     * Content type name
     */
    public function getName(): string
    {
        return 'Video Content';
    }

    /**
     * Content type description
     */
    public function getDescription(): string
    {
        return 'Embed videos from YouTube, Vimeo, or upload custom videos';
    }

    /**
     * Supported platforms
     */
    public function getSupportedPlatforms(): array
    {
        return ['youtube', 'vimeo', 'upload'];
    }

    /**
     * Render the content
     */
    public function render($value, array $attributes = []): string
    {
        if (empty($value)) {
            return $this->renderPlaceholder($attributes);
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        return view('cms::content-types.video', [
            'data' => $data,
            'attributes' => $attributes
        ])->render();
    }

    /**
     * Render placeholder for empty content
     */
    protected function renderPlaceholder(array $attributes): string
    {
        return '<div class="cms-video-placeholder" ' . $this->attributesToString($attributes) . '>
            <i class="fas fa-video"></i>
            <p>Click to add video</p>
        </div>';
    }

    /**
     * Process the content before saving
     */
    public function process($value, Request $request): array
    {
        if ($request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $path = $file->store('videos', 'public');

            return [
                'type' => 'upload',
                'url' => Storage::url($path),
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'duration' => $this->getVideoDuration($file),
            ];
        }

        if ($request->has('video_url')) {
            $url = $request->input('video_url');
            $platform = $this->detectPlatform($url);

            return [
                'type' => $platform,
                'url' => $url,
                'embed_id' => $this->extractVideoId($url, $platform),
                'thumbnail' => $this->getThumbnail($url, $platform),
            ];
        }

        return $value;
    }

    /**
     * Validate the content
     */
    public function validate($value, Request $request): array
    {
        $rules = [];

        if ($request->hasFile('video_file')) {
            $rules['video_file'] = 'required|mimes:mp4,avi,mov,wmv|max:102400'; // 100MB
        }

        if ($request->has('video_url')) {
            $rules['video_url'] = 'required|url';
        }

        return $rules;
    }

    /**
     * Get the editor interface
     */
    public function getEditorView(): string
    {
        return 'cms::editors.video';
    }

    /**
     * Get editor configuration
     */
    public function getEditorConfig(): array
    {
        return [
            'accepted_formats' => ['mp4', 'avi', 'mov', 'wmv'],
            'max_file_size' => 100 * 1024 * 1024, // 100MB
            'supported_platforms' => $this->getSupportedPlatforms(),
            'thumbnail_sizes' => [
                'small' => [320, 180],
                'medium' => [640, 360],
                'large' => [1280, 720],
            ],
        ];
    }

    /**
     * Detect video platform from URL
     */
    protected function detectPlatform(string $url): string
    {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        }

        if (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        }

        return 'unknown';
    }

    /**
     * Extract video ID from URL
     */
    protected function extractVideoId(string $url, string $platform): ?string
    {
        switch ($platform) {
            case 'youtube':
                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches);
                return $matches[1] ?? null;

            case 'vimeo':
                preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
                return $matches[1] ?? null;

            default:
                return null;
        }
    }

    /**
     * Get video thumbnail
     */
    protected function getThumbnail(string $url, string $platform): ?string
    {
        $videoId = $this->extractVideoId($url, $platform);

        if (!$videoId) {
            return null;
        }

        switch ($platform) {
            case 'youtube':
                return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";

            case 'vimeo':
                // Vimeo requires API call for thumbnail
                return $this->getVimeoThumbnail($videoId);

            default:
                return null;
        }
    }

    /**
     * Get video duration
     */
    protected function getVideoDuration($file): ?int
    {
        // Implementation depends on your media processing library
        // Example with FFMpeg
        try {
            $ffmpeg = \FFMpeg\FFMpeg::create();
            $video = $ffmpeg->open($file->getPathname());
            $duration = $video->getFFProbe()->format($file->getPathname())->get('duration');
            return (int) $duration;
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

### Advanced Content Type with Components

```php
<?php

namespace YourNamespace\ContentTypes;

use Webook\LaravelCMS\ContentTypes\BaseContentType;

class GalleryContentType extends BaseContentType
{
    public function getType(): string
    {
        return 'gallery';
    }

    public function getName(): string
    {
        return 'Image Gallery';
    }

    public function render($value, array $attributes = []): string
    {
        $images = is_string($value) ? json_decode($value, true) : $value;

        if (empty($images)) {
            return $this->renderPlaceholder($attributes);
        }

        return view('cms::content-types.gallery', [
            'images' => $images,
            'attributes' => $attributes,
            'config' => $this->getConfig()
        ])->render();
    }

    public function process($value, Request $request): array
    {
        $gallery = [];

        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $file) {
                $path = $file->store('gallery', 'public');

                $gallery[] = [
                    'url' => Storage::url($path),
                    'alt' => $request->input('alt_' . $file->getClientOriginalName(), ''),
                    'caption' => $request->input('caption_' . $file->getClientOriginalName(), ''),
                    'order' => $request->input('order_' . $file->getClientOriginalName(), 0),
                ];
            }
        }

        // Sort by order
        usort($gallery, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $gallery;
    }

    public function getEditorConfig(): array
    {
        return [
            'max_images' => 20,
            'thumbnail_size' => [200, 150],
            'allowed_formats' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'sortable' => true,
            'bulk_upload' => true,
        ];
    }

    protected function getConfig(): array
    {
        return [
            'layout' => 'grid', // grid, carousel, masonry
            'columns' => 3,
            'spacing' => 10,
            'lightbox' => true,
            'lazy_load' => true,
        ];
    }
}
```

### Registering Content Types

Register your content types in a service provider:

```php
<?php

namespace YourNamespace\Providers;

use Illuminate\Support\ServiceProvider;
use Webook\LaravelCMS\ContentTypes\ContentTypeRegistry;
use YourNamespace\ContentTypes\VideoContentType;
use YourNamespace\ContentTypes\GalleryContentType;

class CMSExtensionServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $registry = $this->app->make(ContentTypeRegistry::class);

        $registry->register('video', VideoContentType::class);
        $registry->register('gallery', GalleryContentType::class);
    }
}
```

## 🎣 Event Hooks

Laravel CMS fires events that you can hook into for custom functionality.

### Available Events

```php
<?php

namespace Webook\LaravelCMS\Events;

// Content Events
class ContentCreated
{
    public $key;
    public $value;
    public $language;
    public $user;
}

class ContentUpdated
{
    public $key;
    public $value;
    public $previousValue;
    public $language;
    public $user;
}

class ContentDeleted
{
    public $key;
    public $language;
    public $user;
}

// Translation Events
class TranslationCreated
class TranslationUpdated
class TranslationDeleted

// File Events
class FileUploaded
class FileDeleted

// User Events
class UserLoggedIn
class UserLoggedOut

// System Events
class CacheCleared
class BackupCreated
```

### Creating Event Listeners

```php
<?php

namespace App\Listeners;

use Webook\LaravelCMS\Events\ContentUpdated;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContentUpdateNotification;

class NotifyContentUpdate
{
    public function handle(ContentUpdated $event)
    {
        // Send notification email
        if ($this->shouldNotify($event->key)) {
            Mail::to('admin@example.com')->send(
                new ContentUpdateNotification($event)
            );
        }

        // Log activity
        activity()
            ->performedOn(new \stdClass())
            ->causedBy($event->user)
            ->withProperties([
                'key' => $event->key,
                'language' => $event->language,
                'previous_value' => $event->previousValue,
                'new_value' => $event->value,
            ])
            ->log('content_updated');

        // Clear related caches
        $this->clearRelatedCaches($event->key);

        // Update search index
        $this->updateSearchIndex($event);
    }

    protected function shouldNotify(string $key): bool
    {
        $notifyPatterns = [
            'page.*',
            'nav.*',
            'footer.*'
        ];

        foreach ($notifyPatterns as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    protected function clearRelatedCaches(string $key): void
    {
        $prefix = explode('.', $key)[0];

        Cache::tags(['cms', $prefix])->flush();
    }

    protected function updateSearchIndex(ContentUpdated $event): void
    {
        // Update search index (example with Laravel Scout)
        if (class_exists('\Laravel\Scout\Searchable')) {
            \App\Models\SearchableContent::updateOrCreate(
                ['key' => $event->key, 'language' => $event->language],
                ['content' => $event->value]
            );
        }
    }
}
```

### Registering Event Listeners

```php
<?php

// app/Providers/EventServiceProvider.php

protected $listen = [
    'Webook\LaravelCMS\Events\ContentUpdated' => [
        'App\Listeners\NotifyContentUpdate',
        'App\Listeners\ClearContentCache',
        'App\Listeners\UpdateSitemap',
    ],
    'Webook\LaravelCMS\Events\TranslationUpdated' => [
        'App\Listeners\InvalidateTranslationCache',
    ],
    'Webook\LaravelCMS\Events\FileUploaded' => [
        'App\Listeners\ProcessUploadedFile',
        'App\Listeners\ScanForMalware',
    ],
];
```

## 🔧 Creating Plugins

### Plugin Base Class

```php
<?php

namespace YourNamespace;

use Illuminate\Support\ServiceProvider;
use Webook\LaravelCMS\Contracts\PluginInterface;

abstract class BasePlugin extends ServiceProvider implements PluginInterface
{
    /**
     * Plugin name
     */
    abstract public function getName(): string;

    /**
     * Plugin version
     */
    abstract public function getVersion(): string;

    /**
     * Plugin description
     */
    abstract public function getDescription(): string;

    /**
     * Plugin author
     */
    abstract public function getAuthor(): string;

    /**
     * Plugin dependencies
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Check if plugin is compatible
     */
    public function isCompatible(): bool
    {
        return version_compare(app('cms.version'), $this->getMinimumCMSVersion(), '>=');
    }

    /**
     * Minimum CMS version required
     */
    public function getMinimumCMSVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Plugin configuration
     */
    public function getConfig(): array
    {
        return [];
    }

    /**
     * Install plugin
     */
    public function install(): bool
    {
        try {
            $this->publishAssets();
            $this->runMigrations();
            $this->seedData();
            return true;
        } catch (\Exception $e) {
            \Log::error('Plugin installation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Uninstall plugin
     */
    public function uninstall(): bool
    {
        try {
            $this->rollbackMigrations();
            $this->cleanupAssets();
            $this->cleanupData();
            return true;
        } catch (\Exception $e) {
            \Log::error('Plugin uninstallation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish plugin assets
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/js' => public_path('vendor/' . $this->getSlug() . '/js'),
            __DIR__ . '/../resources/css' => public_path('vendor/' . $this->getSlug() . '/css'),
        ], $this->getSlug() . '-assets');
    }

    /**
     * Get plugin slug
     */
    protected function getSlug(): string
    {
        return str_slug($this->getName());
    }
}
```

### SEO Plugin Example

```php
<?php

namespace YourNamespace\Plugins;

use YourNamespace\BasePlugin;
use Webook\LaravelCMS\Events\ContentUpdated;
use YourNamespace\Listeners\UpdateSitemap;
use YourNamespace\Listeners\GenerateMetaTags;

class SEOPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'SEO Optimizer';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Automatic SEO optimization for CMS content including meta tags, sitemaps, and structured data.';
    }

    public function getAuthor(): string
    {
        return 'Your Company';
    }

    public function boot()
    {
        // Register event listeners
        $this->app['events']->listen(ContentUpdated::class, UpdateSitemap::class);
        $this->app['events']->listen(ContentUpdated::class, GenerateMetaTags::class);

        // Register content types
        $this->registerContentTypes();

        // Register middleware
        $this->registerMiddleware();

        // Register view composers
        $this->registerViewComposers();

        // Register commands
        $this->registerCommands();
    }

    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/seo.php', 'cms-seo');

        // Register services
        $this->app->singleton('cms.seo', function ($app) {
            return new \YourNamespace\Services\SEOService($app['config']['cms-seo']);
        });
    }

    protected function registerContentTypes(): void
    {
        $registry = $this->app->make(\Webook\LaravelCMS\ContentTypes\ContentTypeRegistry::class);

        $registry->register('meta', \YourNamespace\ContentTypes\MetaContentType::class);
        $registry->register('schema', \YourNamespace\ContentTypes\SchemaContentType::class);
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->pushMiddlewareToGroup('web', \YourNamespace\Middleware\SEOMiddleware::class);
    }

    protected function registerViewComposers(): void
    {
        view()->composer('*', \YourNamespace\ViewComposers\SEOComposer::class);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \YourNamespace\Commands\GenerateSitemap::class,
                \YourNamespace\Commands\AnalyzeSEO::class,
            ]);
        }
    }

    public function getConfig(): array
    {
        return [
            'generate_sitemap' => true,
            'auto_meta_tags' => true,
            'structured_data' => true,
            'social_meta' => true,
            'performance_hints' => true,
        ];
    }
}
```

### Analytics Plugin Example

```php
<?php

namespace YourNamespace\Plugins;

use YourNamespace\BasePlugin;

class AnalyticsPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'CMS Analytics';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Track content performance, user engagement, and editor activity.';
    }

    public function getAuthor(): string
    {
        return 'Your Company';
    }

    public function boot()
    {
        // Register routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Register views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cms-analytics');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../resources/js' => public_path('vendor/cms-analytics/js'),
            __DIR__ . '/../resources/css' => public_path('vendor/cms-analytics/css'),
        ], 'cms-analytics-assets');

        // Register event listeners
        $this->registerEventListeners();

        // Add dashboard widgets
        $this->registerDashboardWidgets();
    }

    public function register()
    {
        // Register analytics service
        $this->app->singleton('cms.analytics', function ($app) {
            return new \YourNamespace\Services\AnalyticsService();
        });

        // Register data collectors
        $this->app->singleton('cms.analytics.collectors', function ($app) {
            return new \YourNamespace\Services\DataCollectionService();
        });
    }

    protected function registerEventListeners(): void
    {
        $events = $this->app['events'];

        $events->listen('Webook\LaravelCMS\Events\ContentUpdated', function ($event) {
            app('cms.analytics.collectors')->recordContentUpdate($event);
        });

        $events->listen('Webook\LaravelCMS\Events\ContentViewed', function ($event) {
            app('cms.analytics.collectors')->recordContentView($event);
        });
    }

    protected function registerDashboardWidgets(): void
    {
        app('cms.dashboard')->addWidget('analytics-overview', [
            'title' => 'Analytics Overview',
            'view' => 'cms-analytics::widgets.overview',
            'data' => function () {
                return app('cms.analytics')->getOverviewData();
            },
            'position' => 1,
        ]);
    }
}
```

## 🎛️ Custom Middleware

Create middleware for custom CMS functionality:

```php
<?php

namespace YourNamespace\Middleware;

use Closure;
use Illuminate\Http\Request;

class CMSAnalyticsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Track page views for CMS content
        if ($request->route() && $this->shouldTrack($request)) {
            $this->trackPageView($request, $response);
        }

        return $response;
    }

    protected function shouldTrack(Request $request): bool
    {
        // Only track GET requests
        if (!$request->isMethod('GET')) {
            return false;
        }

        // Skip AJAX requests
        if ($request->ajax()) {
            return false;
        }

        // Skip admin/API routes
        if ($request->is('cms/*') || $request->is('api/*')) {
            return false;
        }

        return true;
    }

    protected function trackPageView(Request $request, $response): void
    {
        // Collect analytics data
        $data = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'response_time' => $this->getResponseTime(),
            'status_code' => $response->getStatusCode(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'cms_content' => $this->extractCMSContent($response),
        ];

        // Store analytics data
        app('cms.analytics.collectors')->recordPageView($data);
    }

    protected function extractCMSContent($response): array
    {
        $content = $response->getContent();
        $cmsContent = [];

        // Extract CMS content keys from response
        preg_match_all('/data-cms-[\w]+="([^"]+)"/', $content, $matches);

        if (!empty($matches[1])) {
            $cmsContent = array_unique($matches[1]);
        }

        return $cmsContent;
    }

    protected function getResponseTime(): float
    {
        return microtime(true) - LARAVEL_START;
    }
}
```

## 🎨 Custom Editor Components

Create custom editor interfaces:

```php
<?php

namespace YourNamespace\EditorComponents;

use Webook\LaravelCMS\EditorComponents\BaseEditorComponent;

class ColorPickerComponent extends BaseEditorComponent
{
    public function getName(): string
    {
        return 'color-picker';
    }

    public function getView(): string
    {
        return 'cms-extensions::editor.color-picker';
    }

    public function getAssets(): array
    {
        return [
            'js' => [
                '/vendor/cms-extensions/js/color-picker.js'
            ],
            'css' => [
                '/vendor/cms-extensions/css/color-picker.css'
            ]
        ];
    }

    public function getConfig(): array
    {
        return [
            'palette' => [
                '#FF0000', '#00FF00', '#0000FF',
                '#FFFF00', '#FF00FF', '#00FFFF',
                '#000000', '#FFFFFF', '#808080'
            ],
            'allow_custom' => true,
            'format' => 'hex' // hex, rgb, hsl
        ];
    }
}
```

## 🔍 Custom Search Providers

Integrate with search engines:

```php
<?php

namespace YourNamespace\Search;

use Webook\LaravelCMS\Contracts\SearchProviderInterface;

class ElasticsearchProvider implements SearchProviderInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new \Elasticsearch\Client([
            'hosts' => [config('services.elasticsearch.host')]
        ]);
    }

    public function index(string $key, string $content, string $language = 'en'): bool
    {
        try {
            $this->client->index([
                'index' => 'cms_content',
                'id' => $key . '_' . $language,
                'body' => [
                    'key' => $key,
                    'content' => $content,
                    'language' => $language,
                    'indexed_at' => now()->toISOString()
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Elasticsearch indexing failed: ' . $e->getMessage());
            return false;
        }
    }

    public function search(string $query, string $language = null, int $limit = 10): array
    {
        $params = [
            'index' => 'cms_content',
            'body' => [
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['content^2', 'key']
                    ]
                ],
                'size' => $limit
            ]
        ];

        if ($language) {
            $params['body']['query'] = [
                'bool' => [
                    'must' => $params['body']['query'],
                    'filter' => [
                        'term' => ['language' => $language]
                    ]
                ]
            ];
        }

        try {
            $response = $this->client->search($params);

            return array_map(function ($hit) {
                return [
                    'key' => $hit['_source']['key'],
                    'content' => $hit['_source']['content'],
                    'language' => $hit['_source']['language'],
                    'score' => $hit['_score']
                ];
            }, $response['hits']['hits']);
        } catch (\Exception $e) {
            \Log::error('Elasticsearch search failed: ' . $e->getMessage());
            return [];
        }
    }

    public function delete(string $key, string $language = 'en'): bool
    {
        try {
            $this->client->delete([
                'index' => 'cms_content',
                'id' => $key . '_' . $language
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Elasticsearch deletion failed: ' . $e->getMessage());
            return false;
        }
    }
}
```

## 🔧 Custom Commands

Create Artisan commands for CMS operations:

```php
<?php

namespace YourNamespace\Commands;

use Illuminate\Console\Command;
use Webook\LaravelCMS\Services\ContentService;

class OptimizeContentCommand extends Command
{
    protected $signature = 'cms:optimize
                           {--type=* : Content types to optimize}
                           {--language=* : Languages to process}
                           {--dry-run : Show what would be optimized without making changes}';

    protected $description = 'Optimize CMS content for performance and SEO';

    protected $contentService;

    public function __construct(ContentService $contentService)
    {
        parent::__construct();
        $this->contentService = $contentService;
    }

    public function handle()
    {
        $this->info('🚀 Starting CMS content optimization...');

        $types = $this->option('type') ?: ['text', 'rich', 'image'];
        $languages = $this->option('language') ?: ['en'];
        $dryRun = $this->option('dry-run');

        $optimized = 0;
        $errors = 0;

        foreach ($types as $type) {
            $this->line("Processing {$type} content...");

            $content = $this->contentService->getByType($type);
            $bar = $this->output->createProgressBar($content->count());

            foreach ($content as $item) {
                try {
                    if ($this->optimizeContent($item, $dryRun)) {
                        $optimized++;
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to optimize {$item->key}: " . $e->getMessage());
                    $errors++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->info("✅ Optimization complete!");
        $this->table(['Metric', 'Count'], [
            ['Optimized', $optimized],
            ['Errors', $errors],
            ['Mode', $dryRun ? 'Dry Run' : 'Live'],
        ]);
    }

    protected function optimizeContent($content, bool $dryRun): bool
    {
        $optimizations = [];

        // Optimize images
        if ($content->type === 'image') {
            $optimizations[] = $this->optimizeImage($content);
        }

        // Optimize rich text
        if ($content->type === 'rich') {
            $optimizations[] = $this->optimizeRichText($content);
        }

        // Apply optimizations
        if (!$dryRun && !empty(array_filter($optimizations))) {
            return $this->contentService->update($content->key, $content->value);
        }

        return !empty(array_filter($optimizations));
    }

    protected function optimizeImage($content): bool
    {
        // Image optimization logic
        return true;
    }

    protected function optimizeRichText($content): bool
    {
        // Rich text optimization logic
        return true;
    }
}
```

## 📦 Publishing Your Plugin

### Package Configuration

```json
{
    "name": "your-vendor/laravel-cms-plugin",
    "description": "Custom plugin for Laravel CMS",
    "type": "laravel-package",
    "keywords": ["laravel", "cms", "plugin"],
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your@email.com"
        }
    ],
    "require": {
        "php": "^8.1",
        "laravel/framework": "^9.0|^10.0",
        "webook/laravel-cms": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\LaravelCMSPlugin\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "YourVendor\\LaravelCMSPlugin\\PluginServiceProvider"
            ]
        },
        "cms-plugin": {
            "name": "Your Plugin Name",
            "version": "1.0.0",
            "description": "Plugin description",
            "author": "Your Name",
            "compatibility": "^2.0"
        }
    }
}
```

### Service Provider Registration

```php
<?php

namespace YourVendor\LaravelCMSPlugin;

use Illuminate\Support\ServiceProvider;
use Webook\LaravelCMS\PluginManager;

class PluginServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register plugin
        app(PluginManager::class)->register(YourPlugin::class);

        // Publish assets
        $this->publishes([
            __DIR__ . '/../config/plugin.php' => config_path('cms-plugins/your-plugin.php'),
        ], 'cms-plugin-config');

        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/your-plugin'),
        ], 'cms-plugin-assets');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/plugin.php', 'cms-plugins.your-plugin');
    }
}
```

## 📚 Testing Your Extensions

### Unit Tests

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use YourNamespace\ContentTypes\VideoContentType;

class VideoContentTypeTest extends TestCase
{
    protected $contentType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentType = new VideoContentType();
    }

    public function test_can_detect_youtube_videos()
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $platform = $this->contentType->detectPlatform($url);

        $this->assertEquals('youtube', $platform);
    }

    public function test_can_extract_video_id()
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $videoId = $this->contentType->extractVideoId($url, 'youtube');

        $this->assertEquals('dQw4w9WgXcQ', $videoId);
    }

    public function test_renders_placeholder_for_empty_content()
    {
        $html = $this->contentType->render(null, ['data-cms-video' => 'test.video']);

        $this->assertStringContainsString('cms-video-placeholder', $html);
        $this->assertStringContainsString('Click to add video', $html);
    }
}
```

### Feature Tests

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;

class VideoContentTypeFeatureTest extends TestCase
{
    public function test_can_upload_video_file()
    {
        $this->actingAs($this->createUser(['cms:edit']));

        $file = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');

        $response = $this->post('/cms/api/content/video', [
            'key' => 'test.video',
            'video_file' => $file,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('cms_content', [
            'key' => 'test.video',
            'type' => 'video',
        ]);
    }

    public function test_can_embed_youtube_video()
    {
        $this->actingAs($this->createUser(['cms:edit']));

        $response = $this->post('/cms/api/content/video', [
            'key' => 'test.video',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.type', 'youtube');
        $response->assertJsonPath('data.embed_id', 'dQw4w9WgXcQ');
    }
}
```

## 📞 Next Steps

Now that you can extend Laravel CMS:

1. **Secure Your Extensions**: [Security Guide](security.md)
2. **Deploy Custom Features**: [Deployment Guide](deployment.md)
3. **Get Help**: [Troubleshooting Guide](troubleshooting.md)
4. **Share Your Plugin**: [Community Guidelines](../CONTRIBUTING.md)

---

**Building something cool?** Share it with the [Laravel CMS community](https://discord.gg/laravel-cms) and help others extend their CMS!