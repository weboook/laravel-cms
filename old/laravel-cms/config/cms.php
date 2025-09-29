<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CMS Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls the authentication mechanism for the CMS.
    | You can use Laravel's default guards or create custom ones.
    |
    */

    'auth' => [
        'guard' => env('CMS_AUTH_GUARD', 'web'),
        'middleware' => ['auth', 'cms.permissions'],
        'permissions' => [
            'cms.edit' => env('CMS_PERMISSION_EDIT', 'cms-edit'),
            'cms.publish' => env('CMS_PERMISSION_PUBLISH', 'cms-publish'),
            'cms.admin' => env('CMS_PERMISSION_ADMIN', 'cms-admin'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routing settings for CMS endpoints.
    |
    */
    'routes' => [
        // Route prefix for CMS endpoints
        'prefix' => env('CMS_ROUTE_PREFIX', 'cms'),

        // Domain for CMS routes (optional)
        'domain' => env('CMS_ROUTE_DOMAIN', null),

        // Middleware groups to apply to CMS routes
        'middleware' => ['web', 'auth'],

        // Rate limiting for CMS routes
        'rate_limit' => env('CMS_RATE_LIMIT', '60,1'),

        // Custom route model bindings
        'bindings' => [
            'content' => 'Webook\LaravelCMS\Models\Content',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Settings
    |--------------------------------------------------------------------------
    |
    | Configure multi-language support for the CMS.
    |
    */
    'locale' => [
        // Enable multi-language support
        'enabled' => env('CMS_LOCALE_ENABLED', false),

        // Default locale
        'default' => env('CMS_LOCALE_DEFAULT', 'en'),

        // Available locales for content
        'available' => explode(',', env('CMS_LOCALES_AVAILABLE', 'en,es,fr,de')),

        // Fallback locale when translation not found
        'fallback' => env('CMS_LOCALE_FALLBACK', 'en'),

        // Store locale in session
        'session_key' => 'cms_locale',

        // URL locale detection
        'detect_from_url' => env('CMS_LOCALE_DETECT_URL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor Settings
    |--------------------------------------------------------------------------
    |
    | Configure the inline editor behavior and appearance.
    |
    */
    'editor' => [
        // Toolbar position: 'top', 'bottom', 'floating'
        'toolbar_position' => env('CMS_EDITOR_TOOLBAR_POSITION', 'floating'),

        // Auto-save interval in seconds (0 to disable)
        'auto_save_interval' => (int) env('CMS_EDITOR_AUTO_SAVE_INTERVAL', 30),

        // Enable spell check
        'spell_check' => env('CMS_EDITOR_SPELL_CHECK', true),

        // Editor theme: 'light', 'dark', 'auto'
        'theme' => env('CMS_EDITOR_THEME', 'auto'),

        // Rich text editor configuration
        'rich_text' => [
            'enabled' => env('CMS_RICH_TEXT_ENABLED', true),
            'toolbar' => [
                'bold', 'italic', 'underline', '|',
                'heading1', 'heading2', 'heading3', '|',
                'bulletList', 'orderedList', '|',
                'link', 'image', '|',
                'undo', 'redo'
            ],
        ],

        // Keyboard shortcuts
        'shortcuts' => [
            'save' => 'Ctrl+S',
            'cancel' => 'Escape',
            'bold' => 'Ctrl+B',
            'italic' => 'Ctrl+I',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configure file storage and backup settings.
    |
    */
    'storage' => [
        // Default disk for CMS files
        'disk' => env('CMS_STORAGE_DISK', 'local'),

        // Backup storage configuration
        'backup' => [
            'enabled' => env('CMS_BACKUP_ENABLED', true),
            'disk' => env('CMS_BACKUP_DISK', 'local'),
            'path' => env('CMS_BACKUP_PATH', 'cms/backups'),
            'retention_days' => (int) env('CMS_BACKUP_RETENTION_DAYS', 30),
            'compress' => env('CMS_BACKUP_COMPRESS', true),
        ],

        // File versioning
        'versioning' => [
            'enabled' => env('CMS_VERSIONING_ENABLED', true),
            'max_versions' => (int) env('CMS_MAX_VERSIONS', 10),
            'auto_cleanup' => env('CMS_VERSION_AUTO_CLEANUP', true),
        ],

        // Upload settings
        'uploads' => [
            'max_size' => env('CMS_UPLOAD_MAX_SIZE', '10M'),
            'allowed_types' => explode(',', env('CMS_UPLOAD_ALLOWED_TYPES', 'jpg,jpeg,png,gif,svg,pdf,doc,docx')),
            'path' => env('CMS_UPLOAD_PATH', 'cms/uploads'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Settings
    |--------------------------------------------------------------------------
    |
    | Configure asset handling, CDN, and optimization.
    |
    */
    'assets' => [
        // CDN configuration
        'cdn' => [
            'enabled' => env('CMS_CDN_ENABLED', false),
            'url' => env('CMS_CDN_URL', null),
            'fallback' => env('CMS_CDN_FALLBACK', true),
        ],

        // Asset minification
        'minification' => [
            'enabled' => env('CMS_MINIFY_ENABLED', env('APP_ENV') === 'production'),
            'css' => env('CMS_MINIFY_CSS', true),
            'js' => env('CMS_MINIFY_JS', true),
        ],

        // Asset versioning for cache busting
        'versioning' => env('CMS_ASSET_VERSIONING', true),

        // Inline critical CSS
        'inline_critical_css' => env('CMS_INLINE_CRITICAL_CSS', false),

        // Asset preloading
        'preload' => [
            'fonts' => env('CMS_PRELOAD_FONTS', true),
            'images' => env('CMS_PRELOAD_IMAGES', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security policies and content filtering.
    |
    */
    'security' => [
        // Content Security Policy
        'csp' => [
            'enabled' => env('CMS_CSP_ENABLED', true),
            'script_src' => env('CMS_CSP_SCRIPT_SRC', "'self' 'unsafe-inline'"),
            'style_src' => env('CMS_CSP_STYLE_SRC', "'self' 'unsafe-inline'"),
            'img_src' => env('CMS_CSP_IMG_SRC', "'self' data: https:"),
        ],

        // HTML sanitization
        'html_purifier' => [
            'enabled' => env('CMS_HTML_PURIFIER_ENABLED', true),
            'cache_dir' => env('CMS_HTML_PURIFIER_CACHE', storage_path('app/cms/purifier')),
        ],

        // Allowed HTML tags and attributes
        'allowed_tags' => [
            'p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'a', 'img', 'blockquote', 'code', 'pre',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'div', 'span', 'section', 'article', 'aside', 'header', 'footer'
        ],

        'allowed_attributes' => [
            'href', 'src', 'alt', 'title', 'class', 'id', 'target',
            'width', 'height', 'style', 'data-*'
        ],

        // XSS protection
        'xss_protection' => env('CMS_XSS_PROTECTION', true),

        // CSRF protection for CMS forms
        'csrf_protection' => env('CMS_CSRF_PROTECTION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for improved performance.
    |
    */
    'cache' => [
        // Enable caching
        'enabled' => env('CMS_CACHE_ENABLED', true),

        // Cache store to use
        'store' => env('CMS_CACHE_STORE', 'file'),

        // Cache key prefix
        'prefix' => env('CMS_CACHE_PREFIX', 'cms'),

        // Default cache TTL in seconds
        'ttl' => (int) env('CMS_CACHE_TTL', 3600),

        // Cache tags (if supported by cache driver)
        'tags' => [
            'content' => 'cms.content',
            'config' => 'cms.config',
            'assets' => 'cms.assets',
        ],

        // Auto-invalidation rules
        'auto_invalidate' => [
            'on_content_update' => true,
            'on_config_change' => true,
            'on_file_change' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Git Integration Settings
    |--------------------------------------------------------------------------
    |
    | Advanced git integration for version control and collaboration.
    |
    */
    'git' => [
        // Enable git integration
        'enabled' => env('CMS_GIT_ENABLED', false),

        // Git repository path (relative to Laravel root)
        'repository_path' => env('CMS_GIT_REPO_PATH', base_path()),

        // Auto-commit changes
        'auto_commit' => [
            'enabled' => env('CMS_GIT_AUTO_COMMIT', false),
            'message_template' => env('CMS_GIT_COMMIT_MESSAGE', 'CMS: Updated {file} by {user}'),
            'author_name' => env('CMS_GIT_AUTHOR_NAME', 'CMS System'),
            'author_email' => env('CMS_GIT_AUTHOR_EMAIL', 'cms@' . parse_url(config('app.url'), PHP_URL_HOST)),
        ],

        // Branch management
        'branches' => [
            'main' => env('CMS_GIT_MAIN_BRANCH', 'main'),
            'create_feature_branches' => env('CMS_GIT_FEATURE_BRANCHES', false),
            'feature_prefix' => env('CMS_GIT_FEATURE_PREFIX', 'cms/'),
        ],

        // Collaboration features
        'collaboration' => [
            'enable_blame' => env('CMS_GIT_BLAME', true),
            'show_history' => env('CMS_GIT_HISTORY', true),
            'diff_preview' => env('CMS_GIT_DIFF_PREVIEW', true),
        ],

        // Hooks
        'hooks' => [
            'pre_commit' => env('CMS_GIT_PRE_COMMIT_HOOK', null),
            'post_commit' => env('CMS_GIT_POST_COMMIT_HOOK', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Configure database-related settings for the CMS.
    |
    */
    'database' => [
        // Connection to use for CMS tables
        'connection' => env('CMS_DB_CONNECTION', null),

        // Table prefix
        'prefix' => env('CMS_DB_PREFIX', 'cms_'),

        // Enable soft deletes
        'soft_deletes' => env('CMS_SOFT_DELETES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Configure API endpoints and behavior.
    |
    */
    'api' => [
        // Enable REST API
        'enabled' => env('CMS_API_ENABLED', true),

        // API version
        'version' => env('CMS_API_VERSION', 'v1'),

        // Rate limiting for API
        'rate_limit' => env('CMS_API_RATE_LIMIT', '1000,60'),

        // API authentication
        'auth' => [
            'driver' => env('CMS_API_AUTH_DRIVER', 'sanctum'),
            'required' => env('CMS_API_AUTH_REQUIRED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug and Development
    |--------------------------------------------------------------------------
    |
    | Settings for debugging and development.
    |
    */
    'debug' => [
        // Enable debug mode
        'enabled' => env('CMS_DEBUG', env('APP_DEBUG', false)),

        // Log level for CMS operations
        'log_level' => env('CMS_LOG_LEVEL', 'info'),

        // Enable performance profiling
        'profiling' => env('CMS_PROFILING', false),

        // Show debug toolbar
        'toolbar' => env('CMS_DEBUG_TOOLBAR', false),
    ],
];