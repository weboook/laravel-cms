<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asset Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how assets are stored and managed in the CMS.
    |
    */

    'storage' => [
        'disk' => env('CMS_ASSETS_DISK', 'public'),
        'path' => env('CMS_ASSETS_PATH', 'cms-assets'),
        'url_prefix' => env('CMS_ASSETS_URL_PREFIX', '/storage/cms-assets'),
        'temporary_path' => env('CMS_TEMP_PATH', 'cms-temp'),
        'cleanup_temp_after' => env('CMS_TEMP_CLEANUP_HOURS', 24), // hours
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configure file upload settings and restrictions.
    |
    */

    'uploads' => [
        'max_file_size' => env('CMS_UPLOAD_MAX_SIZE', 10485760), // 10MB in bytes
        'max_files_per_upload' => env('CMS_UPLOAD_MAX_FILES', 10),
        'allowed_mime_types' => [
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            // Documents
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Text
            'text/plain', 'text/csv',
            // Archives
            'application/zip', 'application/x-rar-compressed',
            // Video
            'video/mp4', 'video/avi', 'video/quicktime',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg',
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'zip', 'rar', 'mp4', 'avi', 'mov', 'mp3', 'wav', 'ogg'
        ],
        'scan_for_viruses' => env('CMS_VIRUS_SCAN', false),
        'generate_thumbnails' => env('CMS_GENERATE_THUMBNAILS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure image processing and thumbnail generation.
    |
    */

    'images' => [
        'driver' => env('CMS_IMAGE_DRIVER', 'gd'), // 'gd' or 'imagick'
        'quality' => env('CMS_IMAGE_QUALITY', 90),
        'auto_orient' => env('CMS_IMAGE_AUTO_ORIENT', true),
        'strip_metadata' => env('CMS_IMAGE_STRIP_METADATA', true),
        'progressive_jpeg' => env('CMS_IMAGE_PROGRESSIVE', true),

        'thumbnails' => [
            'small' => [
                'width' => 150,
                'height' => 150,
                'crop' => true,
                'quality' => 85,
            ],
            'medium' => [
                'width' => 300,
                'height' => 300,
                'crop' => true,
                'quality' => 90,
            ],
            'large' => [
                'width' => 800,
                'height' => 600,
                'crop' => false,
                'quality' => 90,
            ],
            'xlarge' => [
                'width' => 1200,
                'height' => 900,
                'crop' => false,
                'quality' => 85,
            ],
        ],

        'watermark' => [
            'enabled' => env('CMS_WATERMARK_ENABLED', false),
            'image_path' => env('CMS_WATERMARK_IMAGE', null),
            'position' => env('CMS_WATERMARK_POSITION', 'bottom-right'), // 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'
            'opacity' => env('CMS_WATERMARK_OPACITY', 50), // 0-100
            'margin' => env('CMS_WATERMARK_MARGIN', 10), // pixels
        ],

        'optimization' => [
            'enabled' => env('CMS_IMAGE_OPTIMIZATION', true),
            'convert_to_webp' => env('CMS_CONVERT_TO_WEBP', false),
            'preserve_original' => env('CMS_PRESERVE_ORIGINAL', true),
            'max_width' => env('CMS_IMAGE_MAX_WIDTH', 2048),
            'max_height' => env('CMS_IMAGE_MAX_HEIGHT', 2048),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Organization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how assets are organized in folders and categories.
    |
    */

    'organization' => [
        'enable_folders' => env('CMS_ENABLE_FOLDERS', true),
        'enable_tags' => env('CMS_ENABLE_TAGS', true),
        'enable_categories' => env('CMS_ENABLE_CATEGORIES', true),
        'max_folder_depth' => env('CMS_MAX_FOLDER_DEPTH', 5),
        'auto_create_date_folders' => env('CMS_AUTO_DATE_FOLDERS', false),
        'date_folder_format' => env('CMS_DATE_FOLDER_FORMAT', 'Y/m'), // e.g., 2024/01
        'duplicate_handling' => env('CMS_DUPLICATE_HANDLING', 'rename'), // 'rename', 'overwrite', 'error'
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Library Interface Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the asset library user interface and behavior.
    |
    */

    'interface' => [
        'default_view' => env('CMS_ASSETS_DEFAULT_VIEW', 'grid'), // 'grid', 'list', 'cards'
        'items_per_page' => env('CMS_ASSETS_PER_PAGE', 20),
        'enable_bulk_actions' => env('CMS_ASSETS_BULK_ACTIONS', true),
        'enable_drag_drop' => env('CMS_ASSETS_DRAG_DROP', true),
        'enable_inline_editing' => env('CMS_ASSETS_INLINE_EDIT', true),
        'show_file_info' => env('CMS_ASSETS_SHOW_INFO', true),
        'enable_search' => env('CMS_ASSETS_SEARCH', true),
        'enable_filters' => env('CMS_ASSETS_FILTERS', true),
        'enable_sorting' => env('CMS_ASSETS_SORTING', true),
        'auto_refresh_interval' => env('CMS_ASSETS_AUTO_REFRESH', 0), // seconds, 0 to disable
        'keyboard_shortcuts' => env('CMS_ASSETS_SHORTCUTS', true),
        'touch_gestures' => env('CMS_ASSETS_TOUCH_GESTURES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Metadata Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metadata extraction and storage for assets.
    |
    */

    'metadata' => [
        'extract_exif' => env('CMS_EXTRACT_EXIF', true),
        'extract_iptc' => env('CMS_EXTRACT_IPTC', true),
        'extract_xmp' => env('CMS_EXTRACT_XMP', false),
        'store_file_hash' => env('CMS_STORE_FILE_HASH', true),
        'hash_algorithm' => env('CMS_HASH_ALGORITHM', 'sha256'),
        'analyze_colors' => env('CMS_ANALYZE_COLORS', false),
        'extract_text_from_pdfs' => env('CMS_EXTRACT_PDF_TEXT', false),

        'custom_fields' => [
            'alt_text' => [
                'type' => 'text',
                'label' => 'Alternative Text',
                'required' => false,
                'max_length' => 255,
            ],
            'caption' => [
                'type' => 'textarea',
                'label' => 'Caption',
                'required' => false,
                'max_length' => 1000,
            ],
            'copyright' => [
                'type' => 'text',
                'label' => 'Copyright',
                'required' => false,
                'max_length' => 255,
            ],
            'keywords' => [
                'type' => 'tags',
                'label' => 'Keywords',
                'required' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN and Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CDN usage and performance optimizations.
    |
    */

    'cdn' => [
        'enabled' => env('CMS_CDN_ENABLED', false),
        'url' => env('CMS_CDN_URL', ''),
        'push_to_cdn' => env('CMS_CDN_PUSH', false),
        'cdn_provider' => env('CMS_CDN_PROVIDER', 'cloudflare'), // 'cloudflare', 'aws', 'custom'
        'purge_on_update' => env('CMS_CDN_PURGE_ON_UPDATE', true),
        'cache_headers' => [
            'max_age' => env('CMS_CACHE_MAX_AGE', 31536000), // 1 year
            'public' => env('CMS_CACHE_PUBLIC', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security settings for asset uploads and access.
    |
    */

    'security' => [
        'check_file_content' => env('CMS_CHECK_FILE_CONTENT', true),
        'block_executable_files' => env('CMS_BLOCK_EXECUTABLES', true),
        'block_php_files' => env('CMS_BLOCK_PHP_FILES', true),
        'scan_for_malware' => env('CMS_MALWARE_SCAN', false),
        'quarantine_suspicious_files' => env('CMS_QUARANTINE_FILES', false),
        'allowed_domains_for_remote_images' => [
            // Add domains that are allowed for remote image imports
        ],
        'rate_limiting' => [
            'uploads_per_minute' => env('CMS_UPLOAD_RATE_LIMIT', 10),
            'downloads_per_minute' => env('CMS_DOWNLOAD_RATE_LIMIT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Versioning Configuration
    |--------------------------------------------------------------------------
    |
    | Configure version control for assets.
    |
    */

    'versioning' => [
        'enabled' => env('CMS_ASSET_VERSIONING', false),
        'keep_versions' => env('CMS_ASSET_KEEP_VERSIONS', 5),
        'version_on_update' => env('CMS_VERSION_ON_UPDATE', true),
        'auto_cleanup_old_versions' => env('CMS_AUTO_CLEANUP_VERSIONS', true),
        'cleanup_after_days' => env('CMS_VERSION_CLEANUP_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configure integrations with external services.
    |
    */

    'integrations' => [
        'unsplash' => [
            'enabled' => env('CMS_UNSPLASH_ENABLED', false),
            'access_key' => env('UNSPLASH_ACCESS_KEY', ''),
            'collections' => env('CMS_UNSPLASH_COLLECTIONS', ''),
        ],
        'pixabay' => [
            'enabled' => env('CMS_PIXABAY_ENABLED', false),
            'api_key' => env('PIXABAY_API_KEY', ''),
        ],
        'google_drive' => [
            'enabled' => env('CMS_GOOGLE_DRIVE_ENABLED', false),
            'client_id' => env('GOOGLE_DRIVE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET', ''),
        ],
        'dropbox' => [
            'enabled' => env('CMS_DROPBOX_ENABLED', false),
            'app_key' => env('DROPBOX_APP_KEY', ''),
            'app_secret' => env('DROPBOX_APP_SECRET', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure asset backup settings.
    |
    */

    'backup' => [
        'enabled' => env('CMS_ASSET_BACKUP', false),
        'schedule' => env('CMS_BACKUP_SCHEDULE', 'daily'),
        'keep_backups' => env('CMS_KEEP_BACKUPS', 7),
        'backup_disk' => env('CMS_BACKUP_DISK', 'local'),
        'compress_backups' => env('CMS_COMPRESS_BACKUPS', true),
        'include_thumbnails' => env('CMS_BACKUP_THUMBNAILS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure asset usage analytics and reporting.
    |
    */

    'analytics' => [
        'enabled' => env('CMS_ASSET_ANALYTICS', false),
        'track_downloads' => env('CMS_TRACK_DOWNLOADS', true),
        'track_views' => env('CMS_TRACK_VIEWS', true),
        'track_usage_in_content' => env('CMS_TRACK_USAGE', true),
        'generate_reports' => env('CMS_GENERATE_REPORTS', false),
        'report_schedule' => env('CMS_REPORT_SCHEDULE', 'weekly'),
    ],

];