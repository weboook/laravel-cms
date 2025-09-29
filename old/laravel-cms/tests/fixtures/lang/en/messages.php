<?php

return [
    'welcome' => 'Welcome to our website',
    'about' => 'About Us',
    'contact' => 'Contact Us',
    'home' => 'Home',
    'services' => 'Our Services',
    'blog' => 'Blog',
    'news' => 'Latest News',

    // Navigation
    'navigation' => [
        'home' => 'Home',
        'about' => 'About',
        'services' => 'Services',
        'portfolio' => 'Portfolio',
        'blog' => 'Blog',
        'contact' => 'Contact',
    ],

    // Common actions
    'actions' => [
        'read_more' => 'Read More',
        'learn_more' => 'Learn More',
        'get_started' => 'Get Started',
        'download' => 'Download',
        'subscribe' => 'Subscribe',
        'submit' => 'Submit',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'view' => 'View',
        'search' => 'Search',
    ],

    // Forms
    'forms' => [
        'name' => 'Name',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'message' => 'Message',
        'subject' => 'Subject',
        'company' => 'Company',
        'website' => 'Website',
        'address' => 'Address',
        'city' => 'City',
        'state' => 'State',
        'zip_code' => 'ZIP Code',
        'country' => 'Country',
    ],

    // Status messages
    'status' => [
        'success' => 'Success!',
        'error' => 'Error occurred',
        'warning' => 'Warning',
        'info' => 'Information',
        'loading' => 'Loading...',
        'saving' => 'Saving...',
        'saved' => 'Saved successfully',
        'deleted' => 'Deleted successfully',
        'updated' => 'Updated successfully',
        'created' => 'Created successfully',
    ],

    // Content management
    'content' => [
        'title' => 'Title',
        'subtitle' => 'Subtitle',
        'description' => 'Description',
        'content' => 'Content',
        'excerpt' => 'Excerpt',
        'category' => 'Category',
        'tags' => 'Tags',
        'author' => 'Author',
        'date' => 'Date',
        'published' => 'Published',
        'draft' => 'Draft',
        'featured' => 'Featured',
        'image' => 'Image',
        'gallery' => 'Gallery',
    ],

    // Time and dates
    'time' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'tomorrow' => 'Tomorrow',
        'this_week' => 'This Week',
        'last_week' => 'Last Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year' => 'This Year',
        'ago' => ':time ago',
        'in' => 'in :time',
    ],

    // Pagination
    'pagination' => [
        'previous' => 'Previous',
        'next' => 'Next',
        'first' => 'First',
        'last' => 'Last',
        'showing' => 'Showing :from to :to of :total results',
        'no_results' => 'No results found',
    ],

    // Errors
    'errors' => [
        'not_found' => 'Page not found',
        'unauthorized' => 'Unauthorized access',
        'forbidden' => 'Access forbidden',
        'server_error' => 'Internal server error',
        'validation_failed' => 'Validation failed',
        'file_not_found' => 'File not found',
        'permission_denied' => 'Permission denied',
    ],

    // CMS specific
    'cms' => [
        'editor' => 'Content Editor',
        'preview' => 'Preview',
        'publish' => 'Publish',
        'unpublish' => 'Unpublish',
        'draft' => 'Save as Draft',
        'auto_save' => 'Auto-saved',
        'manual_save' => 'Save manually',
        'restore' => 'Restore',
        'backup' => 'Backup',
        'history' => 'Version History',
        'undo' => 'Undo',
        'redo' => 'Redo',
        'cut' => 'Cut',
        'copy' => 'Copy',
        'paste' => 'Paste',
        'find' => 'Find',
        'replace' => 'Replace',
        'settings' => 'Settings',
        'preferences' => 'Preferences',
        'theme' => 'Theme',
        'layout' => 'Layout',
        'sidebar' => 'Sidebar',
        'toolbar' => 'Toolbar',
        'menu' => 'Menu',
        'widget' => 'Widget',
        'component' => 'Component',
        'template' => 'Template',
        'page' => 'Page',
        'post' => 'Post',
        'media' => 'Media',
        'files' => 'Files',
        'images' => 'Images',
        'videos' => 'Videos',
        'documents' => 'Documents',
        'upload' => 'Upload',
        'download' => 'Download',
        'optimize' => 'Optimize',
        'compress' => 'Compress',
        'resize' => 'Resize',
        'crop' => 'Crop',
        'filter' => 'Filter',
        'sort' => 'Sort',
        'group' => 'Group',
        'archive' => 'Archive',
        'trash' => 'Trash',
        'permanent_delete' => 'Delete Permanently',
    ],

    // Nested translations for testing
    'nested' => [
        'level1' => [
            'level2' => [
                'level3' => [
                    'deep_value' => 'This is a deeply nested translation value',
                    'another_deep' => 'Another deeply nested value for testing',
                ],
                'value' => 'Level 2 value',
            ],
            'value' => 'Level 1 value',
        ],
        'simple' => 'Simple nested value',
        'complex' => [
            'array' => [
                'item1' => 'First item',
                'item2' => 'Second item',
                'item3' => 'Third item',
            ],
            'object' => [
                'property1' => 'Property 1 value',
                'property2' => 'Property 2 value',
            ],
        ],
    ],

    // Pluralization examples
    'items' => '{0} No items|{1} One item|[2,*] :count items',
    'users' => '{0} No users|{1} One user|[2,*] :count users',
    'files' => '{0} No files|{1} One file|[2,*] :count files',
    'comments' => '{0} No comments|{1} One comment|[2,*] :count comments',

    // With parameters
    'welcome_user' => 'Welcome back, :name!',
    'user_profile' => ':name\'s profile',
    'last_login' => 'Last login: :date at :time',
    'file_size' => 'File size: :size KB',
    'upload_progress' => 'Uploading... :percent% complete',
    'remaining_time' => ':minutes minutes and :seconds seconds remaining',
];