<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CMS Test Application Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings specific to the CMS test
    | application. These settings control various testing features,
    | mock services, and test data generation.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the application will run in test mode with additional
    | debugging information, test indicators, and mock data generation.
    |
    */

    'enabled' => env('CMS_TEST_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of supported locales for testing translation functionality.
    | These locales will be available in the language switcher and
    | will have corresponding translation files.
    |
    */

    'supported_locales' => [
        'en' => [
            'name' => 'English',
            'native_name' => 'English',
            'flag' => '🇺🇸',
            'rtl' => false,
            'enabled' => true,
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Feature flags for testing different CMS functionalities.
    | These can be toggled to enable/disable specific features
    | during testing.
    |
    */

    'features' => [
        'new_design' => env('CMS_TEST_NEW_DESIGN', false),
        'beta_features' => env('CMS_TEST_BETA_FEATURES', false),
        'analytics' => env('CMS_TEST_ANALYTICS', true),
        'auto_save' => env('CMS_TEST_AUTO_SAVE', true),
        'real_time_updates' => env('CMS_TEST_REAL_TIME', false),
        'collaborative_editing' => env('CMS_TEST_COLLABORATIVE', false),
        'ai_assistance' => env('CMS_TEST_AI', false),
        'version_control' => env('CMS_TEST_VERSION_CONTROL', true),
        'advanced_permissions' => env('CMS_TEST_ADVANCED_PERMS', false),
        'performance_monitoring' => env('CMS_TEST_PERFORMANCE', true),
        'audit_logging' => env('CMS_TEST_AUDIT_LOG', true),
        'backup_automation' => env('CMS_TEST_AUTO_BACKUP', false),
        'content_scheduling' => env('CMS_TEST_SCHEDULING', false),
        'seo_optimization' => env('CMS_TEST_SEO', true),
        'media_optimization' => env('CMS_TEST_MEDIA_OPT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mock Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for mock services used during testing.
    | These services simulate external APIs and integrations.
    |
    */

    'mock_services' => [
        'weather_api' => [
            'enabled' => true,
            'base_url' => 'https://api.mock-weather.com',
            'api_key' => 'test-api-key',
            'timeout' => 5,
            'cache_duration' => 300, // 5 minutes
        ],

        'translation_api' => [
            'enabled' => false,
            'provider' => 'google', // google, deepl, azure
            'api_key' => env('TRANSLATION_API_KEY', 'mock-key'),
            'rate_limit' => 100, // requests per hour
            'timeout' => 10,
        ],

        'image_processing' => [
            'enabled' => true,
            'max_size' => 10240, // KB
            'allowed_formats' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
            'generate_thumbnails' => true,
            'thumbnail_sizes' => [
                'small' => [150, 150],
                'medium' => [400, 300],
                'large' => [800, 600],
            ],
        ],

        'notification_service' => [
            'enabled' => true,
            'provider' => 'mock', // mock, pusher, redis
            'channels' => ['content-updates', 'user-activity', 'system-alerts'],
            'rate_limit' => 1000, // per hour
        ],

        'analytics_service' => [
            'enabled' => false,
            'provider' => 'mock',
            'tracking_id' => 'TEST-123456',
            'events' => ['page_view', 'content_edit', 'user_action'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Data Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for test data generation and management.
    |
    */

    'test_data' => [
        'users' => [
            'count' => 50,
            'roles' => [
                'cms_admin' => 5,
                'cms_editor' => 15,
                'cms_translator' => 10,
                'user' => 20,
            ],
            'generate_activity' => true,
            'activity_days' => 30,
        ],

        'content' => [
            'pages' => 20,
            'posts' => 100,
            'translations' => [
                'keys_per_group' => 50,
                'groups' => ['messages', 'forms', 'errors', 'navigation'],
            ],
            'media_files' => 200,
            'generate_history' => true,
        ],

        'performance' => [
            'large_files' => 10,
            'large_file_size' => 1024 * 1024, // 1MB
            'concurrent_users' => 10,
            'stress_test_duration' => 300, // 5 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for security testing features.
    |
    */

    'security' => [
        'enable_xss_testing' => true,
        'enable_csrf_testing' => true,
        'enable_sql_injection_testing' => false, // Dangerous - only in isolated environments
        'enable_file_upload_testing' => true,
        'max_upload_size' => 10240, // KB
        'allowed_upload_types' => [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
            'documents' => ['pdf', 'doc', 'docx', 'txt', 'rtf'],
            'archives' => ['zip', 'tar', 'gz'],
        ],
        'blocked_upload_types' => ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js'],
        'scan_uploaded_files' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for performance testing and monitoring.
    |
    */

    'performance' => [
        'enable_monitoring' => true,
        'slow_query_threshold' => 1000, // milliseconds
        'memory_limit_warning' => 80, // percent
        'response_time_warning' => 2000, // milliseconds
        'log_performance_data' => true,
        'performance_data_retention' => 7, // days

        'benchmarks' => [
            'page_load_time' => 2000, // ms
            'api_response_time' => 500, // ms
            'file_upload_time' => 10000, // ms
            'translation_lookup_time' => 50, // ms
            'database_query_time' => 100, // ms
        ],

        'stress_testing' => [
            'max_concurrent_requests' => 100,
            'test_duration' => 300, // seconds
            'ramp_up_time' => 60, // seconds
            'target_endpoints' => [
                '/test/simple',
                '/test/translated',
                '/test/complex',
                '/test/api/dynamic-content',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for browser-based testing with Laravel Dusk.
    |
    */

    'browser_testing' => [
        'enabled' => env('DUSK_ENABLED', false),
        'headless' => env('DUSK_HEADLESS', true),
        'window_size' => env('DUSK_WINDOW_SIZE', '1920,1080'),
        'browser' => env('DUSK_BROWSER', 'chrome'),
        'driver_path' => env('DUSK_DRIVER_PATH', null),

        'test_scenarios' => [
            'responsive_design' => [
                'mobile' => [320, 568],
                'tablet' => [768, 1024],
                'desktop' => [1920, 1080],
            ],
            'accessibility' => [
                'keyboard_navigation' => true,
                'screen_reader_compatibility' => true,
                'color_contrast_testing' => true,
                'focus_management' => true,
            ],
            'cross_browser' => [
                'chrome' => true,
                'firefox' => false,
                'safari' => false,
                'edge' => false,
            ],
        ],

        'screenshots' => [
            'enabled' => true,
            'on_failure' => true,
            'directory' => 'tests/Browser/screenshots',
            'failure_directory' => 'tests/Browser/screenshots/failures',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for API endpoint testing.
    |
    */

    'api_testing' => [
        'base_url' => env('API_TEST_BASE_URL', 'http://localhost:8000'),
        'rate_limit' => 1000, // requests per hour
        'timeout' => 30, // seconds
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds

        'endpoints' => [
            'content' => [
                'text_update' => '/cms/api/content/text/update',
                'bulk_update' => '/cms/api/content/text/bulk-update',
                'image_upload' => '/cms/api/content/image/upload',
                'link_update' => '/cms/api/content/link/update',
            ],
            'translation' => [
                'create' => '/cms/api/translation',
                'update' => '/cms/api/translation/{id}',
                'delete' => '/cms/api/translation/{id}',
                'bulk_import' => '/cms/api/translation/bulk-import',
                'export' => '/cms/api/translation/export',
            ],
            'history' => [
                'file' => '/cms/api/history/file',
                'system' => '/cms/api/history/system',
                'restore' => '/cms/api/history/restore',
            ],
        ],

        'test_data' => [
            'valid_payloads' => true,
            'invalid_payloads' => true,
            'edge_cases' => true,
            'malformed_requests' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for database testing and seeding.
    |
    */

    'database' => [
        'use_transactions' => true,
        'refresh_between_tests' => true,
        'seed_test_data' => true,
        'truncate_tables' => ['activity_logs', 'file_history', 'translations'],

        'connections' => [
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'foreign_key_constraints' => true,
            ],
        ],

        'migrations' => [
            'run_migrations' => true,
            'migration_paths' => [
                'database/migrations',
                'vendor/webook/laravel-cms/database/migrations',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Debugging
    |--------------------------------------------------------------------------
    |
    | Configuration for test logging and debugging features.
    |
    */

    'logging' => [
        'test_channel' => 'testing',
        'level' => env('CMS_TEST_LOG_LEVEL', 'debug'),
        'log_sql_queries' => env('CMS_TEST_LOG_SQL', false),
        'log_api_requests' => env('CMS_TEST_LOG_API', true),
        'log_user_actions' => env('CMS_TEST_LOG_ACTIONS', true),
        'log_performance_data' => env('CMS_TEST_LOG_PERFORMANCE', true),

        'channels' => [
            'test_results' => [
                'driver' => 'single',
                'path' => storage_path('logs/test-results.log'),
                'level' => 'info',
            ],
            'performance' => [
                'driver' => 'single',
                'path' => storage_path('logs/performance.log'),
                'level' => 'debug',
            ],
            'security' => [
                'driver' => 'single',
                'path' => storage_path('logs/security-tests.log'),
                'level' => 'warning',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for email testing functionality.
    |
    */

    'mail' => [
        'driver' => 'log',
        'capture_emails' => true,
        'test_recipients' => [
            'admin@test.com',
            'editor@test.com',
            'user@test.com',
        ],
        'templates' => [
            'welcome' => 'emails.welcome',
            'password_reset' => 'emails.password-reset',
            'content_updated' => 'emails.content-updated',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for cache testing functionality.
    |
    */

    'cache' => [
        'test_driver' => 'array',
        'test_cache_performance' => true,
        'test_cache_invalidation' => true,
        'cache_stress_test' => false,

        'test_scenarios' => [
            'cache_hit_rate' => [
                'target' => 80, // percent
                'test_duration' => 300, // seconds
            ],
            'cache_memory_usage' => [
                'max_memory' => 100, // MB
                'items_to_cache' => 10000,
            ],
            'cache_expiration' => [
                'short_ttl' => 60, // seconds
                'medium_ttl' => 3600, // 1 hour
                'long_ttl' => 86400, // 24 hours
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File System Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for file system testing functionality.
    |
    */

    'filesystem' => [
        'test_disk' => 'testing',
        'test_file_operations' => true,
        'test_permissions' => true,
        'test_large_files' => false,

        'test_scenarios' => [
            'file_upload' => [
                'max_size' => 10240, // KB
                'concurrent_uploads' => 10,
                'test_file_types' => ['image', 'document', 'archive'],
            ],
            'file_processing' => [
                'image_resize' => true,
                'image_compression' => true,
                'thumbnail_generation' => true,
            ],
            'backup_restore' => [
                'test_backup_creation' => true,
                'test_backup_restoration' => true,
                'test_incremental_backup' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for third-party integration testing.
    |
    */

    'integrations' => [
        'external_apis' => [
            'timeout' => 10, // seconds
            'retry_attempts' => 2,
            'mock_responses' => true,
        ],

        'webhooks' => [
            'test_endpoints' => [
                'content_updated' => 'https://webhook.site/test-content',
                'user_registered' => 'https://webhook.site/test-user',
            ],
            'timeout' => 5,
            'verify_ssl' => false,
        ],

        'social_auth' => [
            'providers' => ['google', 'facebook', 'twitter'],
            'mock_providers' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Reporting
    |--------------------------------------------------------------------------
    |
    | Configuration for test result reporting and analytics.
    |
    */

    'reporting' => [
        'generate_reports' => true,
        'report_formats' => ['html', 'json', 'xml'],
        'include_screenshots' => true,
        'include_performance_data' => true,
        'include_coverage_data' => true,

        'export_locations' => [
            'local' => storage_path('test-reports'),
            'remote' => env('TEST_REPORT_REMOTE_PATH', null),
        ],

        'notifications' => [
            'on_failure' => true,
            'on_success' => false,
            'channels' => ['email', 'slack'],
            'recipients' => [
                'admin@test.com',
            ],
        ],
    ],

];