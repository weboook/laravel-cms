<?php

/**
 * Laravel CMS - Multi-Language Implementation Examples
 *
 * This file demonstrates how to implement multi-language functionality
 * with Laravel CMS including translation management, language switching,
 * and localization best practices.
 */

// =============================================================================
// BASIC MULTI-LANGUAGE SETUP
// =============================================================================

/**
 * Example 1: Basic Translation Setup
 *
 * Setting up content for multiple languages
 */

// Automatic language detection
?>
<h1 data-cms-text="welcome.title">{{ cms_text('welcome.title', 'Welcome') }}</h1>
<p data-cms-text="welcome.message">{{ cms_text('welcome.message', 'Welcome to our website') }}</p>

<?php

// Language-specific content
?>
<!-- English content -->
<div class="lang-en" data-cms-text="content.intro" data-cms-lang="en">
    {{ cms_text('content.intro', 'Welcome to our website', 'en') }}
</div>

<!-- Spanish content -->
<div class="lang-es" data-cms-text="content.intro" data-cms-lang="es">
    {{ cms_text('content.intro', 'Bienvenido a nuestro sitio web', 'es') }}
</div>

<!-- French content -->
<div class="lang-fr" data-cms-text="content.intro" data-cms-lang="fr">
    {{ cms_text('content.intro', 'Bienvenue sur notre site web', 'fr') }}
</div>

<?php

/**
 * Example 2: Language Switcher
 *
 * Creating user-friendly language switching
 */

// Get available languages
$availableLanguages = cms_locales();
$currentLanguage = app()->getLocale();

?>
<!-- Dropdown language switcher -->
<div class="language-switcher dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
        {{ $availableLanguages[$currentLanguage]['flag'] ?? '🌐' }}
        {{ $availableLanguages[$currentLanguage]['name'] ?? 'Language' }}
    </button>
    <ul class="dropdown-menu">
        @foreach($availableLanguages as $locale => $config)
            @if($config['enabled'])
                <li>
                    <a class="dropdown-item {{ $locale === $currentLanguage ? 'active' : '' }}"
                       href="{{ cms_locale_url($locale) }}">
                        {{ $config['flag'] }} {{ $config['name'] }}
                        @if($config['native_name'] !== $config['name'])
                            <small class="text-muted">({{ $config['native_name'] }})</small>
                        @endif
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</div>

<!-- Flag-based switcher -->
<div class="flag-switcher">
    @foreach($availableLanguages as $locale => $config)
        @if($config['enabled'])
            <a href="{{ cms_locale_url($locale) }}"
               class="flag-link {{ $locale === $currentLanguage ? 'active' : '' }}"
               title="{{ $config['name'] }}">
                <span class="flag">{{ $config['flag'] }}</span>
                <span class="sr-only">{{ $config['name'] }}</span>
            </a>
        @endif
    @endforeach
</div>

<!-- Text-based switcher -->
<div class="text-switcher">
    @foreach($availableLanguages as $locale => $config)
        @if($config['enabled'])
            <a href="{{ cms_locale_url($locale) }}"
               class="lang-link {{ $locale === $currentLanguage ? 'active' : '' }}">
                {{ $config['native_name'] }}
            </a>
            @if(!$loop->last) | @endif
        @endif
    @endforeach
</div>

<?php

// =============================================================================
// ADVANCED TRANSLATION MANAGEMENT
// =============================================================================

/**
 * Example 3: Managing Translation Keys
 *
 * Organizing translations with proper key structure
 */

// Hierarchical translation keys
$translationKeys = [
    // Navigation
    'nav.home' => [
        'en' => 'Home',
        'es' => 'Inicio',
        'fr' => 'Accueil',
        'de' => 'Startseite'
    ],
    'nav.about' => [
        'en' => 'About Us',
        'es' => 'Acerca de Nosotros',
        'fr' => 'À Propos',
        'de' => 'Über Uns'
    ],
    'nav.services' => [
        'en' => 'Services',
        'es' => 'Servicios',
        'fr' => 'Services',
        'de' => 'Dienstleistungen'
    ],

    // Page content
    'page.title' => [
        'en' => 'Welcome to Our Company',
        'es' => 'Bienvenido a Nuestra Empresa',
        'fr' => 'Bienvenue dans Notre Entreprise',
        'de' => 'Willkommen in Unserem Unternehmen'
    ],
    'page.subtitle' => [
        'en' => 'Leading solutions for your business',
        'es' => 'Soluciones líderes para su negocio',
        'fr' => 'Solutions de pointe pour votre entreprise',
        'de' => 'Führende Lösungen für Ihr Unternehmen'
    ],

    // Call-to-action buttons
    'cta.get_started' => [
        'en' => 'Get Started',
        'es' => 'Comenzar',
        'fr' => 'Commencer',
        'de' => 'Loslegen'
    ],
    'cta.learn_more' => [
        'en' => 'Learn More',
        'es' => 'Saber Más',
        'fr' => 'En Savoir Plus',
        'de' => 'Mehr Erfahren'
    ],

    // Contact information
    'contact.title' => [
        'en' => 'Contact Us',
        'es' => 'Contáctanos',
        'fr' => 'Contactez-nous',
        'de' => 'Kontaktieren Sie Uns'
    ],
    'contact.address' => [
        'en' => '123 Business Street, City, State 12345',
        'es' => 'Calle de Negocios 123, Ciudad, Estado 12345',
        'fr' => '123 Rue des Affaires, Ville, État 12345',
        'de' => 'Geschäftsstraße 123, Stadt, Staat 12345'
    ],

    // Forms
    'form.name' => [
        'en' => 'Full Name',
        'es' => 'Nombre Completo',
        'fr' => 'Nom Complet',
        'de' => 'Vollständiger Name'
    ],
    'form.email' => [
        'en' => 'Email Address',
        'es' => 'Dirección de Correo',
        'fr' => 'Adresse E-mail',
        'de' => 'E-Mail-Adresse'
    ],
    'form.submit' => [
        'en' => 'Send Message',
        'es' => 'Enviar Mensaje',
        'fr' => 'Envoyer le Message',
        'de' => 'Nachricht Senden'
    ],
];

/**
 * Example 4: Bulk Translation Operations
 *
 * Working with multiple translations efficiently
 */

// Bulk update translations
cms_bulk_translate([
    'nav.home' => [
        'en' => 'Home',
        'es' => 'Inicio',
        'fr' => 'Accueil'
    ],
    'nav.about' => [
        'en' => 'About',
        'es' => 'Acerca de',
        'fr' => 'À propos'
    ],
    'nav.contact' => [
        'en' => 'Contact',
        'es' => 'Contacto',
        'fr' => 'Contact'
    ]
]);

// Get all translations for a specific prefix
$navigationTranslations = cms_translations('nav.*');

// Get translations for specific language
$spanishTranslations = cms_translations('*', 'es');

// Export translations for translation services
$exportData = cms_export_translations(['page.*', 'nav.*'], 'en');

/**
 * Example 5: Translation Fallbacks
 *
 * Handling missing translations gracefully
 */

// Translation with fallback chain
function getTranslationWithFallback($key, $locale = null) {
    $locale = $locale ?? app()->getLocale();
    $fallbackChain = config('cms.localization.fallback_chain.' . $locale, ['en']);

    // Try current locale first
    $translation = cms_text($key, null, $locale);
    if ($translation) {
        return $translation;
    }

    // Try fallback languages
    foreach ($fallbackChain as $fallbackLocale) {
        $translation = cms_text($key, null, $fallbackLocale);
        if ($translation) {
            return $translation;
        }
    }

    // Return key as last resort
    return $key;
}

// Usage example
$title = getTranslationWithFallback('page.title');

/**
 * Example 6: Auto-Translation Integration
 *
 * Using translation services for automatic translation
 */

// Auto-translate content using Google Translate
function autoTranslateContent($key, $sourceLanguage, $targetLanguages) {
    $sourceContent = cms_text($key, null, $sourceLanguage);

    if (!$sourceContent) {
        return false;
    }

    $translations = [];

    foreach ($targetLanguages as $targetLang) {
        try {
            // Use Google Translate API
            $translated = app('cms.translator')->translate(
                $sourceContent,
                $sourceLanguage,
                $targetLang
            );

            if ($translated) {
                cms_set_text($key, $translated, $targetLang);
                $translations[$targetLang] = $translated;
            }
        } catch (\Exception $e) {
            \Log::error("Translation failed for {$key} to {$targetLang}: " . $e->getMessage());
        }
    }

    return $translations;
}

// Auto-translate a page
$translations = autoTranslateContent('page.title', 'en', ['es', 'fr', 'de']);

/**
 * Example 7: RTL Language Support
 *
 * Supporting right-to-left languages
 */

// Check if current language is RTL
function isRTL($locale = null) {
    $locale = $locale ?? app()->getLocale();
    $localeConfig = cms_locale_config($locale);
    return $localeConfig['rtl'] ?? false;
}

?>
<!-- RTL-aware layout -->
<html lang="{{ app()->getLocale() }}" dir="{{ isRTL() ? 'rtl' : 'ltr' }}">
<head>
    <!-- Language-specific CSS -->
    @if(isRTL())
        <link href="{{ asset('css/rtl.css') }}" rel="stylesheet">
    @endif
</head>
<body class="{{ isRTL() ? 'rtl' : 'ltr' }}">
    <!-- Content with RTL support -->
    <div class="container">
        <h1 data-cms-text="page.title">{{ cms_text('page.title') }}</h1>
        <p data-cms-text="page.content" class="text-{{ isRTL() ? 'right' : 'left' }}">
            {{ cms_text('page.content') }}
        </p>
    </div>
</body>
</html>

<?php

// =============================================================================
// LANGUAGE-SPECIFIC CONTENT MANAGEMENT
// =============================================================================

/**
 * Example 8: Language-Specific Images and Media
 *
 * Managing media content for different languages
 */

// Language-specific images
?>
<img data-cms-image="hero.banner.{{ app()->getLocale() }}"
     src="{{ cms_image('hero.banner.' . app()->getLocale(), cms_image('hero.banner.en')) }}"
     alt="{{ cms_text('hero.banner_alt') }}">

<!-- Language-specific videos -->
<video data-cms-video="intro.video.{{ app()->getLocale() }}"
       src="{{ cms_video('intro.video.' . app()->getLocale()) }}"
       poster="{{ cms_image('intro.poster.' . app()->getLocale()) }}">
    {{ cms_text('video.not_supported', 'Your browser does not support the video tag.') }}
</video>

<!-- Language-specific documents -->
<a href="{{ cms_file('brochure.' . app()->getLocale()) }}"
   data-cms-text="download.brochure"
   class="btn btn-primary">
    {{ cms_text('download.brochure', 'Download Brochure') }}
</a>

<?php

/**
 * Example 9: Dynamic Language Detection
 *
 * Automatically detecting user's preferred language
 */

class LanguageDetector
{
    public static function detectUserLanguage()
    {
        $supportedLocales = array_keys(cms_locales());

        // 1. Check URL parameter
        if (request()->has('lang') && in_array(request('lang'), $supportedLocales)) {
            return request('lang');
        }

        // 2. Check session
        if (session()->has('locale') && in_array(session('locale'), $supportedLocales)) {
            return session('locale');
        }

        // 3. Check cookie
        if (request()->cookie('cms_locale') && in_array(request()->cookie('cms_locale'), $supportedLocales)) {
            return request()->cookie('cms_locale');
        }

        // 4. Check browser accept-language header
        $acceptLanguage = request()->header('Accept-Language');
        if ($acceptLanguage) {
            $languages = self::parseAcceptLanguage($acceptLanguage);
            foreach ($languages as $lang => $priority) {
                $locale = substr($lang, 0, 2); // Extract language code
                if (in_array($locale, $supportedLocales)) {
                    return $locale;
                }
            }
        }

        // 5. Fall back to default
        return config('app.locale', 'en');
    }

    protected static function parseAcceptLanguage($acceptLanguage)
    {
        $languages = [];
        $parts = explode(',', $acceptLanguage);

        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, ';q=') !== false) {
                list($lang, $priority) = explode(';q=', $part);
                $languages[trim($lang)] = (float) $priority;
            } else {
                $languages[trim($part)] = 1.0;
            }
        }

        arsort($languages);
        return $languages;
    }
}

// Middleware for automatic language detection
class DetectLanguageMiddleware
{
    public function handle($request, \Closure $next)
    {
        $detectedLanguage = LanguageDetector::detectUserLanguage();

        app()->setLocale($detectedLanguage);
        session(['locale' => $detectedLanguage]);

        return $next($request);
    }
}

/**
 * Example 10: Translation Import/Export
 *
 * Managing translations with external tools
 */

// Export translations to CSV for translation services
function exportTranslationsToCSV($keys = ['*'], $languages = null)
{
    $languages = $languages ?? array_keys(cms_locales());
    $translations = [];

    foreach ($keys as $keyPattern) {
        $keyData = cms_translations($keyPattern);
        foreach ($keyData as $key => $values) {
            $row = ['key' => $key];
            foreach ($languages as $lang) {
                $row[$lang] = $values[$lang] ?? '';
            }
            $translations[] = $row;
        }
    }

    $csvContent = '';
    if (!empty($translations)) {
        // Header
        $csvContent .= implode(',', array_keys($translations[0])) . "\n";

        // Rows
        foreach ($translations as $row) {
            $csvContent .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }
    }

    return $csvContent;
}

// Import translations from CSV
function importTranslationsFromCSV($csvContent, $updateExisting = false)
{
    $lines = str_getcsv($csvContent, "\n");
    $header = str_getcsv(array_shift($lines));

    $keyIndex = array_search('key', $header);
    if ($keyIndex === false) {
        throw new \Exception('CSV must have a "key" column');
    }

    $languageColumns = array_filter($header, function($col) {
        return $col !== 'key' && strlen($col) === 2; // Language codes
    });

    $imported = 0;
    $updated = 0;

    foreach ($lines as $line) {
        $row = str_getcsv($line);
        $key = $row[$keyIndex];

        if (empty($key)) {
            continue;
        }

        foreach ($languageColumns as $index => $language) {
            $value = $row[$index] ?? '';

            if (!empty($value)) {
                $exists = cms_has_translation($key, $language);

                if (!$exists) {
                    cms_set_text($key, $value, $language);
                    $imported++;
                } elseif ($updateExisting) {
                    cms_set_text($key, $value, $language);
                    $updated++;
                }
            }
        }
    }

    return compact('imported', 'updated');
}

/**
 * Example 11: SEO-Friendly Multi-Language URLs
 *
 * Creating language-aware URLs for better SEO
 */

// Language-aware routing
class MultiLanguageRouter
{
    public static function addLanguageRoutes($callback)
    {
        $supportedLocales = array_keys(cms_locales());

        foreach ($supportedLocales as $locale) {
            Route::group([
                'prefix' => $locale === config('app.locale') ? '' : $locale,
                'middleware' => ['set.locale:' . $locale]
            ], $callback);
        }
    }

    public static function generateLocalizedUrl($routeName, $locale = null, $parameters = [])
    {
        $locale = $locale ?? app()->getLocale();
        $defaultLocale = config('app.locale');

        if ($locale === $defaultLocale) {
            return route($routeName, $parameters);
        } else {
            return route($locale . '.' . $routeName, $parameters);
        }
    }
}

// Usage in routes/web.php
MultiLanguageRouter::addLanguageRoutes(function() {
    Route::get('/', 'HomeController@index')->name('home');
    Route::get('/about', 'AboutController@index')->name('about');
    Route::get('/contact', 'ContactController@index')->name('contact');
});

/**
 * Example 12: Translation Performance Optimization
 *
 * Optimizing translation loading and caching
 */

// Preload translations for better performance
class TranslationPreloader
{
    public static function preloadTranslations($keyPatterns, $languages = null)
    {
        $languages = $languages ?? [app()->getLocale()];
        $translations = [];

        foreach ($keyPatterns as $pattern) {
            foreach ($languages as $language) {
                $translations = array_merge(
                    $translations,
                    cms_translations($pattern, $language)
                );
            }
        }

        // Store in memory for the request
        app()->instance('cms.preloaded_translations', $translations);

        return $translations;
    }
}

// Preload common translations
TranslationPreloader::preloadTranslations([
    'nav.*',
    'footer.*',
    'common.*'
], [app()->getLocale()]);

// Cache translations by language
function getCachedTranslations($language, $keyPattern = '*')
{
    return cache()->remember(
        "cms_translations_{$language}_{$keyPattern}",
        3600, // 1 hour
        function() use ($language, $keyPattern) {
            return cms_translations($keyPattern, $language);
        }
    );
}

/**
 * Example 13: Translation Validation
 *
 * Ensuring translation quality and completeness
 */

class TranslationValidator
{
    public static function validateTranslations($languages = null)
    {
        $languages = $languages ?? array_keys(cms_locales());
        $baseLanguage = config('app.locale');
        $baseTranslations = cms_translations('*', $baseLanguage);

        $report = [
            'missing' => [],
            'empty' => [],
            'outdated' => [],
            'stats' => []
        ];

        foreach ($languages as $language) {
            if ($language === $baseLanguage) continue;

            $langTranslations = cms_translations('*', $language);
            $missing = array_diff_key($baseTranslations, $langTranslations);
            $empty = array_filter($langTranslations, function($value) {
                return empty(trim($value));
            });

            $report['missing'][$language] = array_keys($missing);
            $report['empty'][$language] = array_keys($empty);

            $report['stats'][$language] = [
                'total' => count($baseTranslations),
                'translated' => count($langTranslations),
                'missing' => count($missing),
                'empty' => count($empty),
                'completion' => round((count($langTranslations) / count($baseTranslations)) * 100, 2)
            ];
        }

        return $report;
    }

    public static function findUntranslatedContent($language)
    {
        $baseLanguage = config('app.locale');
        $baseContent = cms_translations('*', $baseLanguage);
        $translatedContent = cms_translations('*', $language);

        return array_diff_key($baseContent, $translatedContent);
    }
}

// Generate translation report
$report = TranslationValidator::validateTranslations(['es', 'fr', 'de']);

/**
 * Example 14: Advanced Language Features
 *
 * Currency, dates, and number formatting per language
 */

// Language-aware formatting
class LocaleFormatter
{
    public static function formatCurrency($amount, $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        $currencies = [
            'en' => 'USD',
            'es' => 'EUR',
            'fr' => 'EUR',
            'de' => 'EUR',
        ];

        $currency = $currencies[$locale] ?? 'USD';
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, $currency);
    }

    public static function formatDate($date, $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        $formats = cms_config("locales.{$locale}.date_format", 'M j, Y');

        return $date->format($formats);
    }

    public static function formatNumber($number, $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);

        return $formatter->format($number);
    }
}

// Usage examples
?>
<div class="pricing">
    <span class="price">{{ LocaleFormatter::formatCurrency(99.99) }}</span>
    <span class="date">{{ LocaleFormatter::formatDate(now()) }}</span>
    <span class="views">{{ LocaleFormatter::formatNumber(1234567) }}</span>
</div>

<?php

echo "✅ Multi-language examples completed!\n";
echo "Your CMS now supports comprehensive multi-language functionality.\n";