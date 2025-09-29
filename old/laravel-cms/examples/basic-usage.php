<?php

/**
 * Laravel CMS - Basic Usage Examples
 *
 * This file demonstrates basic usage of Laravel CMS functionality
 * including content management, editing, and helper functions.
 */

// =============================================================================
// BASIC CONTENT MANAGEMENT
// =============================================================================

/**
 * Example 1: Simple Text Content
 *
 * Basic text content that can be edited in-place
 */

// In your Blade template:
?>
<!-- Simple editable text -->
<h1 data-cms-text="page.title">{{ cms_text('page.title', 'Default Page Title') }}</h1>
<p data-cms-text="page.subtitle">{{ cms_text('page.subtitle', 'Default subtitle') }}</p>

<!-- Navigation with editable labels -->
<nav>
    <a href="/" data-cms-text="nav.home">{{ cms_text('nav.home', 'Home') }}</a>
    <a href="/about" data-cms-text="nav.about">{{ cms_text('nav.about', 'About') }}</a>
    <a href="/contact" data-cms-text="nav.contact">{{ cms_text('nav.contact', 'Contact') }}</a>
</nav>

<?php

/**
 * Example 2: Rich Text Content
 *
 * Content areas that support HTML formatting
 */
?>

<!-- Rich text content area -->
<div data-cms-rich="content.introduction" data-cms-toolbar="standard">
    {!! cms_content('content.introduction', '<p>Welcome to our website!</p>') !!}
</div>

<!-- Advanced rich text with custom toolbar -->
<article data-cms-rich="blog.post"
         data-cms-toolbar="advanced"
         data-cms-height="400">
    {!! cms_content('blog.post', '<h2>Blog Post Title</h2><p>Start writing your post...</p>') !!}
</article>

<?php

/**
 * Example 3: Image Management
 *
 * Editable images with automatic optimization
 */
?>

<!-- Simple editable image -->
<img data-cms-image="hero.banner"
     src="{{ cms_image('hero.banner', '/images/default-banner.jpg') }}"
     alt="{{ cms_text('hero.banner_alt', 'Hero Banner') }}"
     class="img-fluid">

<!-- Responsive image with multiple sizes -->
<img data-cms-image="gallery.featured"
     src="{{ cms_image('gallery.featured', '/images/placeholder.jpg', ['w' => 800, 'h' => 600]) }}"
     srcset="{{ cms_srcset('gallery.featured', [
         ['w' => 400, 'h' => 300],
         ['w' => 800, 'h' => 600],
         ['w' => 1200, 'h' => 900]
     ]) }}"
     sizes="(max-width: 768px) 400px, (max-width: 1024px) 800px, 1200px"
     alt="{{ cms_text('gallery.featured_alt') }}">

<!-- Background image -->
<section data-cms-image="section.background"
         style="background-image: url('{{ cms_image('section.background') }}');"
         class="hero-section">
    <div class="content">
        <h1 data-cms-text="hero.title">{{ cms_text('hero.title') }}</h1>
    </div>
</section>

<?php

/**
 * Example 4: Link Management
 *
 * Editable links with validation
 */
?>

<!-- Navigation links -->
<ul class="navbar-nav">
    <li><a href="{{ cms_link('nav.home', '/') }}" data-cms-link="nav.home">{{ cms_text('nav.home', 'Home') }}</a></li>
    <li><a href="{{ cms_link('nav.services', '/services') }}" data-cms-link="nav.services">{{ cms_text('nav.services', 'Services') }}</a></li>
    <li><a href="{{ cms_link('nav.contact', '/contact') }}" data-cms-link="nav.contact">{{ cms_text('nav.contact', 'Contact') }}</a></li>
</ul>

<!-- CTA Button -->
<a href="{{ cms_link('cta.primary', '#') }}"
   data-cms-link="cta.primary"
   data-cms-text="cta.primary_label"
   class="btn btn-primary">
    {{ cms_text('cta.primary_label', 'Get Started') }}
</a>

<!-- Social media links -->
<div class="social-links">
    <a href="{{ cms_link('social.twitter') }}" target="_blank" rel="noopener">
        <i class="fab fa-twitter"></i> {{ cms_text('social.twitter_label', 'Follow us') }}
    </a>
    <a href="{{ cms_link('social.facebook') }}" target="_blank" rel="noopener">
        <i class="fab fa-facebook"></i> {{ cms_text('social.facebook_label', 'Like us') }}
    </a>
</div>

<?php

// =============================================================================
// CONTENT ORGANIZATION
// =============================================================================

/**
 * Example 5: Organized Content Structure
 *
 * Best practices for organizing content keys
 */

// Homepage content
$homepageContent = [
    'hero.title' => cms_text('hero.title', 'Welcome to Our Site'),
    'hero.subtitle' => cms_text('hero.subtitle', 'Your success is our mission'),
    'hero.cta_button' => cms_text('hero.cta_button', 'Get Started'),
    'hero.background' => cms_image('hero.background'),
];

// Features section
$features = cms_group('features'); // Gets all keys starting with 'features.'
/*
Returns:
[
    'features.title' => 'Our Features',
    'features.subtitle' => 'What we offer',
    'features.feature1.title' => 'Fast Performance',
    'features.feature1.description' => 'Lightning fast loading...',
    'features.feature2.title' => 'Secure & Reliable',
    // ...
]
*/

// Navigation structure
$navigation = cms_group('nav');
/*
Returns:
[
    'nav.home' => 'Home',
    'nav.about' => 'About',
    'nav.services' => 'Services',
    'nav.contact' => 'Contact',
]
*/

/**
 * Example 6: Bulk Content Operations
 *
 * Working with multiple content items at once
 */

// Get multiple content items
$pageContent = cms_bulk([
    'title' => 'page.title',
    'subtitle' => 'page.subtitle',
    'content' => 'page.content',
    'banner' => 'page.banner',
    'cta_text' => 'page.cta_text',
    'cta_link' => 'page.cta_link',
]);

// Use in template
?>
<div class="page-header">
    <h1>{{ $pageContent['title'] }}</h1>
    <p class="lead">{{ $pageContent['subtitle'] }}</p>
</div>

<div class="page-content">
    {!! $pageContent['content'] !!}
</div>

<div class="page-cta">
    <a href="{{ $pageContent['cta_link'] }}" class="btn btn-primary">
        {{ $pageContent['cta_text'] }}
    </a>
</div>

<?php

/**
 * Example 7: Conditional Content
 *
 * Display different content based on conditions
 */

// Time-based content
$timeBasedGreeting = cms_text(
    'greeting.' . (now()->hour < 12 ? 'morning' : 'evening'),
    'Hello!'
);

// User role-based content
$roleBasedContent = auth()->check()
    ? cms_text('content.authenticated', 'Welcome back!')
    : cms_text('content.guest', 'Please sign in');

// Device-based content
$deviceType = request()->header('User-Agent');
$isMobile = str_contains($deviceType, 'Mobile');
$deviceContent = cms_text(
    $isMobile ? 'content.mobile' : 'content.desktop',
    'Welcome to our site'
);

/**
 * Example 8: Content with Default Values
 *
 * Providing fallbacks for empty content
 */

// Text with fallback
$title = cms_text('page.title', 'Default Page Title');

// Rich content with HTML fallback
$content = cms_content('page.content', '<p>Default content goes here.</p>');

// Image with fallback
$image = cms_image('hero.banner', '/images/default-hero.jpg');

// Link with fallback
$link = cms_link('nav.home', '/');

/**
 * Example 9: Content Validation and Sanitization
 *
 * Ensuring content safety
 */

// Get sanitized rich content (automatic)
$safeContent = cms_content('user.bio'); // Already sanitized

// Check if content exists
if (cms_has_content('page.optional_section')) {
    $optionalContent = cms_content('page.optional_section');
}

// Get content length for excerpts
$excerpt = cms_excerpt('blog.post', 150); // First 150 characters

/**
 * Example 10: Working with Content Metadata
 *
 * Accessing content information
 */

// Get content info
$contentInfo = cms_info('page.title');
/*
Returns:
[
    'key' => 'page.title',
    'value' => 'Page Title',
    'type' => 'text',
    'language' => 'en',
    'updated_at' => '2024-01-15 10:30:00',
    'updated_by' => 'user@example.com'
]
*/

// Check when content was last updated
$lastUpdated = cms_updated('page.title'); // Carbon instance

// Get content history
$history = cms_history('page.title', 5); // Last 5 changes

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Example 11: CMS Helper Functions
 *
 * Useful utility functions
 */

// Check if CMS is enabled
if (cms_enabled()) {
    // CMS functionality is available
}

// Check if user can edit content
if (cms_can_edit()) {
    // Show edit controls
}

// Check if in edit mode
if (cms_edit_mode()) {
    // Show additional editing features
}

// Get current language
$currentLang = cms_locale(); // 'en', 'es', 'fr', etc.

// Get available languages
$availableLanguages = cms_locales();
/*
Returns:
[
    'en' => ['name' => 'English', 'flag' => '🇺🇸'],
    'es' => ['name' => 'Spanish', 'flag' => '🇪🇸'],
    'fr' => ['name' => 'French', 'flag' => '🇫🇷'],
]
*/

// Generate language switcher URL
$spanishUrl = cms_locale_url('es');

// Get content statistics
$stats = cms_stats();
/*
Returns:
[
    'total_keys' => 150,
    'total_languages' => 3,
    'last_updated' => '2024-01-15 10:30:00',
    'cache_hit_rate' => 85.2
]
*/

/**
 * Example 12: Performance Optimization
 *
 * Optimizing content loading
 */

// Preload content for better performance
cms_preload(['page.title', 'page.subtitle', 'page.content']);

// Cache content for performance
$cachedContent = cache()->remember('homepage_content', 3600, function() {
    return cms_bulk(['hero.*', 'features.*', 'footer.*']);
});

// Lazy load images
?>
<img data-cms-image="gallery.photo1"
     src="{{ cms_image('gallery.photo1') }}"
     loading="lazy"
     alt="{{ cms_text('gallery.photo1_alt') }}">

<?php

/**
 * Example 13: Error Handling
 *
 * Graceful error handling for content
 */

try {
    $content = cms_content('page.content');
} catch (\Exception $e) {
    $content = '<p>Content temporarily unavailable.</p>';
    \Log::error('CMS content error: ' . $e->getMessage());
}

// Using null coalescing for safe content access
$title = cms_text('page.title') ?? 'Default Title';
$image = cms_image('hero.banner') ?? '/images/placeholder.jpg';

/**
 * Example 14: Integration with Laravel Features
 *
 * Using CMS with other Laravel functionality
 */

// Using with Laravel's localization
$localizedContent = __(cms_text('welcome.message', 'welcome.message'));

// Using with Laravel's validation
$validatedData = $request->validate([
    'title' => 'required|string|max:' . cms_config('limits.title_length', 255),
    'content' => 'required|string|max:' . cms_config('limits.content_length', 10000),
]);

// Using with Laravel's caching
$content = Cache::tags(['cms', 'homepage'])->remember('homepage.hero', 3600, function() {
    return [
        'title' => cms_text('hero.title'),
        'subtitle' => cms_text('hero.subtitle'),
        'image' => cms_image('hero.background'),
    ];
});

// Using with Laravel's events
Event::listen('cms.content.updated', function($event) {
    Cache::tags(['cms'])->flush();
    // Clear specific cache when content changes
});

/**
 * Example 15: Custom Content Components
 *
 * Creating reusable content components
 */

// Hero section component
function renderHeroSection($sectionKey = 'hero') {
    return view('components.hero', [
        'title' => cms_text("{$sectionKey}.title"),
        'subtitle' => cms_text("{$sectionKey}.subtitle"),
        'background' => cms_image("{$sectionKey}.background"),
        'cta_text' => cms_text("{$sectionKey}.cta_text"),
        'cta_link' => cms_link("{$sectionKey}.cta_link"),
    ]);
}

// Feature card component
function renderFeatureCard($featureKey) {
    return view('components.feature-card', [
        'title' => cms_text("{$featureKey}.title"),
        'description' => cms_content("{$featureKey}.description"),
        'icon' => cms_image("{$featureKey}.icon"),
        'link' => cms_link("{$featureKey}.link"),
    ]);
}

// Contact info component
function renderContactInfo() {
    return view('components.contact-info', [
        'phone' => cms_text('contact.phone'),
        'email' => cms_text('contact.email'),
        'address' => cms_content('contact.address'),
        'hours' => cms_content('contact.hours'),
        'map_link' => cms_link('contact.map'),
    ]);
}

/**
 * Example 16: Content Search and Filtering
 *
 * Finding and filtering content
 */

// Search content
$searchResults = cms_search('Laravel CMS');

// Filter content by type
$textContent = cms_filter('type', 'text');
$richContent = cms_filter('type', 'rich');
$images = cms_filter('type', 'image');

// Filter by language
$englishContent = cms_filter('language', 'en');
$spanishContent = cms_filter('language', 'es');

// Complex content queries
$blogContent = cms_query()
    ->where('key', 'like', 'blog.%')
    ->where('type', 'rich')
    ->where('language', 'en')
    ->orderBy('updated_at', 'desc')
    ->limit(10)
    ->get();

/**
 * Example 17: Content Export/Import
 *
 * Backing up and restoring content
 */

// Export content
$exportData = cms_export(['page.*', 'nav.*', 'footer.*']);
file_put_contents('content-backup.json', json_encode($exportData, JSON_PRETTY_PRINT));

// Import content
$importData = json_decode(file_get_contents('content-backup.json'), true);
cms_import($importData);

// Export specific language
$spanishContent = cms_export(['*'], 'es');

// Import with merge option
cms_import($importData, ['merge' => true, 'language' => 'en']);

echo "✅ Basic usage examples completed!\n";
echo "Check the documentation at docs/usage.md for more detailed information.\n";