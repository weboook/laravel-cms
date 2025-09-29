# Usage Guide

Learn how to use Laravel CMS to create editable content in your Laravel application.

## 🎯 Basic Concepts

### Content Types

Laravel CMS supports several content types, each designed for specific use cases:

| Type | Attribute | Use Case | Example |
|------|-----------|----------|---------|
| **Text** | `data-cms-text` | Simple text content | Headings, labels, short descriptions |
| **Rich Text** | `data-cms-rich` | Formatted HTML content | Articles, long descriptions, formatted text |
| **Images** | `data-cms-image` | Image management | Banners, photos, logos |
| **Links** | `data-cms-link` | URL management | Navigation links, buttons |
| **Components** | `data-cms-component` | Blade components | Cards, widgets, complex layouts |
| **JSON** | `data-cms-json` | Structured data | Settings, configurations, arrays |

### Edit Modes

1. **Inline Editing**: Edit content directly on the page
2. **Modal Editing**: Edit in a popup modal for complex content
3. **Sidebar Editing**: Edit in a collapsible sidebar panel

## ✏️ Making Content Editable

### 1. Text Content

The simplest way to make text editable:

```blade
<!-- Basic text editing -->
<h1 data-cms-text="page.title">{{ __('page.title') }}</h1>
<p data-cms-text="page.subtitle">{{ __('page.subtitle') }}</p>

<!-- With default value -->
<h2 data-cms-text="section.heading">Default Heading</h2>

<!-- With custom attributes -->
<span data-cms-text="nav.label"
      data-cms-placeholder="Enter navigation label"
      class="nav-link">Home</span>
```

### 2. Rich Text Content

For content that needs formatting:

```blade
<!-- Rich text area -->
<div data-cms-rich="content.body">
    {!! cms_content('content.body', '<p>Default content here</p>') !!}
</div>

<!-- With custom toolbar -->
<div data-cms-rich="content.description"
     data-cms-toolbar="basic"
     data-cms-height="200">
    {!! cms_content('content.description') !!}
</div>

<!-- Advanced rich text -->
<article data-cms-rich="blog.post"
         data-cms-toolbar="advanced"
         data-cms-plugins="table,link,image">
    {!! cms_content('blog.post') !!}
</article>
```

### 3. Image Management

Handle images with automatic optimization:

```blade
<!-- Basic image -->
<img data-cms-image="hero.banner"
     src="{{ cms_image('hero.banner', '/default-banner.jpg') }}"
     alt="Hero Banner">

<!-- With responsive sizes -->
<img data-cms-image="gallery.photo1"
     src="{{ cms_image('gallery.photo1', '/placeholder.jpg') }}"
     data-cms-sizes="300x200,600x400,1200x800"
     class="img-fluid"
     alt="Gallery Photo">

<!-- Background image -->
<div data-cms-image="section.background"
     style="background-image: url('{{ cms_image('section.background') }}');"
     class="hero-section">
    <h1>Content over background</h1>
</div>
```

### 4. Link Management

Smart link handling with validation:

```blade
<!-- Navigation link -->
<a data-cms-link="nav.about"
   href="{{ cms_link('nav.about', '/about') }}"
   class="nav-link">About Us</a>

<!-- Button with link -->
<a data-cms-link="cta.button"
   href="{{ cms_link('cta.button', '#') }}"
   class="btn btn-primary"
   data-cms-text="cta.label">Get Started</a>

<!-- External link with target -->
<a data-cms-link="footer.social.twitter"
   href="{{ cms_link('footer.social.twitter') }}"
   target="_blank"
   rel="noopener">Follow us on Twitter</a>
```

### 5. Component Editing

Edit entire Blade components:

```blade
<!-- Editable component -->
<div data-cms-component="widgets.featured-card"
     data-cms-props='{"title": "Default Title", "image": "default.jpg"}'>
    @include('components.featured-card', [
        'title' => cms_prop('widgets.featured-card.title', 'Default Title'),
        'image' => cms_prop('widgets.featured-card.image', 'default.jpg'),
        'description' => cms_prop('widgets.featured-card.description', 'Default description')
    ])
</div>

<!-- Complex component with JSON data -->
<div data-cms-component="sections.testimonials"
     data-cms-schema='{"testimonials": {"type": "array", "items": {"name": "string", "quote": "string", "image": "image"}}}'>
    @foreach(cms_component('sections.testimonials.testimonials', []) as $testimonial)
        <blockquote>
            <p>{{ $testimonial['quote'] }}</p>
            <cite>{{ $testimonial['name'] }}</cite>
        </blockquote>
    @endforeach
</div>
```

## 🌐 Multi-language Support

### Language Detection

Laravel CMS automatically detects the current language:

```blade
<!-- Automatic language detection -->
<h1 data-cms-text="welcome.title">{{ __('welcome.title') }}</h1>

<!-- Force specific language -->
<h1 data-cms-text="welcome.title"
    data-cms-lang="es">{{ __('welcome.title', [], 'es') }}</h1>

<!-- Multiple languages on same page -->
<div class="language-versions">
    <div data-cms-text="content.heading" data-cms-lang="en">
        {{ __('content.heading', [], 'en') }}
    </div>
    <div data-cms-text="content.heading" data-cms-lang="es">
        {{ __('content.heading', [], 'es') }}
    </div>
</div>
```

### Language Switching

Create language switchers:

```blade
<!-- Language switcher -->
<div class="language-switcher">
    @foreach(cms_locales() as $locale => $name)
        <a href="{{ cms_locale_url($locale) }}"
           class="lang-link {{ app()->getLocale() === $locale ? 'active' : '' }}">
            {{ $name }}
        </a>
    @endforeach
</div>

<!-- Flag-based switcher -->
<div class="flag-switcher">
    @foreach(cms_locales() as $locale => $config)
        <a href="{{ cms_locale_url($locale) }}"
           title="{{ $config['name'] }}">
            <span class="flag">{{ $config['flag'] }}</span>
        </a>
    @endforeach
</div>
```

### Translation Helpers

Use helper functions for translations:

```php
// Get translated content with fallback
$title = cms_text('page.title', app()->getLocale(), 'Default Title');

// Get all translations for a key
$translations = cms_translations('page.title');

// Check if translation exists
if (cms_has_translation('page.title', 'es')) {
    echo cms_text('page.title', 'es');
}

// Get translation with parameters
$message = cms_text('welcome.message', 'en', 'Hello :name', ['name' => $user->name]);
```

## 🎛️ Content Editor Interface

### Enabling Edit Mode

There are several ways to enable edit mode:

#### 1. URL Parameter (Development)
```
http://your-site.com/page?cms=1
```

#### 2. Button Toggle
```blade
@auth
    @if(auth()->user()->can('edit-cms'))
        <button onclick="CMS.toggleEditMode()" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-edit"></i> Edit Page
        </button>
    @endif
@endauth
```

#### 3. Keyboard Shortcut
```javascript
// Press Ctrl+E to toggle edit mode
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        CMS.toggleEditMode();
    }
});
```

#### 4. Automatic for Admins
```blade
@if(auth()->check() && auth()->user()->hasRole('admin'))
    <script>
        // Auto-enable edit mode for admins
        document.addEventListener('DOMContentLoaded', function() {
            CMS.enableEditMode();
        });
    </script>
@endif
```

### Editor Toolbar Options

Customize the rich text editor toolbar:

```blade
<!-- Basic toolbar: bold, italic, lists -->
<div data-cms-rich="content.basic" data-cms-toolbar="basic">
    {!! cms_content('content.basic') !!}
</div>

<!-- Standard toolbar: formatting, lists, links -->
<div data-cms-rich="content.standard" data-cms-toolbar="standard">
    {!! cms_content('content.standard') !!}
</div>

<!-- Advanced toolbar: all formatting options -->
<div data-cms-rich="content.advanced" data-cms-toolbar="advanced">
    {!! cms_content('content.advanced') !!}
</div>

<!-- Custom toolbar -->
<div data-cms-rich="content.custom"
     data-cms-toolbar="bold italic | bullist numlist | link image">
    {!! cms_content('content.custom') !!}
</div>
```

### Custom Editor Configuration

Configure editor behavior:

```blade
<!-- Custom height -->
<div data-cms-rich="content.tall"
     data-cms-height="400"
     data-cms-resize="both">
    {!! cms_content('content.tall') !!}
</div>

<!-- Disable specific features -->
<div data-cms-rich="content.simple"
     data-cms-images="false"
     data-cms-tables="false"
     data-cms-links="false">
    {!! cms_content('content.simple') !!}
</div>

<!-- Custom CSS classes -->
<div data-cms-rich="content.styled"
     data-cms-body-class="custom-editor"
     data-cms-content-css="/css/editor-content.css">
    {!! cms_content('content.styled') !!}
</div>
```

## 📁 File Management

### File Uploads

Handle file uploads in the editor:

```blade
<!-- File upload area -->
<div data-cms-file="documents.manual"
     data-cms-accept=".pdf,.doc,.docx"
     data-cms-max-size="5120">
    <a href="{{ cms_file('documents.manual') }}">
        {{ cms_filename('documents.manual', 'Download Manual') }}
    </a>
</div>

<!-- Multiple file uploads -->
<div data-cms-files="gallery.photos"
     data-cms-accept="image/*"
     data-cms-max-files="10">
    @foreach(cms_files('gallery.photos') as $photo)
        <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" class="gallery-thumb">
    @endforeach
</div>
```

### Image Processing

Automatic image optimization and resizing:

```blade
<!-- Responsive images -->
<img data-cms-image="hero.banner"
     src="{{ cms_image('hero.banner', '/default.jpg', ['w' => 1200, 'h' => 600]) }}"
     srcset="{{ cms_srcset('hero.banner', [
         ['w' => 400, 'h' => 200],
         ['w' => 800, 'h' => 400],
         ['w' => 1200, 'h' => 600]
     ]) }}"
     sizes="(max-width: 768px) 400px, (max-width: 1024px) 800px, 1200px"
     alt="Hero Banner">

<!-- Image with effects -->
<img data-cms-image="team.photo"
     src="{{ cms_image('team.photo', '/placeholder.jpg', [
         'w' => 300,
         'h' => 300,
         'fit' => 'crop',
         'quality' => 85,
         'format' => 'webp'
     ]) }}"
     alt="Team Photo">
```

## 🔍 Content Querying

### Retrieving Content

Use helper functions to retrieve content:

```php
// Basic content retrieval
$title = cms_text('page.title');
$content = cms_content('page.body');
$image = cms_image('hero.banner');

// With fallbacks
$title = cms_text('page.title', null, 'Default Title');
$content = cms_content('page.body', '<p>Default content</p>');

// Multiple keys at once
$pageData = cms_bulk([
    'title' => 'page.title',
    'subtitle' => 'page.subtitle',
    'content' => 'page.body',
    'image' => 'hero.banner'
]);

// All content for a prefix
$navigation = cms_group('nav');
// Returns: ['nav.home' => 'Home', 'nav.about' => 'About', ...]
```

### Filtering and Searching

Search through content:

```php
// Search content
$results = cms_search('Laravel CMS');

// Filter by content type
$images = cms_filter('type', 'image');
$richContent = cms_filter('type', 'rich');

// Filter by language
$spanishContent = cms_filter('language', 'es');

// Complex filtering
$filtered = cms_query()
    ->where('type', 'text')
    ->where('language', 'en')
    ->where('key', 'like', 'nav.%')
    ->get();
```

## 🎨 Customizing the Interface

### Custom Styles

Customize the editor appearance:

```css
/* Custom editor styles */
.cms-editor {
    border: 2px solid #007bff;
    border-radius: 8px;
}

.cms-toolbar {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.cms-content {
    min-height: 200px;
    padding: 15px;
}

/* Custom content styles */
.cms-editable:hover {
    outline: 2px dashed #007bff;
    outline-offset: 2px;
}

.cms-editing {
    background: rgba(0, 123, 255, 0.1);
}
```

### Custom JavaScript

Extend editor functionality:

```javascript
// Custom editor initialization
document.addEventListener('cms:ready', function() {
    // Editor is ready
    console.log('CMS Editor loaded');
});

// Custom content handlers
CMS.on('content:save', function(event) {
    console.log('Content saved:', event.key, event.value);

    // Custom save logic
    showNotification('Content saved successfully!');
});

CMS.on('content:error', function(event) {
    console.error('Save failed:', event.error);
    showNotification('Failed to save content', 'error');
});

// Custom toolbar buttons
CMS.addToolbarButton('custom-action', {
    text: 'Custom Action',
    icon: 'fas fa-star',
    onClick: function(editor) {
        // Custom action
        editor.insertContent('<span class="highlight">Highlighted text</span>');
    }
});
```

## 📊 Analytics and Tracking

### Usage Analytics

Track content usage and editing:

```php
// Get content statistics
$stats = cms_stats();
// Returns: ['total_keys' => 150, 'last_edited' => '2024-01-15', ...]

// Popular content
$popular = cms_popular_content(10);

// Recent changes
$recent = cms_recent_changes(20);

// User activity
$activity = cms_user_activity(auth()->id());
```

### Performance Monitoring

Monitor CMS performance:

```javascript
// Track editing time
CMS.on('edit:start', function(event) {
    window.editStartTime = Date.now();
});

CMS.on('edit:save', function(event) {
    const editTime = Date.now() - window.editStartTime;
    analytics.track('cms_edit_duration', {
        key: event.key,
        duration: editTime
    });
});

// Track user engagement
CMS.on('content:view', function(event) {
    analytics.track('cms_content_view', {
        key: event.key,
        language: event.language
    });
});
```

## 🔧 Advanced Usage

### Conditional Content

Show different content based on conditions:

```blade
<!-- User role-based content -->
<div data-cms-text="content.{{ auth()->user()->role }}.welcome">
    {{ cms_text('content.' . auth()->user()->role . '.welcome', 'Welcome!') }}
</div>

<!-- Time-based content -->
@php
    $timeKey = 'content.' . (now()->hour < 12 ? 'morning' : 'evening') . '.greeting';
@endphp
<h1 data-cms-text="{{ $timeKey }}">
    {{ cms_text($timeKey, 'Hello!') }}
</h1>

<!-- A/B testing -->
@php
    $variant = session('ab_variant', 'a');
@endphp
<div data-cms-text="hero.title.{{ $variant }}">
    {{ cms_text("hero.title.{$variant}", 'Default Title') }}
</div>
```

### Dynamic Content Loading

Load content dynamically with AJAX:

```javascript
// Load content via AJAX
async function loadContent(key, language = null) {
    const response = await fetch(`/cms/api/content/${key}?lang=${language}`);
    const data = await response.json();
    return data.content;
}

// Update content dynamically
async function updatePageContent() {
    const title = await loadContent('page.title');
    document.querySelector('[data-cms-text="page.title"]').textContent = title;
}

// Auto-refresh content
setInterval(updatePageContent, 30000); // Every 30 seconds
```

### Bulk Content Operations

Handle multiple content items:

```php
// Bulk update
cms_bulk_update([
    'nav.home' => 'Home',
    'nav.about' => 'About Us',
    'nav.contact' => 'Contact',
]);

// Bulk translation
cms_bulk_translate([
    'page.title' => ['en' => 'Welcome', 'es' => 'Bienvenido', 'fr' => 'Bienvenue'],
    'page.subtitle' => ['en' => 'Get started', 'es' => 'Empezar', 'fr' => 'Commencer'],
]);

// Export content
$export = cms_export(['page.*', 'nav.*']);
file_put_contents('content-backup.json', json_encode($export));

// Import content
$import = json_decode(file_get_contents('content-backup.json'), true);
cms_import($import);
```

## 🎯 Best Practices

### Content Organization

1. **Use Consistent Naming**: `section.subsection.item`
2. **Group Related Content**: Use prefixes like `nav.*`, `footer.*`
3. **Descriptive Keys**: `hero.main_title` instead of `hero.title1`

### Performance Optimization

1. **Cache Frequently Used Content**:
   ```php
   $cachedContent = cache()->remember('cms.homepage', 3600, function() {
       return cms_bulk(['hero.*', 'features.*']);
   });
   ```

2. **Lazy Load Images**:
   ```blade
   <img data-cms-image="gallery.photo"
        loading="lazy"
        src="{{ cms_image('gallery.photo') }}">
   ```

3. **Minimize Editor Instances**: Don't make every text element editable

### Security Considerations

1. **Sanitize Rich Content**: Always use `{!! !!}` for rich content
2. **Validate Uploads**: Set file type and size restrictions
3. **User Permissions**: Check permissions before enabling edit mode

### SEO Optimization

1. **Meta Tags**:
   ```blade
   <title>{{ cms_text('meta.title', __('meta.title')) }}</title>
   <meta name="description" content="{{ cms_text('meta.description', __('meta.description')) }}">
   ```

2. **Structured Data**:
   ```blade
   <script type="application/ld+json">
   {
       "@context": "https://schema.org",
       "@type": "Article",
       "headline": "{{ cms_text('article.title') }}",
       "description": "{{ cms_text('article.excerpt') }}"
   }
   </script>
   ```

## 📞 Next Steps

Now that you understand the basics:

1. **Explore Advanced Features**: [Configuration Guide](configuration.md)
2. **Learn the API**: [API Documentation](api.md)
3. **Customize Further**: [Extension Guide](extending.md)
4. **Deploy to Production**: [Deployment Guide](deployment.md)

---

**Need help?** Check our [Troubleshooting Guide](troubleshooting.md) or join the [Discord community](https://discord.gg/laravel-cms).