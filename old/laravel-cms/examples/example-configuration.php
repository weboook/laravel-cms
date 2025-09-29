<?php

/**
 * Example Configuration for Laravel CMS
 *
 * This file demonstrates how to configure the Laravel CMS package
 * for different use cases and environments.
 */

// ============================================================================
// Example 1: Basic Blog Configuration
// ============================================================================

// config/cms-database.php for a blog
return [
    'models' => [
        'App\Models\Post' => [
            'enabled' => true,
            'fields' => [
                'title' => [
                    'type' => 'text',
                    'label' => 'Post Title',
                    'required' => true,
                    'max_length' => 255,
                    'editor' => 'plain',
                ],
                'content' => [
                    'type' => 'longtext',
                    'label' => 'Post Content',
                    'required' => false,
                    'editor' => 'rich',
                ],
                'excerpt' => [
                    'type' => 'text',
                    'label' => 'Post Excerpt',
                    'required' => false,
                    'max_length' => 500,
                    'editor' => 'plain',
                    'auto_generate' => true,
                ],
            ],
            'permissions' => [
                'edit' => 'edit-posts',
                'publish' => 'publish-posts',
                'delete' => 'delete-posts',
            ],
        ],
    ],
];

// ============================================================================
// Example 2: E-commerce Configuration
// ============================================================================

// config/cms-database.php for an e-commerce store
return [
    'models' => [
        'App\Models\Product' => [
            'enabled' => true,
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
                    'label' => 'Product Description',
                    'required' => false,
                    'editor' => 'rich',
                    'toolbar' => ['bold', 'italic', 'bulletList', 'orderedList', 'link'],
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
                    'label' => 'Product Specifications',
                    'required' => false,
                    'editor' => 'json',
                ],
                'features' => [
                    'type' => 'json',
                    'label' => 'Key Features',
                    'required' => false,
                    'editor' => 'list',
                ],
            ],
            'permissions' => [
                'edit' => 'edit-products',
                'publish' => 'publish-products',
                'delete' => 'delete-products',
            ],
            'workflow' => [
                'requires_approval' => true,
                'auto_publish' => false,
                'versioning' => true,
            ],
        ],

        'App\Models\Category' => [
            'enabled' => true,
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'Category Name',
                    'required' => true,
                    'editor' => 'plain',
                ],
                'description' => [
                    'type' => 'text',
                    'label' => 'Category Description',
                    'required' => false,
                    'editor' => 'rich',
                ],
                'meta_title' => [
                    'type' => 'text',
                    'label' => 'SEO Title',
                    'required' => false,
                    'max_length' => 60,
                    'editor' => 'plain',
                ],
                'meta_description' => [
                    'type' => 'text',
                    'label' => 'SEO Description',
                    'required' => false,
                    'max_length' => 160,
                    'editor' => 'plain',
                ],
            ],
        ],
    ],

    'editor' => [
        'theme' => 'light',
        'auto_save' => true,
        'auto_save_interval' => 30,
        'rich_text' => [
            'toolbar' => [
                'bold', 'italic', 'underline',
                'bulletList', 'orderedList',
                'link', 'image'
            ],
        ],
    ],
];

// ============================================================================
// Example 3: Corporate Website Configuration
// ============================================================================

// config/cms-database.php for a corporate website
return [
    'models' => [
        'App\Models\Page' => [
            'enabled' => true,
            'fields' => [
                'title' => [
                    'type' => 'text',
                    'label' => 'Page Title',
                    'required' => true,
                    'editor' => 'plain',
                ],
                'content' => [
                    'type' => 'longtext',
                    'label' => 'Page Content',
                    'required' => false,
                    'editor' => 'rich',
                    'toolbar' => [
                        'bold', 'italic', 'underline',
                        'heading1', 'heading2', 'heading3',
                        'bulletList', 'orderedList',
                        'link', 'image', 'table', 'blockquote'
                    ],
                ],
                'hero_title' => [
                    'type' => 'text',
                    'label' => 'Hero Section Title',
                    'required' => false,
                    'editor' => 'plain',
                ],
                'hero_subtitle' => [
                    'type' => 'text',
                    'label' => 'Hero Section Subtitle',
                    'required' => false,
                    'editor' => 'plain',
                ],
                'cta_text' => [
                    'type' => 'text',
                    'label' => 'Call to Action Text',
                    'required' => false,
                    'editor' => 'plain',
                ],
                'cta_url' => [
                    'type' => 'text',
                    'label' => 'Call to Action URL',
                    'required' => false,
                    'editor' => 'plain',
                ],
            ],
            'permissions' => [
                'edit' => 'edit-pages',
                'publish' => 'publish-pages',
                'delete' => 'delete-pages',
            ],
            'workflow' => [
                'requires_approval' => true,
                'auto_publish' => false,
                'versioning' => true,
            ],
        ],

        'App\Models\TeamMember' => [
            'enabled' => true,
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'required' => true,
                    'editor' => 'plain',
                ],
                'position' => [
                    'type' => 'text',
                    'label' => 'Job Position',
                    'required' => true,
                    'editor' => 'plain',
                ],
                'bio' => [
                    'type' => 'longtext',
                    'label' => 'Biography',
                    'required' => false,
                    'editor' => 'rich',
                ],
                'expertise' => [
                    'type' => 'json',
                    'label' => 'Areas of Expertise',
                    'required' => false,
                    'editor' => 'tags',
                ],
            ],
        ],
    ],

    'workflow' => [
        'enabled' => true,
        'default_status' => 'draft',
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
        ],
    ],
];

// ============================================================================
// Example 4: Multi-language Configuration
// ============================================================================

// config/cms.php with localization
return [
    'localization' => [
        'enabled' => true,
        'default_locale' => 'en',
        'available_locales' => ['en', 'es', 'fr', 'de'],
        'fallback_locale' => 'en',
        'auto_detect_locale' => true,
        'url_strategy' => 'prefix', // URLs like /en/page, /es/page
    ],
];

// config/cms-database.php with multi-language fields
return [
    'models' => [
        'App\Models\Post' => [
            'enabled' => true,
            'fields' => [
                'title' => [
                    'type' => 'text',
                    'label' => 'Post Title',
                    'required' => true,
                    'translatable' => true, // This field will be translated
                    'editor' => 'plain',
                ],
                'content' => [
                    'type' => 'longtext',
                    'label' => 'Post Content',
                    'required' => false,
                    'translatable' => true,
                    'editor' => 'rich',
                ],
                'slug' => [
                    'type' => 'text',
                    'label' => 'URL Slug',
                    'required' => true,
                    'translatable' => true,
                    'auto_generate' => true,
                    'source_field' => 'title',
                    'editor' => 'plain',
                ],
            ],
        ],
    ],
];

// ============================================================================
// Example 5: High-Performance Configuration
// ============================================================================

// config/cms.php for high-traffic sites
return [
    'performance' => [
        'cache' => [
            'enabled' => true,
            'driver' => 'redis', // Use Redis for better performance
            'ttl' => 3600,
            'tags' => true,
        ],
        'optimization' => [
            'minify_html' => true,
            'lazy_loading' => true,
            'image_optimization' => true,
        ],
        'cdn' => [
            'enabled' => true,
            'url' => 'https://cdn.example.com',
            'assets_only' => true,
        ],
    ],
];

// config/cms-assets.php for high-performance asset handling
return [
    'storage' => [
        'disk' => 's3', // Use S3 for asset storage
        'path' => 'cms-assets',
        'url_prefix' => 'https://cdn.example.com/cms-assets',
    ],

    'images' => [
        'driver' => 'imagick',
        'quality' => 85,
        'optimization' => [
            'enabled' => true,
            'convert_to_webp' => true,
            'preserve_original' => false, // Save space by not keeping originals
            'max_width' => 1920,
            'max_height' => 1080,
        ],
    ],

    'cdn' => [
        'enabled' => true,
        'url' => 'https://cdn.example.com',
        'push_to_cdn' => true,
        'purge_on_update' => true,
    ],

    'performance' => [
        'cache_headers' => [
            'max_age' => 31536000, // 1 year
            'public' => true,
        ],
    ],
];

// ============================================================================
// Example 6: Development Configuration
// ============================================================================

// config/cms.php for development environment
return [
    'development' => [
        'debug_mode' => true,
        'log_queries' => true,
        'log_level' => 'debug',
        'profiling' => true,
        'mock_data' => true, // Enable mock data for testing
    ],

    'security' => [
        'csrf_protection' => false, // Disable for API testing
        'content_security_policy' => false,
        'sanitize_content' => false, // Allow all HTML for testing
    ],
];

// ============================================================================
// Example 7: Production Security Configuration
// ============================================================================

// config/cms.php for production environment
return [
    'security' => [
        'csrf_protection' => true,
        'content_security_policy' => true,
        'sanitize_content' => true,
        'allowed_html_tags' => [
            'p', 'br', 'strong', 'em', 'u',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'a', 'img', 'blockquote'
        ],
        'rate_limiting' => [
            'enabled' => true,
            'requests_per_minute' => 60,
        ],
    ],

    'auth' => [
        'guard' => 'web',
        'middleware' => ['auth', 'verified', 'cms.permissions'],
        'permissions' => [
            'cms.edit' => 'cms-edit',
            'cms.publish' => 'cms-publish',
            'cms.admin' => 'cms-admin',
        ],
    ],
];

// config/cms-assets.php for production
return [
    'security' => [
        'check_file_content' => true,
        'block_executable_files' => true,
        'block_php_files' => true,
        'scan_for_malware' => true,
        'quarantine_suspicious_files' => true,
        'rate_limiting' => [
            'uploads_per_minute' => 5,
            'downloads_per_minute' => 100,
        ],
    ],

    'uploads' => [
        'max_file_size' => 5242880, // 5MB
        'max_files_per_upload' => 5,
        'scan_for_viruses' => true,
    ],
];