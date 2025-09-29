<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Content Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the CMS detects and marks editable content in your database.
    |
    */

    'detection' => [
        'enabled' => env('CMS_DB_DETECTION_ENABLED', true),
        'auto_scan' => env('CMS_DB_AUTO_SCAN', false),
        'scan_interval' => env('CMS_DB_SCAN_INTERVAL', 'daily'), // 'hourly', 'daily', 'weekly'
        'cache_scan_results' => env('CMS_DB_CACHE_SCAN', true),
        'cache_duration' => env('CMS_DB_CACHE_DURATION', 3600), // seconds
        'scan_timeout' => env('CMS_DB_SCAN_TIMEOUT', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which models and fields should be editable through the CMS.
    |
    */

    'models' => [
        'App\Models\Post' => [
            'enabled' => env('CMS_POSTS_ENABLED', true),
            'fields' => [
                'title' => [
                    'type' => 'text',
                    'label' => 'Title',
                    'required' => true,
                    'max_length' => 255,
                    'editor' => 'plain',
                ],
                'content' => [
                    'type' => 'longtext',
                    'label' => 'Content',
                    'required' => false,
                    'editor' => 'rich',
                ],
                'excerpt' => [
                    'type' => 'text',
                    'label' => 'Excerpt',
                    'required' => false,
                    'max_length' => 500,
                    'editor' => 'plain',
                ],
                'meta_description' => [
                    'type' => 'text',
                    'label' => 'Meta Description',
                    'required' => false,
                    'max_length' => 160,
                    'editor' => 'plain',
                ],
            ],
            'permissions' => [
                'edit' => 'edit-posts',
                'publish' => 'publish-posts',
                'delete' => 'delete-posts',
            ],
            'workflow' => [
                'requires_approval' => env('CMS_POSTS_REQUIRE_APPROVAL', false),
                'auto_publish' => env('CMS_POSTS_AUTO_PUBLISH', true),
                'versioning' => env('CMS_POSTS_VERSIONING', true),
            ],
        ],

        'App\Models\Page' => [
            'enabled' => env('CMS_PAGES_ENABLED', true),
            'fields' => [
                'title' => [
                    'type' => 'text',
                    'label' => 'Page Title',
                    'required' => true,
                    'max_length' => 255,
                    'editor' => 'plain',
                ],
                'content' => [
                    'type' => 'longtext',
                    'label' => 'Page Content',
                    'required' => false,
                    'editor' => 'rich',
                ],
                'meta_title' => [
                    'type' => 'text',
                    'label' => 'Meta Title',
                    'required' => false,
                    'max_length' => 60,
                    'editor' => 'plain',
                ],
                'meta_description' => [
                    'type' => 'text',
                    'label' => 'Meta Description',
                    'required' => false,
                    'max_length' => 160,
                    'editor' => 'plain',
                ],
                'slug' => [
                    'type' => 'text',
                    'label' => 'URL Slug',
                    'required' => true,
                    'max_length' => 255,
                    'editor' => 'plain',
                    'auto_generate' => true,
                    'source_field' => 'title',
                ],
            ],
            'permissions' => [
                'edit' => 'edit-pages',
                'publish' => 'publish-pages',
                'delete' => 'delete-pages',
            ],
            'workflow' => [
                'requires_approval' => env('CMS_PAGES_REQUIRE_APPROVAL', true),
                'auto_publish' => env('CMS_PAGES_AUTO_PUBLISH', false),
                'versioning' => env('CMS_PAGES_VERSIONING', true),
            ],
        ],

        'App\Models\Product' => [
            'enabled' => env('CMS_PRODUCTS_ENABLED', false),
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'Product Name',
                    'required' => true,
                    'max_length' => 255,
                    'editor' => 'plain',
                ],
                'description' => [
                    'type' => 'longtext',
                    'label' => 'Description',
                    'required' => false,
                    'editor' => 'rich',
                ],
                'short_description' => [
                    'type' => 'text',
                    'label' => 'Short Description',
                    'required' => false,
                    'max_length' => 500,
                    'editor' => 'plain',
                ],
                'specifications' => [
                    'type' => 'json',
                    'label' => 'Specifications',
                    'required' => false,
                    'editor' => 'json',
                ],
            ],
            'permissions' => [
                'edit' => 'edit-products',
                'publish' => 'publish-products',
                'delete' => 'delete-products',
            ],
            'workflow' => [
                'requires_approval' => env('CMS_PRODUCTS_REQUIRE_APPROVAL', true),
                'auto_publish' => env('CMS_PRODUCTS_AUTO_PUBLISH', false),
                'versioning' => env('CMS_PRODUCTS_VERSIONING', true),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor Configuration
    |--------------------------------------------------------------------------
    |
    | Configure editor behavior for database content editing.
    |
    */

    'editor' => [
        'theme' => env('CMS_DB_EDITOR_THEME', 'default'),
        'auto_save' => env('CMS_DB_AUTO_SAVE', true),
        'auto_save_interval' => env('CMS_DB_AUTO_SAVE_INTERVAL', 30), // seconds
        'show_word_count' => env('CMS_DB_SHOW_WORD_COUNT', true),
        'show_character_count' => env('CMS_DB_SHOW_CHAR_COUNT', false),
        'spell_check' => env('CMS_DB_SPELL_CHECK', true),
        'grammar_check' => env('CMS_DB_GRAMMAR_CHECK', false),

        'rich_text' => [
            'toolbar' => [
                'formatting' => ['bold', 'italic', 'underline', 'strikethrough'],
                'headings' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
                'lists' => ['bulletList', 'orderedList'],
                'alignment' => ['alignLeft', 'alignCenter', 'alignRight', 'alignJustify'],
                'indentation' => ['indent', 'outdent'],
                'links' => ['link', 'unlink'],
                'media' => ['image', 'video', 'audio'],
                'tables' => ['table'],
                'blocks' => ['blockquote', 'codeBlock', 'horizontalRule'],
                'history' => ['undo', 'redo'],
                'source' => ['code'],
            ],
            'plugins' => [
                'link' => true,
                'image' => true,
                'table' => true,
                'code' => true,
                'emoji' => env('CMS_DB_EMOJI_ENABLED', false),
                'mentions' => env('CMS_DB_MENTIONS_ENABLED', false),
                'hashtags' => env('CMS_DB_HASHTAGS_ENABLED', false),
            ],
        ],

        'markdown' => [
            'live_preview' => env('CMS_MARKDOWN_PREVIEW', true),
            'syntax_highlighting' => env('CMS_MARKDOWN_SYNTAX', true),
            'auto_links' => env('CMS_MARKDOWN_AUTO_LINKS', true),
            'tables' => env('CMS_MARKDOWN_TABLES', true),
            'math' => env('CMS_MARKDOWN_MATH', false),
        ],

        'code' => [
            'syntax_highlighting' => env('CMS_CODE_SYNTAX', true),
            'line_numbers' => env('CMS_CODE_LINE_NUMBERS', true),
            'word_wrap' => env('CMS_CODE_WORD_WRAP', false),
            'auto_complete' => env('CMS_CODE_AUTO_COMPLETE', true),
            'language_detection' => env('CMS_CODE_LANG_DETECT', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure validation rules for database content.
    |
    */

    'validation' => [
        'enabled' => env('CMS_DB_VALIDATION', true),
        'sanitize_html' => env('CMS_DB_SANITIZE_HTML', true),
        'allowed_html_tags' => [
            'p', 'br', 'strong', 'em', 'u', 's', 'del', 'ins',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'a', 'img', 'figure', 'figcaption',
            'blockquote', 'cite', 'q',
            'code', 'pre', 'kbd', 'samp', 'var',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
            'div', 'span', 'section', 'article', 'aside', 'header', 'footer', 'nav',
            'details', 'summary', 'mark', 'small', 'sub', 'sup'
        ],
        'allowed_attributes' => [
            'href', 'src', 'alt', 'title', 'class', 'id', 'style',
            'width', 'height', 'target', 'rel', 'data-*',
            'colspan', 'rowspan', 'scope',
            'type', 'start', 'reversed'
        ],
        'max_content_length' => env('CMS_DB_MAX_CONTENT_LENGTH', 1000000), // characters
        'require_alt_text_for_images' => env('CMS_DB_REQUIRE_ALT_TEXT', true),
        'validate_links' => env('CMS_DB_VALIDATE_LINKS', false),
        'check_spelling' => env('CMS_DB_CHECK_SPELLING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how content is processed and enhanced.
    |
    */

    'processing' => [
        'auto_generate_excerpts' => env('CMS_DB_AUTO_EXCERPTS', true),
        'excerpt_length' => env('CMS_DB_EXCERPT_LENGTH', 155), // characters
        'auto_generate_slugs' => env('CMS_DB_AUTO_SLUGS', true),
        'auto_optimize_images' => env('CMS_DB_AUTO_OPTIMIZE_IMAGES', true),
        'auto_generate_thumbnails' => env('CMS_DB_AUTO_THUMBNAILS', true),
        'extract_keywords' => env('CMS_DB_EXTRACT_KEYWORDS', false),
        'generate_reading_time' => env('CMS_DB_READING_TIME', true),
        'enhance_seo' => env('CMS_DB_ENHANCE_SEO', true),
        'auto_internal_linking' => env('CMS_DB_AUTO_INTERNAL_LINKS', false),
        'content_analysis' => env('CMS_DB_CONTENT_ANALYSIS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Configuration
    |--------------------------------------------------------------------------
    |
    | Configure content workflow and approval processes.
    |
    */

    'workflow' => [
        'enabled' => env('CMS_DB_WORKFLOW_ENABLED', false),
        'default_status' => env('CMS_DB_DEFAULT_STATUS', 'draft'),
        'statuses' => [
            'draft' => [
                'label' => 'Draft',
                'color' => '#6b7280',
                'permissions' => ['author', 'editor', 'admin'],
                'transitions' => ['review', 'published'],
            ],
            'review' => [
                'label' => 'Under Review',
                'color' => '#f59e0b',
                'permissions' => ['editor', 'admin'],
                'transitions' => ['draft', 'published', 'rejected'],
            ],
            'published' => [
                'label' => 'Published',
                'color' => '#10b981',
                'permissions' => ['editor', 'admin'],
                'transitions' => ['draft', 'archived'],
            ],
            'archived' => [
                'label' => 'Archived',
                'color' => '#6b7280',
                'permissions' => ['admin'],
                'transitions' => ['draft', 'published'],
            ],
            'rejected' => [
                'label' => 'Rejected',
                'color' => '#ef4444',
                'permissions' => ['editor', 'admin'],
                'transitions' => ['draft'],
            ],
        ],
        'notifications' => [
            'enabled' => env('CMS_DB_NOTIFICATIONS', true),
            'email' => env('CMS_DB_EMAIL_NOTIFICATIONS', true),
            'database' => env('CMS_DB_DATABASE_NOTIFICATIONS', true),
            'slack' => env('CMS_DB_SLACK_NOTIFICATIONS', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Versioning Configuration
    |--------------------------------------------------------------------------
    |
    | Configure content versioning and history tracking.
    |
    */

    'versioning' => [
        'enabled' => env('CMS_DB_VERSIONING', true),
        'auto_version' => env('CMS_DB_AUTO_VERSION', true),
        'max_versions' => env('CMS_DB_MAX_VERSIONS', 50),
        'keep_versions_for_days' => env('CMS_DB_KEEP_VERSIONS_DAYS', 365),
        'compress_old_versions' => env('CMS_DB_COMPRESS_VERSIONS', true),
        'track_changes' => [
            'field_level' => env('CMS_DB_TRACK_FIELD_CHANGES', true),
            'user_tracking' => env('CMS_DB_TRACK_USERS', true),
            'ip_tracking' => env('CMS_DB_TRACK_IPS', false),
            'browser_tracking' => env('CMS_DB_TRACK_BROWSER', false),
        ],
        'diff_engine' => env('CMS_DB_DIFF_ENGINE', 'unified'), // 'unified', 'side-by-side', 'inline'
    ],

    /*
    |--------------------------------------------------------------------------
    | Search and Indexing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure content search and indexing capabilities.
    |
    */

    'search' => [
        'enabled' => env('CMS_DB_SEARCH_ENABLED', true),
        'driver' => env('CMS_DB_SEARCH_DRIVER', 'database'), // 'database', 'elasticsearch', 'algolia', 'scout'
        'index_on_save' => env('CMS_DB_INDEX_ON_SAVE', true),
        'full_text_search' => env('CMS_DB_FULL_TEXT_SEARCH', true),
        'fuzzy_search' => env('CMS_DB_FUZZY_SEARCH', false),
        'search_weights' => [
            'title' => 3,
            'excerpt' => 2,
            'content' => 1,
            'tags' => 2,
        ],
        'minimum_search_length' => env('CMS_DB_MIN_SEARCH_LENGTH', 3),
        'results_per_page' => env('CMS_DB_SEARCH_RESULTS_PER_PAGE', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance optimization settings.
    |
    */

    'performance' => [
        'cache' => [
            'enabled' => env('CMS_DB_CACHE_ENABLED', true),
            'driver' => env('CMS_DB_CACHE_DRIVER', 'file'),
            'ttl' => env('CMS_DB_CACHE_TTL', 3600), // seconds
            'tags' => env('CMS_DB_CACHE_TAGS', true),
            'cache_queries' => env('CMS_DB_CACHE_QUERIES', true),
            'cache_content' => env('CMS_DB_CACHE_CONTENT', true),
        ],
        'lazy_loading' => [
            'enabled' => env('CMS_DB_LAZY_LOADING', true),
            'relationships' => env('CMS_DB_LAZY_RELATIONSHIPS', true),
            'chunks' => env('CMS_DB_LAZY_CHUNKS', true),
        ],
        'optimization' => [
            'eager_load_relationships' => env('CMS_DB_EAGER_LOAD', true),
            'database_indexes' => env('CMS_DB_INDEXES', true),
            'query_optimization' => env('CMS_DB_QUERY_OPTIMIZATION', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security settings for database content editing.
    |
    */

    'security' => [
        'audit_logging' => [
            'enabled' => env('CMS_DB_AUDIT_LOGGING', true),
            'log_reads' => env('CMS_DB_LOG_READS', false),
            'log_writes' => env('CMS_DB_LOG_WRITES', true),
            'log_deletes' => env('CMS_DB_LOG_DELETES', true),
            'retention_days' => env('CMS_DB_AUDIT_RETENTION', 90),
        ],
        'content_filtering' => [
            'enabled' => env('CMS_DB_CONTENT_FILTER', true),
            'profanity_filter' => env('CMS_DB_PROFANITY_FILTER', false),
            'malicious_content_detection' => env('CMS_DB_MALICIOUS_DETECTION', true),
            'spam_detection' => env('CMS_DB_SPAM_DETECTION', false),
        ],
        'access_control' => [
            'ip_whitelist' => explode(',', env('CMS_DB_IP_WHITELIST', '')),
            'rate_limiting' => env('CMS_DB_RATE_LIMITING', true),
            'max_requests_per_minute' => env('CMS_DB_MAX_REQUESTS', 60),
            'session_timeout' => env('CMS_DB_SESSION_TIMEOUT', 3600), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure backup settings for database content.
    |
    */

    'backup' => [
        'enabled' => env('CMS_DB_BACKUP_ENABLED', false),
        'schedule' => env('CMS_DB_BACKUP_SCHEDULE', 'daily'),
        'retention_days' => env('CMS_DB_BACKUP_RETENTION', 30),
        'backup_disk' => env('CMS_DB_BACKUP_DISK', 'local'),
        'compress_backups' => env('CMS_DB_COMPRESS_BACKUPS', true),
        'include_media' => env('CMS_DB_BACKUP_INCLUDE_MEDIA', true),
        'encrypt_backups' => env('CMS_DB_ENCRYPT_BACKUPS', false),
    ],

];