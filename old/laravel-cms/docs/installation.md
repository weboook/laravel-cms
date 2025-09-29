# Installation Guide

This guide will walk you through installing Laravel CMS in your Laravel application.

## 📋 System Requirements

### Minimum Requirements

| Component | Version | Notes |
|-----------|---------|-------|
| **PHP** | 8.1+ | Required extensions listed below |
| **Laravel** | 9.0+ | Compatible with Laravel 9.x and 10.x |
| **Node.js** | 16+ | For asset compilation |
| **NPM/Yarn** | Latest | Package management |

### PHP Extensions

Required PHP extensions:
- `fileinfo` - File type detection
- `gd` or `imagick` - Image processing
- `zip` - Archive handling
- `mbstring` - Multi-byte string support
- `openssl` - Encryption and security
- `pdo` - Database connections (if using database features)
- `curl` - HTTP requests
- `json` - JSON data handling

### Storage Requirements

- **Disk Space**: Minimum 100MB available
- **Permissions**: Write access to `storage/` and `public/` directories
- **Memory**: PHP memory limit of 128MB or higher recommended

### Optional Dependencies

| Service | Purpose | Required |
|---------|---------|----------|
| **Redis** | Caching and sessions | Recommended |
| **MySQL/PostgreSQL** | User management and analytics | Optional |
| **Git** | Version control integration | Recommended |
| **ImageMagick** | Advanced image processing | Optional |

## 🚀 Installation Methods

### Method 1: Composer (Recommended)

The easiest way to install Laravel CMS:

```bash
# Install the package
composer require webook/laravel-cms

# Publish configuration files
php artisan vendor:publish --tag="cms-config"

# Publish migration files (if using database features)
php artisan vendor:publish --tag="cms-migrations"

# Run migrations (if using database features)
php artisan migrate

# Publish public assets
php artisan vendor:publish --tag="cms-assets" --force
```

### Method 2: Manual Installation

For advanced users or custom setups:

1. **Download the Package**
   ```bash
   # Download from GitHub
   wget https://github.com/weboook/laravel-cms/archive/main.zip
   unzip main.zip -d vendor/webook/laravel-cms
   ```

2. **Register Service Provider**

   Add to `config/app.php`:
   ```php
   'providers' => [
       // ... other providers
       Webook\LaravelCMS\LaravelCMSServiceProvider::class,
   ],
   ```

3. **Register Facades** (Optional)
   ```php
   'aliases' => [
       // ... other aliases
       'CMS' => Webook\LaravelCMS\Facades\CMS::class,
   ],
   ```

### Method 3: Development Installation

For contributing or development:

```bash
# Clone the repository
git clone https://github.com/weboook/laravel-cms.git
cd laravel-cms

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Build assets
npm run build

# Link to your Laravel project
composer config repositories.laravel-cms path ../laravel-cms
composer require webook/laravel-cms:@dev
```

## ⚙️ Configuration

### 1. Environment Variables

Add these variables to your `.env` file:

```env
# Laravel CMS Configuration
CMS_ENABLED=true
CMS_ROUTE_PREFIX=cms
CMS_MIDDLEWARE=web
CMS_STORAGE_DISK=local
CMS_CACHE_DRIVER=file

# Authentication (optional)
CMS_AUTH_ENABLED=true
CMS_AUTH_GUARD=web
CMS_AUTH_MIDDLEWARE=auth

# File Storage
CMS_CONTENT_PATH=content
CMS_TRANSLATIONS_PATH=lang
CMS_MEDIA_PATH=media

# Performance
CMS_CACHE_TTL=3600
CMS_MINIFY_HTML=true
CMS_LAZY_LOAD=true

# Security
CMS_SANITIZE_HTML=true
CMS_ALLOWED_TAGS="p,br,strong,em,u,h1,h2,h3,h4,h5,h6,ul,ol,li,a,img"
CMS_MAX_FILE_SIZE=10240

# Multi-language
CMS_DEFAULT_LOCALE=en
CMS_FALLBACK_LOCALE=en
CMS_SUPPORTED_LOCALES=en,es,fr

# External Services (optional)
CMS_GOOGLE_TRANSLATE_KEY=
CMS_CLOUDINARY_URL=
CMS_AWS_S3_BUCKET=
```

### 2. Configuration File

Publish and customize the configuration:

```bash
php artisan vendor:publish --tag="cms-config"
```

Edit `config/cms.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel CMS Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('CMS_ENABLED', true),

    'route' => [
        'prefix' => env('CMS_ROUTE_PREFIX', 'cms'),
        'middleware' => explode(',', env('CMS_MIDDLEWARE', 'web')),
    ],

    'authentication' => [
        'enabled' => env('CMS_AUTH_ENABLED', true),
        'guard' => env('CMS_AUTH_GUARD', 'web'),
        'middleware' => env('CMS_AUTH_MIDDLEWARE', 'auth'),
        'permissions' => [
            'edit_content' => 'cms:edit',
            'manage_translations' => 'cms:translate',
            'admin_access' => 'cms:admin',
        ],
    ],

    'storage' => [
        'disk' => env('CMS_STORAGE_DISK', 'local'),
        'content_path' => env('CMS_CONTENT_PATH', 'content'),
        'translations_path' => env('CMS_TRANSLATIONS_PATH', 'lang'),
        'media_path' => env('CMS_MEDIA_PATH', 'media'),
    ],

    'cache' => [
        'driver' => env('CMS_CACHE_DRIVER', 'file'),
        'ttl' => env('CMS_CACHE_TTL', 3600),
        'tags' => ['cms', 'content', 'translations'],
    ],

    'editor' => [
        'enabled' => true,
        'toolbar' => 'basic', // basic, advanced, full
        'plugins' => ['lists', 'link', 'image', 'table'],
        'skin' => 'oxide', // oxide, oxide-dark
    ],

    'security' => [
        'sanitize_html' => env('CMS_SANITIZE_HTML', true),
        'allowed_tags' => env('CMS_ALLOWED_TAGS', 'p,br,strong,em,u,h1,h2,h3,h4,h5,h6,ul,ol,li,a,img'),
        'max_file_size' => env('CMS_MAX_FILE_SIZE', 10240), // KB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'doc', 'docx'],
    ],

    'performance' => [
        'minify_html' => env('CMS_MINIFY_HTML', true),
        'lazy_load' => env('CMS_LAZY_LOAD', true),
        'cache_images' => true,
        'optimize_images' => true,
    ],

    'localization' => [
        'default_locale' => env('CMS_DEFAULT_LOCALE', 'en'),
        'fallback_locale' => env('CMS_FALLBACK_LOCALE', 'en'),
        'supported_locales' => explode(',', env('CMS_SUPPORTED_LOCALES', 'en')),
        'auto_detect' => true,
    ],

    'integrations' => [
        'google_translate' => [
            'enabled' => !empty(env('CMS_GOOGLE_TRANSLATE_KEY')),
            'api_key' => env('CMS_GOOGLE_TRANSLATE_KEY'),
        ],
        'cloudinary' => [
            'enabled' => !empty(env('CMS_CLOUDINARY_URL')),
            'url' => env('CMS_CLOUDINARY_URL'),
        ],
        'aws_s3' => [
            'enabled' => !empty(env('CMS_AWS_S3_BUCKET')),
            'bucket' => env('CMS_AWS_S3_BUCKET'),
        ],
    ],
];
```

### 3. Database Setup (Optional)

If you want to use database features like user management:

```bash
# Publish migrations
php artisan vendor:publish --tag="cms-migrations"

# Run migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed --class=CMSSeeder
```

### 4. File Permissions

Set proper file permissions:

```bash
# Make storage directories writable
chmod -R 775 storage/
chmod -R 775 public/

# Set ownership (adjust user/group as needed)
chown -R www-data:www-data storage/
chown -R www-data:www-data public/

# Create CMS directories
mkdir -p storage/cms/{content,translations,media,cache}
chmod -R 775 storage/cms/
```

## 🎨 Frontend Assets

### 1. Publish Assets

```bash
# Publish CSS, JS, and other assets
php artisan vendor:publish --tag="cms-assets" --force

# This will copy files to:
# - public/vendor/laravel-cms/css/
# - public/vendor/laravel-cms/js/
# - public/vendor/laravel-cms/images/
```

### 2. Include in Your Layout

Add to your main layout file (`resources/views/layouts/app.blade.php`):

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Your existing styles -->

    <!-- Laravel CMS Styles -->
    @cms_styles
</head>
<body>
    <!-- Your content -->

    <!-- Your existing scripts -->

    <!-- Laravel CMS Scripts -->
    @cms_scripts

    <!-- CMS Editor (only when in edit mode) -->
    @cms
</body>
</html>
```

### 3. Custom Styling (Optional)

Customize the CMS appearance:

```bash
# Publish view files for customization
php artisan vendor:publish --tag="cms-views"

# This creates:
# - resources/views/vendor/laravel-cms/
```

## 🚀 First Steps

### 1. Verify Installation

Create a test route to verify installation:

```php
// routes/web.php
Route::get('/test-cms', function () {
    return view('test-cms');
});
```

Create the test view:

```blade
<!-- resources/views/test-cms.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container">
    <h1 data-cms-text="test.title">Welcome to Laravel CMS</h1>
    <p data-cms-text="test.description">
        This is a test page. Click to edit this content!
    </p>

    <div data-cms-rich="test.content">
        <h2>Rich Content Area</h2>
        <p>This area supports <strong>rich text</strong> editing with HTML formatting.</p>
    </div>

    <img data-cms-image="test.banner"
         src="{{ cms_image('test.banner', '/default-banner.jpg') }}"
         alt="Test Banner"
         class="img-fluid">
</div>
@endsection
```

### 2. Enable Edit Mode

Visit your test page with edit mode enabled:
```
http://your-site.com/test-cms?cms=1
```

### 3. Create Admin User (If Using Authentication)

```bash
# Create admin user
php artisan cms:create-admin
```

Or manually:

```php
// In tinker or a seeder
use App\Models\User;

$admin = User::create([
    'name' => 'CMS Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
]);

// Assign CMS permissions (if using a permission system)
$admin->givePermissionTo(['cms:edit', 'cms:translate', 'cms:admin']);
```

## 🔧 Advanced Configuration

### 1. Custom Content Types

Register custom content types in a service provider:

```php
// app/Providers/CMSServiceProvider.php
use Webook\LaravelCMS\ContentTypes\ContentTypeRegistry;

public function boot()
{
    $registry = app(ContentTypeRegistry::class);

    $registry->register('custom-type', \App\CMS\CustomContentType::class);
}
```

### 2. Middleware Configuration

Add custom middleware to CMS routes:

```php
// config/cms.php
'route' => [
    'middleware' => ['web', 'auth', 'cms.permissions'],
],
```

### 3. Storage Configuration

Use different storage drivers:

```php
// For S3 storage
'storage' => [
    'disk' => 's3',
    'content_path' => 'cms/content',
],

// For custom disk
'disks' => [
    'cms' => [
        'driver' => 'local',
        'root' => storage_path('cms'),
        'url' => env('APP_URL') . '/storage/cms',
        'visibility' => 'public',
    ],
],
```

### 4. Performance Optimization

Enable additional performance features:

```php
// config/cms.php
'performance' => [
    'enable_compression' => true,
    'cache_compiled_views' => true,
    'preload_translations' => true,
    'optimize_images' => true,
    'use_cdn' => env('CMS_USE_CDN', false),
],
```

## 🧪 Testing Installation

### 1. Run Tests

```bash
# Install test dependencies
composer install --dev

# Run Laravel CMS tests
php artisan test --testsuite=CMS

# Run with coverage
php artisan test --testsuite=CMS --coverage
```

### 2. Browser Tests

```bash
# Install Dusk
composer require --dev laravel/dusk
php artisan dusk:install

# Run CMS browser tests
php artisan dusk tests/Browser/CMSTest.php
```

### 3. Performance Tests

```bash
# Test page load times
php artisan cms:benchmark

# Test with different content sizes
php artisan cms:stress-test --users=50 --duration=60
```

## 🐛 Troubleshooting

### Common Issues

#### 1. Assets Not Loading

**Problem**: CSS/JS files return 404 errors

**Solution**:
```bash
# Re-publish assets
php artisan vendor:publish --tag="cms-assets" --force

# Clear cache
php artisan cache:clear
php artisan view:clear

# Check public directory permissions
ls -la public/vendor/laravel-cms/
```

#### 2. Edit Mode Not Working

**Problem**: Edit interface doesn't appear

**Solutions**:
- Check if CMS is enabled: `CMS_ENABLED=true` in `.env`
- Verify middleware configuration
- Check browser console for JavaScript errors
- Ensure CSRF token is present

#### 3. File Upload Issues

**Problem**: Cannot upload images/files

**Solutions**:
```bash
# Check file permissions
chmod -R 775 storage/cms/
chown -R www-data:www-data storage/cms/

# Check PHP settings
php -i | grep -E "(upload_max_filesize|post_max_size|max_execution_time)"

# Increase limits in php.ini if needed
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 300
```

#### 4. Translation Issues

**Problem**: Translations not saving or loading

**Solutions**:
- Check language file permissions: `chmod -R 775 resources/lang/`
- Verify locale configuration in `config/app.php`
- Clear translation cache: `php artisan cache:clear`

#### 5. Performance Issues

**Problem**: Slow page loading

**Solutions**:
```bash
# Enable caching
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize

# Enable Redis caching
# Set CMS_CACHE_DRIVER=redis in .env
```

### Debug Mode

Enable debug mode for troubleshooting:

```env
# .env
CMS_DEBUG=true
CMS_LOG_LEVEL=debug
```

Check logs for errors:
```bash
tail -f storage/logs/laravel.log
```

### System Check

Run the built-in system check:

```bash
php artisan cms:check-system
```

This will verify:
- PHP version and extensions
- File permissions
- Configuration settings
- Database connectivity (if enabled)
- Cache functionality

## 📞 Getting Help

If you encounter issues not covered here:

1. **Check Documentation**: [docs/troubleshooting.md](troubleshooting.md)
2. **Search Issues**: [GitHub Issues](https://github.com/weboook/laravel-cms/issues)
3. **Ask Community**: [Discord Chat](https://discord.gg/laravel-cms)
4. **Report Bugs**: [New Issue](https://github.com/weboook/laravel-cms/issues/new)

## ✅ Installation Checklist

- [ ] System requirements verified
- [ ] Package installed via Composer
- [ ] Configuration published and customized
- [ ] Environment variables set
- [ ] File permissions configured
- [ ] Assets published
- [ ] Test page created and working
- [ ] Edit mode functional
- [ ] Authentication configured (if needed)
- [ ] Performance optimizations applied
- [ ] Tests passing

## 🎉 Next Steps

Now that Laravel CMS is installed:

1. **Learn the Basics**: Read the [Usage Guide](usage.md)
2. **Configure Features**: Check [Configuration Options](configuration.md)
3. **Explore Examples**: Browse [Code Examples](../examples/)
4. **Join Community**: [Discord Server](https://discord.gg/laravel-cms)

---

**Need help?** Join our [Discord community](https://discord.gg/laravel-cms) or [open an issue](https://github.com/weboook/laravel-cms/issues/new) on GitHub.