# Configuration Guide

Complete reference for configuring Laravel CMS to fit your specific needs.

## 📁 Configuration Files

Laravel CMS uses multiple configuration files:

| File | Purpose | Auto-Published |
|------|---------|----------------|
| `config/cms.php` | Main CMS configuration | Yes |
| `config/cms-auth.php` | Authentication settings | Optional |
| `config/cms-cache.php` | Caching configuration | Optional |
| `config/cms-locales.php` | Language settings | Optional |

## ⚙️ Environment Variables

Add these variables to your `.env` file:

### Core Settings
```env
# Enable/disable CMS functionality
CMS_ENABLED=true

# Route configuration
CMS_ROUTE_PREFIX=cms
CMS_MIDDLEWARE=web,auth

# Storage settings
CMS_STORAGE_DISK=local
CMS_CONTENT_PATH=content
CMS_TRANSLATIONS_PATH=lang
CMS_MEDIA_PATH=media

# Cache configuration
CMS_CACHE_DRIVER=file
CMS_CACHE_TTL=3600
CMS_CACHE_PREFIX=cms_
```

### Authentication & Security
```env
# Authentication
CMS_AUTH_ENABLED=true
CMS_AUTH_GUARD=web
CMS_AUTH_MIDDLEWARE=auth

# Security settings
CMS_SANITIZE_HTML=true
CMS_ALLOWED_TAGS="p,br,strong,em,u,h1,h2,h3,h4,h5,h6,ul,ol,li,a,img"
CMS_MAX_FILE_SIZE=10240
CMS_ALLOWED_EXTENSIONS=jpg,jpeg,png,gif,svg,pdf,doc,docx

# CSRF and XSS protection
CMS_CSRF_PROTECTION=true
CMS_XSS_PROTECTION=true
```

### Performance & Optimization
```env
# Performance settings
CMS_MINIFY_HTML=true
CMS_LAZY_LOAD=true
CMS_COMPRESS_ASSETS=true
CMS_OPTIMIZE_IMAGES=true

# CDN settings
CMS_CDN_ENABLED=false
CMS_CDN_URL=
CMS_CDN_PATH=cms-assets
```

### Multi-language Support
```env
# Localization
CMS_DEFAULT_LOCALE=en
CMS_FALLBACK_LOCALE=en
CMS_SUPPORTED_LOCALES=en,es,fr,de
CMS_AUTO_DETECT_LOCALE=true
```

### External Services
```env
# Google Translate API
CMS_GOOGLE_TRANSLATE_KEY=

# AWS S3 (for file storage)
CMS_AWS_ACCESS_KEY_ID=
CMS_AWS_SECRET_ACCESS_KEY=
CMS_AWS_DEFAULT_REGION=us-east-1
CMS_AWS_BUCKET=

# Cloudinary (for image processing)
CMS_CLOUDINARY_URL=

# Analytics
CMS_GOOGLE_ANALYTICS_ID=
CMS_ANALYTICS_ENABLED=true
```

### Development & Debugging
```env
# Debug settings
CMS_DEBUG=false
CMS_LOG_LEVEL=info
CMS_LOG_QUERIES=false

# Development tools
CMS_SHOW_TOOLBAR=false
CMS_PROFILING=false
```

## 🎛️ Main Configuration (config/cms.php)

### Basic Configuration

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Status
    |--------------------------------------------------------------------------
    */
    'enabled' => env('CMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix' => env('CMS_ROUTE_PREFIX', 'cms'),
        'middleware' => explode(',', env('CMS_MIDDLEWARE', 'web')),
        'name' => 'cms.',
        'domain' => env('CMS_DOMAIN', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => env('CMS_STORAGE_DISK', 'local'),
        'content_path' => env('CMS_CONTENT_PATH', 'content'),
        'translations_path' => env('CMS_TRANSLATIONS_PATH', 'lang'),
        'media_path' => env('CMS_MEDIA_PATH', 'media'),
        'cache_path' => env('CMS_CACHE_PATH', 'cache'),
        'backup_path' => env('CMS_BACKUP_PATH', 'backups'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'driver' => env('CMS_CACHE_DRIVER', 'file'),
        'ttl' => env('CMS_CACHE_TTL', 3600),
        'prefix' => env('CMS_CACHE_PREFIX', 'cms_'),
        'tags' => ['cms', 'content', 'translations'],
        'serialize' => true,
    ],
];
```

### Authentication Configuration

```php
'authentication' => [
    'enabled' => env('CMS_AUTH_ENABLED', true),
    'guard' => env('CMS_AUTH_GUARD', 'web'),
    'middleware' => env('CMS_AUTH_MIDDLEWARE', 'auth'),

    'permissions' => [
        'view_cms' => 'cms:view',
        'edit_content' => 'cms:edit',
        'manage_translations' => 'cms:translate',
        'upload_files' => 'cms:upload',
        'admin_access' => 'cms:admin',
        'manage_users' => 'cms:users',
    ],

    'roles' => [
        'cms_admin' => [
            'cms:view', 'cms:edit', 'cms:translate',
            'cms:upload', 'cms:admin', 'cms:users'
        ],
        'cms_editor' => [
            'cms:view', 'cms:edit', 'cms:upload'
        ],
        'cms_translator' => [
            'cms:view', 'cms:translate'
        ],
    ],

    'login_redirect' => '/cms/dashboard',
    'logout_redirect' => '/',
],
```

### Content Editor Configuration

```php
'editor' => [
    'enabled' => true,
    'default_toolbar' => 'standard',

    'toolbars' => [
        'basic' => 'bold italic | bullist numlist',
        'standard' => 'bold italic underline | bullist numlist | link unlink',
        'advanced' => 'bold italic underline strikethrough | h1 h2 h3 | bullist numlist | link unlink image | table | code',
        'full' => 'undo redo | formatselect fontselect fontsizeselect | bold italic underline strikethrough | forecolor backcolor | h1 h2 h3 h4 h5 h6 | bullist numlist | outdent indent | link unlink image media | table | code | fullscreen preview',
    ],

    'plugins' => [
        'basic' => ['lists', 'link'],
        'standard' => ['lists', 'link', 'image'],
        'advanced' => ['lists', 'link', 'image', 'table', 'code'],
        'full' => ['lists', 'link', 'image', 'media', 'table', 'code', 'fullscreen', 'preview'],
    ],

    'skin' => env('CMS_EDITOR_SKIN', 'oxide'),
    'height' => env('CMS_EDITOR_HEIGHT', 300),
    'resize' => env('CMS_EDITOR_RESIZE', true),

    'custom_css' => public_path('css/editor-content.css'),
    'body_class' => 'cms-editor-content',
],
```

### Security Configuration

```php
'security' => [
    'sanitize_html' => env('CMS_SANITIZE_HTML', true),
    'allowed_tags' => explode(',', env('CMS_ALLOWED_TAGS', 'p,br,strong,em,u,h1,h2,h3,h4,h5,h6,ul,ol,li,a,img')),
    'allowed_attributes' => [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class'],
        '*' => ['class', 'id', 'style'],
    ],

    'file_upload' => [
        'max_size' => env('CMS_MAX_FILE_SIZE', 10240), // KB
        'allowed_extensions' => explode(',', env('CMS_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,svg,pdf,doc,docx')),
        'blocked_extensions' => ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'php'],
        'scan_uploads' => env('CMS_SCAN_UPLOADS', true),
        'quarantine_path' => storage_path('cms/quarantine'),
    ],

    'csrf_protection' => env('CMS_CSRF_PROTECTION', true),
    'xss_protection' => env('CMS_XSS_PROTECTION', true),
    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],
],
```

### Performance Configuration

```php
'performance' => [
    'minify_html' => env('CMS_MINIFY_HTML', true),
    'lazy_load' => env('CMS_LAZY_LOAD', true),
    'compress_assets' => env('CMS_COMPRESS_ASSETS', true),
    'optimize_images' => env('CMS_OPTIMIZE_IMAGES', true),

    'cache_compiled_views' => true,
    'preload_translations' => true,
    'defer_non_critical_css' => true,

    'image_optimization' => [
        'quality' => 85,
        'format' => 'auto', // auto, webp, jpg, png
        'progressive' => true,
        'strip_metadata' => true,
    ],

    'cdn' => [
        'enabled' => env('CMS_CDN_ENABLED', false),
        'url' => env('CMS_CDN_URL'),
        'path' => env('CMS_CDN_PATH', 'cms-assets'),
        'version' => env('CMS_CDN_VERSION', '1.0'),
    ],
],
```

### Localization Configuration

```php
'localization' => [
    'default_locale' => env('CMS_DEFAULT_LOCALE', 'en'),
    'fallback_locale' => env('CMS_FALLBACK_LOCALE', 'en'),
    'supported_locales' => explode(',', env('CMS_SUPPORTED_LOCALES', 'en')),
    'auto_detect' => env('CMS_AUTO_DETECT_LOCALE', true),

    'locale_detection' => [
        'methods' => ['url', 'session', 'cookie', 'header'],
        'cookie_name' => 'cms_locale',
        'session_key' => 'cms_locale',
        'url_parameter' => 'lang',
    ],

    'translation_services' => [
        'google_translate' => [
            'enabled' => !empty(env('CMS_GOOGLE_TRANSLATE_KEY')),
            'api_key' => env('CMS_GOOGLE_TRANSLATE_KEY'),
            'endpoint' => 'https://translation.googleapis.com/language/translate/v2',
        ],
        'deepl' => [
            'enabled' => !empty(env('CMS_DEEPL_API_KEY')),
            'api_key' => env('CMS_DEEPL_API_KEY'),
            'endpoint' => 'https://api-free.deepl.com/v2/translate',
        ],
    ],
],
```

## 🔐 Authentication Configuration (config/cms-auth.php)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Authentication Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'cms_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'guards' => [
        'cms' => [
            'driver' => 'session',
            'provider' => 'cms_users',
        ],
        'cms_api' => [
            'driver' => 'sanctum',
            'provider' => 'cms_users',
        ],
    ],

    'permissions' => [
        'system' => [
            'cms:admin' => 'Full CMS administration access',
            'cms:config' => 'Modify CMS configuration',
            'cms:users' => 'Manage CMS users',
            'cms:backup' => 'Create and restore backups',
        ],
        'content' => [
            'cms:view' => 'View CMS interface',
            'cms:edit' => 'Edit content',
            'cms:delete' => 'Delete content',
            'cms:publish' => 'Publish/unpublish content',
        ],
        'translation' => [
            'cms:translate' => 'Manage translations',
            'cms:translate:import' => 'Import translations',
            'cms:translate:export' => 'Export translations',
        ],
        'media' => [
            'cms:upload' => 'Upload files',
            'cms:media:manage' => 'Manage media library',
            'cms:media:delete' => 'Delete media files',
        ],
    ],

    'role_hierarchy' => [
        'cms_admin' => ['cms_editor', 'cms_translator'],
        'cms_editor' => ['cms_contributor'],
        'cms_translator' => ['cms_contributor'],
    ],

    'two_factor' => [
        'enabled' => env('CMS_2FA_ENABLED', false),
        'issuer' => env('APP_NAME', 'Laravel CMS'),
        'window' => 30,
    ],
];
```

## 💾 Cache Configuration (config/cms-cache.php)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Cache Configuration
    |--------------------------------------------------------------------------
    */

    'default' => env('CMS_CACHE_DRIVER', 'file'),

    'stores' => [
        'content' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/cms/content'),
            'serializer' => 'php',
        ],
        'translations' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/cms/translations'),
            'serializer' => 'php',
        ],
        'images' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/cms/images'),
            'serializer' => 'php',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'prefix' => 'cms_',
        ],
    ],

    'ttl' => [
        'content' => env('CMS_CONTENT_CACHE_TTL', 3600),
        'translations' => env('CMS_TRANSLATIONS_CACHE_TTL', 7200),
        'images' => env('CMS_IMAGES_CACHE_TTL', 86400),
        'config' => env('CMS_CONFIG_CACHE_TTL', 3600),
    ],

    'tags' => [
        'enabled' => true,
        'separator' => ':',
        'global_tags' => ['cms'],
    ],

    'invalidation' => [
        'auto_invalidate' => true,
        'cascade_delete' => true,
        'soft_purge' => false,
    ],
];
```

## 🌐 Locale Configuration (config/cms-locales.php)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    */

    'locales' => [
        'en' => [
            'name' => 'English',
            'native_name' => 'English',
            'flag' => '🇺🇸',
            'rtl' => false,
            'enabled' => true,
            'default' => true,
        ],
        'es' => [
            'name' => 'Spanish',
            'native_name' => 'Español',
            'flag' => '🇪🇸',
            'rtl' => false,
            'enabled' => true,
        ],
        'fr' => [
            'name' => 'French',
            'native_name' => 'Français',
            'flag' => '🇫🇷',
            'rtl' => false,
            'enabled' => true,
        ],
        'de' => [
            'name' => 'German',
            'native_name' => 'Deutsch',
            'flag' => '🇩🇪',
            'rtl' => false,
            'enabled' => false,
        ],
        'ar' => [
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'flag' => '🇸🇦',
            'rtl' => true,
            'enabled' => false,
        ],
        'zh' => [
            'name' => 'Chinese (Simplified)',
            'native_name' => '简体中文',
            'flag' => '🇨🇳',
            'rtl' => false,
            'enabled' => false,
        ],
        'ja' => [
            'name' => 'Japanese',
            'native_name' => '日本語',
            'flag' => '🇯🇵',
            'rtl' => false,
            'enabled' => false,
        ],
    ],

    'fallback_chain' => [
        'es' => ['en'],
        'fr' => ['en'],
        'de' => ['en'],
        'ar' => ['en'],
        'zh' => ['en'],
        'ja' => ['en'],
    ],

    'date_formats' => [
        'en' => 'M j, Y',
        'es' => 'j \\d\\e F \\d\\e Y',
        'fr' => 'j F Y',
        'de' => 'j. F Y',
        'ar' => 'j F Y',
        'zh' => 'Y年n月j日',
        'ja' => 'Y年n月j日',
    ],

    'number_formats' => [
        'en' => ['decimal' => '.', 'thousands' => ','],
        'es' => ['decimal' => ',', 'thousands' => '.'],
        'fr' => ['decimal' => ',', 'thousands' => ' '],
        'de' => ['decimal' => ',', 'thousands' => '.'],
        'ar' => ['decimal' => '.', 'thousands' => ','],
        'zh' => ['decimal' => '.', 'thousands' => ','],
        'ja' => ['decimal' => '.', 'thousands' => ','],
    ],
];
```

## 🛠️ Advanced Configuration

### Custom Storage Disks

```php
// config/filesystems.php
'disks' => [
    'cms_content' => [
        'driver' => 'local',
        'root' => storage_path('cms/content'),
        'url' => env('APP_URL') . '/storage/cms/content',
        'visibility' => 'public',
    ],
    'cms_media' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET') . '/cms/media',
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
    ],
],
```

### Custom Middleware

```php
// app/Http/Middleware/CMSPermissions.php
class CMSPermissions
{
    public function handle($request, Closure $next, $permission = null)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if ($permission && !auth()->user()->can($permission)) {
            abort(403, 'Insufficient permissions for CMS operation');
        }

        return $next($request);
    }
}

// Register in config/cms.php
'route' => [
    'middleware' => ['web', 'auth', 'cms.permissions'],
],
```

### Content Type Registration

```php
// app/Providers/CMSServiceProvider.php
use Webook\LaravelCMS\ContentTypes\ContentTypeRegistry;

public function boot()
{
    $registry = app(ContentTypeRegistry::class);

    // Register custom content types
    $registry->register('video', \App\CMS\VideoContentType::class);
    $registry->register('gallery', \App\CMS\GalleryContentType::class);
    $registry->register('form', \App\CMS\FormContentType::class);
}
```

### Event Listeners

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    'Webook\LaravelCMS\Events\ContentUpdated' => [
        'App\Listeners\ClearContentCache',
        'App\Listeners\NotifyContentUpdate',
    ],
    'Webook\LaravelCMS\Events\TranslationUpdated' => [
        'App\Listeners\InvalidateTranslationCache',
    ],
];
```

## 🔧 Environment-Specific Configuration

### Development Environment

```env
# .env.local
CMS_DEBUG=true
CMS_LOG_LEVEL=debug
CMS_SHOW_TOOLBAR=true
CMS_PROFILING=true
CMS_CACHE_TTL=60
CMS_MINIFY_HTML=false
CMS_OPTIMIZE_IMAGES=false
```

### Staging Environment

```env
# .env.staging
CMS_DEBUG=false
CMS_LOG_LEVEL=info
CMS_SHOW_TOOLBAR=false
CMS_PROFILING=false
CMS_CACHE_TTL=1800
CMS_MINIFY_HTML=true
CMS_OPTIMIZE_IMAGES=true
CMS_CDN_ENABLED=false
```

### Production Environment

```env
# .env.production
CMS_DEBUG=false
CMS_LOG_LEVEL=warning
CMS_SHOW_TOOLBAR=false
CMS_PROFILING=false
CMS_CACHE_TTL=3600
CMS_MINIFY_HTML=true
CMS_OPTIMIZE_IMAGES=true
CMS_CDN_ENABLED=true
CMS_CDN_URL=https://cdn.yoursite.com
```

## 📊 Configuration Validation

### Artisan Commands

```bash
# Validate CMS configuration
php artisan cms:config:validate

# Show current configuration
php artisan cms:config:show

# Test cache configuration
php artisan cms:cache:test

# Verify permissions
php artisan cms:permissions:check

# System health check
php artisan cms:health
```

### Configuration Validation Script

```php
// config/cms-validation.php
return [
    'rules' => [
        'cms.enabled' => 'required|boolean',
        'cms.route.prefix' => 'required|string|alpha_dash',
        'cms.storage.disk' => 'required|string',
        'cms.cache.ttl' => 'required|integer|min:60',
        'cms.security.max_file_size' => 'required|integer|min:1024',
    ],
    'messages' => [
        'cms.cache.ttl.min' => 'Cache TTL should be at least 60 seconds',
        'cms.security.max_file_size.min' => 'Max file size should be at least 1MB',
    ],
];
```

## 🎛️ Runtime Configuration

### Dynamic Configuration

```php
// Change configuration at runtime
cms_config('editor.toolbar', 'advanced');
cms_config('cache.ttl', 7200);

// Get configuration
$toolbar = cms_config('editor.toolbar');
$ttl = cms_config('cache.ttl', 3600); // with default

// Configuration groups
$editorConfig = cms_config('editor');
$securityConfig = cms_config('security');
```

### Conditional Configuration

```php
// Based on user role
if (auth()->user()->hasRole('admin')) {
    cms_config('editor.toolbar', 'full');
    cms_config('security.file_upload.max_size', 51200);
}

// Based on environment
if (app()->environment('production')) {
    cms_config('performance.minify_html', true);
    cms_config('cache.ttl', 7200);
}

// Based on request
if (request()->is('admin/*')) {
    cms_config('editor.show_advanced_options', true);
}
```

## 📞 Next Steps

Now that you have Laravel CMS configured:

1. **Test Your Setup**: [Usage Guide](usage.md)
2. **Explore the API**: [API Documentation](api.md)
3. **Secure Your Installation**: [Security Guide](security.md)
4. **Optimize Performance**: [Performance Guide](performance.md)
5. **Deploy to Production**: [Deployment Guide](deployment.md)

---

**Need help with configuration?** Check our [Troubleshooting Guide](troubleshooting.md) or ask in our [Discord community](https://discord.gg/laravel-cms).