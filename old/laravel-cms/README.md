# Laravel CMS

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webook/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/webook/laravel-cms)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/webook/laravel-cms/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/weboook/laravel-cms/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/webook/laravel-cms/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/weboook/laravel-cms/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/webook/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/webook/laravel-cms)
[![License](https://img.shields.io/packagist/l/webook/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/webook/laravel-cms)

A powerful, file-based Content Management System for Laravel applications that enables in-context content editing with multi-language support, Git integration, and zero-database content storage.

## ✨ Features

### 🚀 **Core Features**
- ✅ **In-context Content Editing** - Edit content directly on your pages with a rich WYSIWYG editor
- ✅ **Multi-language Support** - Built-in translation management with 40+ language support
- ✅ **File-based Storage** - No database required - content stored in organized file structures
- ✅ **Git Integration** - Version control for all content changes with automatic commit tracking
- ✅ **Zero Configuration** - Works out of the box with sensible defaults
- ✅ **Laravel Integration** - Seamlessly integrates with existing Laravel applications

### 🎨 **Content Management**
- ✅ **Rich Text Editor** - TinyMCE integration with custom toolbar and plugins
- ✅ **Image Management** - Upload, resize, and optimize images with automatic thumbnail generation
- ✅ **Link Management** - Smart link detection and validation for internal/external links
- ✅ **Component Support** - Edit Blade components and dynamic content blocks
- ✅ **Bulk Operations** - Mass edit, import/export translations and content

### 🔐 **Security & Permissions**
- ✅ **Role-based Access Control** - Granular permissions for editors, translators, and admins
- ✅ **XSS Protection** - Advanced content sanitization and security filtering
- ✅ **CSRF Protection** - Secure forms and API endpoints
- ✅ **File Upload Security** - Malware scanning and file type validation
- ✅ **Audit Logging** - Complete activity tracking and change history

### 🚀 **Performance & Scalability**
- ✅ **Intelligent Caching** - Multi-layer caching for optimal performance
- ✅ **CDN Integration** - Seamless integration with popular CDN services
- ✅ **Asset Optimization** - Automatic CSS/JS minification and image compression
- ✅ **Lazy Loading** - Progressive content loading for better UX
- ✅ **Performance Monitoring** - Built-in performance tracking and optimization tools

### 🔧 **Developer Experience**
- ✅ **RESTful API** - Complete API for headless CMS functionality
- ✅ **Event System** - Hooks for custom functionality and integrations
- ✅ **Plugin Architecture** - Extensible plugin system for custom features
- ✅ **Testing Suite** - Comprehensive test coverage with automated testing tools
- ✅ **Documentation** - Extensive documentation with code examples

### 🌐 **Integrations**
- ✅ **Translation Services** - Google Translate, DeepL, Azure Translator integration
- ✅ **Cloud Storage** - AWS S3, Google Cloud, DigitalOcean Spaces support
- ✅ **Analytics** - Google Analytics, Adobe Analytics integration
- ✅ **Webhooks** - Real-time notifications for content changes
- ✅ **Social Authentication** - OAuth integration with major providers

## 📸 Screenshots

![Content Editor](docs/images/editor-screenshot.png)
*In-context content editing with rich WYSIWYG editor*

![Translation Manager](docs/images/translation-manager.png)
*Multi-language translation management interface*

![File Manager](docs/images/file-manager.png)
*Integrated file and media management system*

## 🚀 Quick Start

Get up and running in less than 5 minutes:

### 1. Install the Package

```bash
composer require webook/laravel-cms
```

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --tag="cms-migrations"
php artisan migrate
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --tag="cms-config"
```

### 4. Add to Your Blade Templates

```blade
<!-- Make any content editable -->
<h1 data-cms-text="hero.title">Welcome to Laravel CMS</h1>
<p data-cms-text="hero.description">Edit this content directly on your page!</p>

<!-- Include the CMS editor -->
@cms
```

### 5. Access the Editor

Visit your page and append `?cms=1` to enable editing mode:
```
http://your-site.com/page?cms=1
```

That's it! Click on any marked content to start editing.

## 🎮 Interactive Demo

Experience Laravel CMS in action:

🔗 **[Live Demo](https://cms.webook.dev)** - Try the editor with sample content
🔗 **[Admin Dashboard](https://cms.webook.dev/cms/admin)** - Explore the admin interface
🔗 **[API Playground](https://cms.webook.dev/api/docs)** - Test the REST API endpoints

**Demo Credentials:**
- **Admin:** `admin@webook.dev` / `password`
- **Editor:** `editor@webook.dev` / `password`
- **Translator:** `translator@webook.dev` / `password`

## 📖 Documentation

### 📚 **Getting Started**
- [📦 Installation Guide](docs/installation.md) - System requirements and installation
- [⚙️ Configuration](docs/configuration.md) - All configuration options
- [🎯 Basic Usage](docs/usage.md) - Your first editable content

### 🔧 **Advanced Topics**
- [🚀 API Reference](docs/api.md) - Complete REST API documentation
- [🔌 Extending Laravel CMS](docs/extending.md) - Plugins and customization
- [🔐 Security Guide](docs/security.md) - Security best practices
- [🚀 Deployment](docs/deployment.md) - Production deployment guide

### 🆘 **Support**
- [🐛 Troubleshooting](docs/troubleshooting.md) - Common issues and solutions
- [❓ FAQ](docs/faq.md) - Frequently asked questions
- [📋 Changelog](CHANGELOG.md) - Version history and updates

### 💡 **Examples**
- [Basic Usage Examples](examples/basic-usage.php)
- [Multi-language Implementation](examples/multi-language.php)
- [Custom Permissions Setup](examples/custom-permissions.php)
- [API Integration Examples](examples/api-integration.php)

## 🛠️ Installation

### System Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 9.0 or higher
- **Extensions**: fileinfo, gd, zip
- **Storage**: 100MB+ available disk space
- **Memory**: 128MB+ PHP memory limit

### via Composer

```bash
composer require webook/laravel-cms
```

### Manual Installation

1. Download the latest release from [GitHub](https://github.com/weboook/laravel-cms/releases)
2. Extract to your `vendor/webook/laravel-cms` directory
3. Add the service provider to your `config/app.php`

For detailed installation instructions, see our [Installation Guide](docs/installation.md).

## 🎯 Basic Usage

### Making Content Editable

Add the `data-cms-text` attribute to any element:

```blade
<h1 data-cms-text="page.title">{{ __('page.title') }}</h1>
<p data-cms-text="page.description">{{ __('page.description') }}</p>
```

### Image Management

```blade
<img data-cms-image="hero.banner" src="{{ cms_image('hero.banner', '/default.jpg') }}" alt="Hero Banner">
```

### Link Management

```blade
<a data-cms-link="nav.about" href="{{ cms_link('nav.about', '/about') }}">About Us</a>
```

### Rich Content Areas

```blade
<div data-cms-rich="content.body">
    {!! cms_content('content.body', '<p>Default content here</p>') !!}
</div>
```

### Multi-language Support

```blade
<!-- Automatic language detection -->
<h1 data-cms-text="welcome.title">{{ __('welcome.title') }}</h1>

<!-- Specific language -->
<h1 data-cms-text="welcome.title" data-cms-lang="es">{{ __('welcome.title', [], 'es') }}</h1>
```

For comprehensive usage examples, visit our [Usage Guide](docs/usage.md).

## 🎨 Content Types

Laravel CMS supports various content types:

| Type | Attribute | Description | Example |
|------|-----------|-------------|---------|
| **Text** | `data-cms-text` | Simple text content | Headings, paragraphs |
| **Rich Text** | `data-cms-rich` | HTML content with formatting | Articles, descriptions |
| **Images** | `data-cms-image` | Image management | Banners, galleries |
| **Links** | `data-cms-link` | URL management | Navigation, buttons |
| **Components** | `data-cms-component` | Blade components | Cards, widgets |
| **JSON** | `data-cms-json` | Structured data | Settings, configurations |

## 🌐 Multi-language Support

Easily create multilingual websites:

```php
// Automatic translation detection
{{ cms_text('welcome.message') }}

// Force specific language
{{ cms_text('welcome.message', 'es') }}

// Translation with fallback
{{ cms_text('welcome.message', app()->getLocale(), 'Welcome!') }}
```

**Supported Languages**: 40+ languages including English, Spanish, French, German, Italian, Portuguese, Russian, Chinese, Japanese, Arabic, and more.

## 🔌 Extending Laravel CMS

### Creating Custom Content Types

```php
use Webook\LaravelCMS\ContentTypes\BaseContentType;

class CustomContentType extends BaseContentType
{
    public function render($value, $attributes = [])
    {
        // Custom rendering logic
        return view('custom.content', compact('value', 'attributes'));
    }
}
```

### Event Hooks

```php
use Webook\LaravelCMS\Events\ContentUpdated;

Event::listen(ContentUpdated::class, function ($event) {
    // Handle content update
    Mail::to('admin@site.com')->send(new ContentChangedNotification($event->content));
});
```

### Custom Plugins

```php
use Webook\LaravelCMS\Plugins\BasePlugin;

class SEOPlugin extends BasePlugin
{
    public function boot()
    {
        $this->addMetaTags();
        $this->generateSitemap();
    }
}
```

## 🚀 API Usage

Laravel CMS provides a complete REST API:

### Authentication

```bash
# Get API token
curl -X POST https://your-site.com/cms/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```

### Update Content

```bash
# Update text content
curl -X PUT https://your-site.com/cms/api/content/text \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"key": "page.title", "value": "New Title", "language": "en"}'
```

### Bulk Operations

```bash
# Bulk update translations
curl -X POST https://your-site.com/cms/api/translations/bulk \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"translations": [{"key": "nav.home", "en": "Home", "es": "Inicio"}]}'
```

For complete API documentation, see our [API Reference](docs/api.md).

## 🔐 Security

Laravel CMS prioritizes security:

- **XSS Prevention**: All content is sanitized using HTML Purifier
- **CSRF Protection**: All forms and API endpoints are CSRF protected
- **File Upload Security**: Malware scanning and file type validation
- **Permission System**: Granular role-based access control
- **Audit Logging**: Complete activity tracking

See our [Security Guide](docs/security.md) for best practices.

## 📊 Performance

Optimized for high performance:

- **Intelligent Caching**: Multi-layer caching strategy
- **Asset Optimization**: Automatic minification and compression
- **Lazy Loading**: Progressive content loading
- **CDN Integration**: Seamless CDN support
- **Database-free**: No database queries for content delivery

**Benchmarks**:
- Content rendering: < 50ms
- Editor loading: < 200ms
- Image optimization: < 2s
- Translation lookup: < 10ms

## 🏗️ Testing

Run the test suite:

```bash
# Install test dependencies
composer install --dev

# Run unit tests
php artisan test

# Run browser tests
php artisan dusk

# Generate coverage report
php artisan test --coverage
```

Laravel CMS includes:
- **Unit Tests**: 200+ tests covering core functionality
- **Feature Tests**: End-to-end testing of major features
- **Browser Tests**: Automated UI testing with Laravel Dusk
- **Performance Tests**: Load testing and benchmarking
- **Security Tests**: Vulnerability scanning and penetration testing

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/weboook/laravel-cms.git
cd laravel-cms

# Install dependencies
composer install
npm install

# Set up test environment
cp .env.example .env.testing
php artisan key:generate --env=testing

# Run tests
php artisan test
```

### Code of Conduct

This project adheres to the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md).

## 📝 License

Laravel CMS is open-sourced software licensed under the [MIT License](LICENSE.md).

## 🏆 Credits

Laravel CMS is developed and maintained by [Webook](https://webook.dev).

### Core Team
- **[Your Name](https://github.com/yourusername)** - Lead Developer
- **[Team Member](https://github.com/teammember)** - Frontend Developer

### Contributors
- See our [Contributors](https://github.com/weboook/laravel-cms/contributors) page

### Special Thanks
- [Laravel](https://laravel.com) - The amazing framework this package is built on
- [TinyMCE](https://www.tiny.cloud) - Rich text editor
- [HTML Purifier](http://htmlpurifier.org) - HTML sanitization
- [All our contributors](https://github.com/weboook/laravel-cms/contributors)

## 📞 Support

Need help? We're here for you:

- 📖 **[Documentation](docs/README.md)** - Comprehensive guides and API reference
- 🐛 **[GitHub Issues](https://github.com/weboook/laravel-cms/issues)** - Bug reports and feature requests
- 💬 **[Discord Community](https://discord.gg/webook)** - Chat with other users and get help
- 📧 **[Email Support](mailto:support@webook.dev)** - Direct support for pro users
- 🎓 **[Video Tutorials](https://youtube.com/@webook)** - Step-by-step video guides

### Professional Support

Need enterprise support, custom development, or consulting?

🏢 **[Contact our team](https://webook.dev/contact)** for:
- Custom feature development
- Enterprise deployment assistance
- Performance optimization
- Security audits
- Training and workshops

---

<div align="center">
  <p>
    <strong>Laravel CMS</strong> - Powerful content management for Laravel applications
  </p>
  <p>
    Made with ❤️ by <a href="https://webook.dev">Webook</a>
  </p>
  <p>
    <a href="https://github.com/weboook/laravel-cms">⭐ Star us on GitHub</a> •
    <a href="https://twitter.com/webookdev">🐦 Follow on Twitter</a> •
    <a href="https://linkedin.com/company/webook-dev">💼 LinkedIn</a>
  </p>
</div>