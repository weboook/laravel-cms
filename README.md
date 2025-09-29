# Laravel CMS Package

A Webflow-like inline content management system for Laravel applications.

## Installation

### 1. Install via Composer

```bash
composer require webook/laravel-cms
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="Webook\LaravelCMS\CMSServiceProvider" --tag=cms-config
```

### 3. Publish and Run Migrations

```bash
php artisan vendor:publish --provider="Webook\LaravelCMS\CMSServiceProvider" --tag=cms-migrations
php artisan migrate
```

### 4. Create Storage Link

For image uploads to work properly, you need to create a symbolic link:

```bash
php artisan storage:link
```

### 5. Publish Assets (Optional)

If you want to customize the CMS assets:

```bash
php artisan vendor:publish --provider="Webook\LaravelCMS\CMSServiceProvider" --tag=cms-assets
```

## Configuration

The configuration file will be published to `config/cms.php`. You can customize:

- **Toolbar settings**: Position (top/bottom), theme (dark/light), auto-injection
- **Storage settings**: Disk and path for media uploads
- **Cache settings**: Enable/disable caching, TTL
- **Editor settings**: Toolbar buttons available in the rich text editor
- **Media settings**: Upload disk, path, max file size, allowed file types

## Usage

### Basic Usage

Once installed, the CMS toolbar will automatically be injected into your pages when enabled.

1. Navigate to any page in your application
2. Click "Edit" in the toolbar to enter edit mode
3. Hover over any content to see editable areas
4. Click to edit text inline
5. For images, hover to see the gear icon for changing images
6. Click "Save" to persist changes

### Permissions

By default, the CMS is available without authentication. To restrict access, add your own middleware to the configuration:

```php
// config/cms.php
'middleware' => [
    'web',
    'auth',  // Add authentication
    'can:edit-content', // Add authorization
],
```

### Excluding Content

To prevent content from being editable, add the `data-cms-ignore` attribute:

```html
<div data-cms-ignore="true">
    This content won't be editable
</div>
```

### Database Content

Content fetched from database models will automatically be marked as "coming soon" and won't be editable yet.

## Features

- ✅ Inline content editing
- ✅ Rich text editor with formatting options
- ✅ Image upload with drag-and-drop
- ✅ Link editing
- ✅ Automatic backup system
- ✅ Dark mode toolbar
- ✅ Mobile responsive
- ✅ Pages explorer
- ✅ Multi-language ready (coming soon)
- ✅ Media library (coming soon)

## Troubleshooting

### Images return 403 Forbidden

Make sure you've created the storage link:

```bash
php artisan storage:link
```

### Content changes don't persist

Clear the view cache after updates:

```bash
php artisan view:clear
php artisan config:clear
```

### Toolbar doesn't appear

1. Check that CMS is enabled in your `.env`:
   ```
   CMS_ENABLED=true
   ```

2. Clear caches:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x

## License

MIT License

## Support

For issues and questions, please use the GitHub issue tracker.