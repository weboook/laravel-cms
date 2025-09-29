# Laravel Package Development Setup

This guide explains how to set up local development linking between your Laravel package and test application using Composer path repositories with symlinks.

## 🔗 How Symlinks Work

### What is a Symlink?

A **symbolic link (symlink)** is a file system feature that creates a reference to another file or directory. Instead of copying files, it creates a "pointer" that redirects to the original location.

```bash
# Example symlink structure
cms-test-app/vendor/webook/laravel-cms -> ../../../laravel-cms
```

### Symlink vs Copy Comparison

| Aspect | Symlink | Copy |
|--------|---------|------|
| **Real-time updates** | ✅ Instant | ❌ Manual sync required |
| **Disk space** | ✅ Minimal | ❌ Duplicated files |
| **Development speed** | ✅ Fast iteration | ❌ Slow rebuild cycle |
| **File consistency** | ✅ Always in sync | ❌ Can get out of sync |
| **Git tracking** | ✅ One source of truth | ❌ Multiple copies |

## 🚀 Quick Start

### Prerequisites

- PHP 8.0+
- Composer 2.x
- Laravel 9.x, 10.x, or 11.x
- Git

### 1. Initial Setup

```bash
# Make setup script executable and run
chmod +x setup-development.sh
./setup-development.sh
```

This script will:
- ✅ Verify package directory exists
- ✅ Create Laravel test application
- ✅ Add path repository to composer.json
- ✅ Require the local package with symlinks
- ✅ Publish package assets
- ✅ Set up file watchers

### 2. Manual Setup (Alternative)

If you prefer manual setup:

```bash
# Create test application
composer create-project laravel/laravel cms-test-app

# Navigate to test app
cd cms-test-app

# Add path repository to composer.json
```

**Add this to your `composer.json`:**

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-cms",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "webook/laravel-cms": "*@dev"
    }
}
```

```bash
# Install the package
composer require webook/laravel-cms:*@dev

# Publish assets
php artisan vendor:publish --provider="Webook\\LaravelCMS\\CMSServiceProvider"
```

## 🛠️ Development Commands

### Using Make (Recommended)

```bash
# View all available commands
make help

# Initial setup
make setup

# Start development server
make serve

# Watch for changes
make watch

# Refresh autoloader
make refresh

# Run tests
make test

# Format code
make format

# Check symlink status
make link-check

# Show environment status
make status
```

### Using Composer Commands

```bash
# In package directory (../laravel-cms)
composer test              # Run PHPUnit tests
composer test-coverage     # Run tests with coverage
composer format           # Format code with Pint
composer analyse          # Check code style

# In test app directory (./cms-test-app)
composer dump-autoload    # Refresh autoloader
php artisan vendor:publish --force  # Republish assets
```

## 📁 Directory Structure

```
public_html/
├── laravel-cms/              # Your package source
│   ├── src/
│   ├── tests/
│   ├── config/
│   └── composer.json
├── cms-test-app/             # Test Laravel application
│   ├── vendor/
│   │   └── webook/
│   │       └── laravel-cms/  # Symlink to ../../laravel-cms
│   ├── composer.json         # Contains path repository
│   └── artisan
├── setup-development.sh      # Setup script
├── Makefile                  # Development commands
└── DEVELOPMENT.md           # This file
```

## 🔄 Development Workflow

### 1. Daily Development

```bash
# Start your development session
make watch    # In one terminal (watches for changes)
make serve    # In another terminal (development server)
```

### 2. Making Changes

1. **Edit package files** in `../laravel-cms/src/`
2. **Changes are immediately available** in test app (thanks to symlinks!)
3. **Autoloader refreshes automatically** (if using file watcher)
4. **Test your changes** in the browser or via tests

### 3. Testing

```bash
# Run package tests
make test

# Run with coverage
make test-coverage

# Format code
make format

# Run all quality checks
make test-all
```

## 🔧 Troubleshooting

### Symlink Not Working?

```bash
# Check symlink status
make link-check

# If showing "copied" instead of "symlinked"
cd cms-test-app
rm -rf vendor/webook/laravel-cms
composer install
```

### Autoloader Issues?

```bash
# Refresh everything
make refresh

# Or manually
cd cms-test-app
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Package Not Found?

```bash
# Verify repository configuration
cd cms-test-app
composer config repositories

# Should show your path repository
```

### File Watcher Not Working?

**Linux:**
```bash
sudo apt-get install inotify-tools
```

**macOS:**
```bash
brew install fswatch
```

**Windows:**
Use WSL or manually run `composer dump-autoload` after changes.

## 🎯 Benefits of This Setup

### 1. **Real-time Development**
- Edit package files and see changes immediately
- No manual copying or rebuilding required
- Faster development cycle

### 2. **Single Source of Truth**
- All changes happen in the package directory
- No risk of forgetting to sync changes
- Git tracks only the package files

### 3. **Efficient Testing**
- Test package in a real Laravel environment
- Use Laravel's testing tools and features
- Easy integration testing

### 4. **Production-like Environment**
- Test how package behaves when installed via Composer
- Verify service provider registration
- Test asset publishing

## 🔒 Security Considerations

### Development Only
This setup is for development only. In production:
- Packages are installed from Packagist
- No symlinks are used
- Files are copied to vendor directory

### Local Environment
- Path repositories only work locally
- Other developers need their own setup
- CI/CD uses normal Composer installation

## 📝 Advanced Configuration

### Custom Test App Configuration

If you need specific Laravel configuration for testing:

```php
// cms-test-app/config/cms.php
return [
    'test_mode' => true,
    'debug_level' => 'verbose',
    // ... other test-specific settings
];
```

### Multiple Test Apps

You can create multiple test applications for different scenarios:

```bash
# Create different test environments
composer create-project laravel/laravel cms-test-app-9 "9.*"
composer create-project laravel/laravel cms-test-app-10 "10.*"
composer create-project laravel/laravel cms-test-app-11 "11.*"
```

### Custom Composer Scripts

Add these to your package's `composer.json`:

```json
{
    "scripts": {
        "dev-setup": "cd ../cms-test-app && composer install",
        "dev-test": [
            "@test",
            "cd ../cms-test-app && php artisan test"
        ]
    }
}
```

## 🚀 Deployment

When ready to publish your package:

1. **Tag a release:**
   ```bash
   cd laravel-cms
   git tag v1.0.0
   git push origin v1.0.0
   ```

2. **Submit to Packagist:**
   - Register at [packagist.org](https://packagist.org)
   - Add your GitHub repository
   - Enable auto-updating

3. **Update installation docs:**
   ```bash
   composer require webook/laravel-cms
   ```

## 📚 Additional Resources

- [Composer Path Repositories](https://getcomposer.org/doc/05-repositories.md#path)
- [Laravel Package Development](https://laravel.com/docs/packages)
- [PHP Symlinks Documentation](https://www.php.net/manual/en/function.symlink.php)
- [Laravel Pint Code Style](https://laravel.com/docs/pint)