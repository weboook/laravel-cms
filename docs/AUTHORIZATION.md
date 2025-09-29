# CMS Authorization Documentation

The Laravel CMS package includes a flexible authorization system that allows you to control who can access and use the CMS editor.

## Quick Start

By default, the CMS is available to everyone. To enable authorization:

1. Publish the configuration file:
```bash
php artisan vendor:publish --tag=cms-config
```

2. Enable authorization in your `.env` file:
```env
CMS_AUTH_ENABLED=true
```

3. Configure your authorization rules in `config/cms-auth.php`

## Authorization Methods

### 1. Simple Role-Based Access

Edit `config/cms-auth.php`:

```php
'auth' => [
    'enabled' => true,
    'roles' => [
        'admin',
        'editor',
        'content-manager',
    ],
],
```

### 2. Permission-Based Access

For fine-grained control using permissions:

```php
'auth' => [
    'enabled' => true,
    'permissions' => [
        'edit-content',
        'manage-cms',
    ],
],
```

### 3. User ID Whitelist

Allow specific users by their IDs:

```php
'auth' => [
    'enabled' => true,
    'user_ids' => [1, 2, 5, 10],
],
```

### 4. Email Domain Restrictions

Allow users from specific email domains:

```php
'auth' => [
    'enabled' => true,
    'email_domains' => [
        'company.com',
        'admin.example.com',
    ],
],
```

### 5. Custom Authorization Handler

For complete control, define a custom handler:

```php
'auth' => [
    'enabled' => true,
    'handler' => function (\Illuminate\Http\Request $request) {
        $user = $request->user();

        // Your custom logic here
        if (!$user) {
            return false;
        }

        // Example: Check if user has an active subscription
        if ($user->subscription && $user->subscription->active) {
            return true;
        }

        // Example: Check custom user property
        if ($user->is_staff || $user->is_admin) {
            return true;
        }

        return false;
    },
],
```

### 6. Custom User Check

Add additional checks for authenticated users:

```php
'auth' => [
    'enabled' => true,
    'user_check' => function ($user) {
        // Check if user's account is verified
        return $user->email_verified_at !== null;
    },
],
```

## Permission Levels

The CMS supports different permission levels:

- **admin** - Full access to all CMS features
- **editor** - Can edit content but may have restricted features
- **viewer** - Can see the CMS but cannot make changes

Configure permission levels:

```php
'auth' => [
    'permission_level_handler' => function ($request) {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            return 'admin';
        }

        if ($user->hasRole('editor')) {
            return 'editor';
        }

        return 'viewer';
    },
],
```

## Route Exclusions

Exclude certain routes from showing the CMS, even for authorized users:

```php
'auth' => [
    'excluded_routes' => [
        'admin.*',      // All admin routes
        'api.*',        // All API routes
        'login',        // Login page
        'register',     // Registration page
    ],

    'excluded_paths' => [
        'admin/*',      // All paths starting with admin/
        'dashboard',    // Dashboard path
        'profile/*',    // All profile paths
    ],
],
```

## Using with Laravel Permission Packages

### With Spatie Laravel-Permission

The CMS automatically works with Spatie's Laravel-Permission package:

```php
// In your User model
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}

// In cms-auth.php config
'auth' => [
    'enabled' => true,
    'roles' => ['editor', 'admin'],
    'permissions' => ['edit-content'],
],
```

### With Laravel Bouncer

```php
// In cms-auth.php config
'auth' => [
    'enabled' => true,
    'handler' => function ($request) {
        $user = $request->user();
        return $user && $user->can('access-cms');
    },
],
```

## Multiple Guards

To use different authentication guards:

```php
'auth' => [
    'enabled' => true,
    'guards' => ['web', 'admin'],
    'roles' => ['editor'],
],
```

## Creating a Custom CMSAuthHandler

For complex authorization logic, you can extend the CMSAuthHandler class:

```php
namespace App\Services;

use Webook\LaravelCMS\Services\CMSAuthHandler;
use Illuminate\Http\Request;

class CustomCMSAuthHandler extends CMSAuthHandler
{
    public function canAccessCMS(Request $request): bool
    {
        // Your custom implementation
        $user = $request->user();

        if (!$user) {
            return false;
        }

        // Check if user is in a specific team
        if ($user->team && $user->team->has_cms_access) {
            return true;
        }

        // Check IP whitelist
        $allowedIps = ['192.168.1.1', '10.0.0.1'];
        if (in_array($request->ip(), $allowedIps)) {
            return true;
        }

        return parent::canAccessCMS($request);
    }
}
```

Register your custom handler in a service provider:

```php
$this->app->singleton(
    \Webook\LaravelCMS\Services\CMSAuthHandler::class,
    \App\Services\CustomCMSAuthHandler::class
);
```

## Environment-Based Configuration

You can use environment variables for quick configuration:

```env
CMS_AUTH_ENABLED=true
CMS_AUTH_ROLES="admin,editor"
CMS_AUTH_USER_IDS="1,2,3"
```

Then in your config:

```php
'auth' => [
    'enabled' => env('CMS_AUTH_ENABLED', false),
    'roles' => explode(',', env('CMS_AUTH_ROLES', '')),
    'user_ids' => array_map('intval', explode(',', env('CMS_AUTH_USER_IDS', ''))),
],
```

## Testing Authorization

To test your authorization setup:

```php
// In a test or tinker session
$authHandler = app(\Webook\LaravelCMS\Services\CMSAuthHandler::class);
$request = request();

// Check if current request can access CMS
$canAccess = $authHandler->canAccessCMS($request);

// Check permission level
$level = $authHandler->getPermissionLevel($request);

// Check if should show on current route
$shouldShow = $authHandler->shouldShowOnRoute($request);
```

## Troubleshooting

### CMS not showing for authorized users

1. Check that authorization is enabled in config
2. Verify user meets the configured criteria
3. Check for route exclusions
4. Enable debug logging in CMSAuthHandler

### CMS showing for unauthorized users

1. Ensure `CMS_AUTH_ENABLED=true` in `.env`
2. Clear config cache: `php artisan config:clear`
3. Verify authorization rules are properly configured

## Security Best Practices

1. Always enable authorization in production
2. Use the most restrictive rules appropriate for your use case
3. Regularly review user access logs
4. Consider IP whitelisting for sensitive environments
5. Use permission levels to limit features
6. Exclude admin and API routes from CMS