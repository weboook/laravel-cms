<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure who can access the CMS editor. By default, the CMS is available
    | to everyone. You can restrict access using various methods below.
    |
    */

    'auth' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Authorization
        |--------------------------------------------------------------------------
        |
        | Set to true to enable authorization checks for CMS access.
        | When false, everyone can see and use the CMS editor.
        |
        */
        'enabled' => env('CMS_AUTH_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | Custom Authorization Handler
        |--------------------------------------------------------------------------
        |
        | Define a custom callback to determine if a user can access the CMS.
        | This overrides all other authorization settings.
        |
        | Example:
        | 'handler' => function ($request) {
        |     return $request->user() && $request->user()->isAdmin();
        | },
        |
        */
        'handler' => null,

        /*
        |--------------------------------------------------------------------------
        | Auth Guards
        |--------------------------------------------------------------------------
        |
        | Specify which auth guards to check. If empty, uses the default guard.
        |
        */
        'guards' => [],

        /*
        |--------------------------------------------------------------------------
        | Required Roles
        |--------------------------------------------------------------------------
        |
        | Users must have at least one of these roles to access the CMS.
        | Works with Laravel permission packages like Spatie/laravel-permission.
        |
        */
        'roles' => [
            // 'admin',
            // 'editor',
            // 'content-manager',
        ],

        /*
        |--------------------------------------------------------------------------
        | Required Permissions
        |--------------------------------------------------------------------------
        |
        | Users must have all of these permissions to access the CMS.
        |
        */
        'permissions' => [
            // 'edit-content',
            // 'manage-cms',
        ],

        /*
        |--------------------------------------------------------------------------
        | Allowed User IDs
        |--------------------------------------------------------------------------
        |
        | Whitelist specific user IDs that can access the CMS.
        |
        */
        'user_ids' => [
            // 1,
            // 2,
        ],

        /*
        |--------------------------------------------------------------------------
        | Admin User IDs
        |--------------------------------------------------------------------------
        |
        | User IDs that have full admin access to the CMS.
        |
        */
        'admin_user_ids' => [
            // 1,
        ],

        /*
        |--------------------------------------------------------------------------
        | Allowed Email Domains
        |--------------------------------------------------------------------------
        |
        | Only users with email addresses from these domains can access the CMS.
        |
        */
        'email_domains' => [
            // 'company.com',
            // 'admin.example.com',
        ],

        /*
        |--------------------------------------------------------------------------
        | Custom User Check
        |--------------------------------------------------------------------------
        |
        | Additional custom callback to check if a specific user can access CMS.
        |
        | Example:
        | 'user_check' => function ($user) {
        |     return $user->subscription && $user->subscription->active;
        | },
        |
        */
        'user_check' => null,

        /*
        |--------------------------------------------------------------------------
        | Excluded Routes
        |--------------------------------------------------------------------------
        |
        | Routes where the CMS should never appear, even for authorized users.
        |
        */
        'excluded_routes' => [
            // 'admin.*',
            // 'api.*',
        ],

        /*
        |--------------------------------------------------------------------------
        | Excluded Paths
        |--------------------------------------------------------------------------
        |
        | Path patterns where the CMS should never appear.
        | Supports wildcards: admin/* will match admin/users, admin/posts, etc.
        |
        */
        'excluded_paths' => [
            // 'admin/*',
            // 'api/*',
            // 'auth/*',
        ],

        /*
        |--------------------------------------------------------------------------
        | Custom Permission Level Handler
        |--------------------------------------------------------------------------
        |
        | Define custom logic to determine user's permission level in CMS.
        | Should return: 'none', 'viewer', 'editor', or 'admin'
        |
        | Example:
        | 'permission_level_handler' => function ($request) {
        |     $user = $request->user();
        |     if ($user->hasRole('super-admin')) return 'admin';
        |     if ($user->hasRole('editor')) return 'editor';
        |     return 'viewer';
        | },
        |
        */
        'permission_level_handler' => null,
    ],
];