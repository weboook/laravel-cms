<?php

return [
    'enabled' => env('CMS_ENABLED', true),

    'toolbar' => [
        'enabled' => env('CMS_TOOLBAR_ENABLED', true),
        'position' => env('CMS_TOOLBAR_POSITION', 'bottom'), // bottom, top
        'theme' => env('CMS_TOOLBAR_THEME', 'dark'), // dark, light
        'auto_inject' => env('CMS_TOOLBAR_AUTO_INJECT', true),
    ],

    'storage' => [
        'driver' => env('CMS_STORAGE_DRIVER', 'file'),
        'path' => env('CMS_STORAGE_PATH', storage_path('cms')),
    ],

    'cache' => [
        'enabled' => env('CMS_CACHE_ENABLED', true),
        'ttl' => env('CMS_CACHE_TTL', 3600),
    ],

    'editor' => [
        'toolbar' => [
            'bold', 'italic', 'underline', 'strike',
            'h1', 'h2', 'h3', 'h4',
            'bulletList', 'orderedList',
            'link', 'image',
            'align', 'color',
            'undo', 'redo'
        ],
    ],

    'media' => [
        'disk' => env('CMS_MEDIA_DISK', 'public'),
        'path' => env('CMS_MEDIA_PATH', 'cms/media'),
        'max_upload_size' => env('CMS_MAX_UPLOAD_SIZE', 10240), // in KB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'doc', 'docx'],
    ],

    'middleware' => [
        'web',
    ],

    'route_prefix' => 'cms',
];