<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\HtmlString;
use Illuminate\Contracts\Translation\Translator;

class TranslationWrapper implements Translator
{
    public $originalTranslator;
    protected $enabled = false;

    public function __construct()
    {
        // Original translator will be set by service provider
        // Don't resolve it here to avoid circular dependency
    }

    /**
     * Enable CMS wrapping for translations
     */
    public function enable()
    {
        $this->enabled = true;
    }

    /**
     * Disable CMS wrapping for translations
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Check if CMS wrapping is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Get a translation with optional CMS wrapping
     */
    public function get($key, array $replace = [], $locale = null)
    {
        // If no original translator, return the key
        if (!$this->originalTranslator) {
            return $key;
        }

        // Get the actual translation
        $translation = $this->originalTranslator->get($key, $replace, $locale);

        // If CMS is not enabled or this is the same as the key (translation not found), return as-is
        if (!$this->enabled || $translation === $key) {
            return $translation;
        }

        // Don't wrap if we're in a non-HTML context or if it's an array
        if (is_array($translation)) {
            return $translation;
        }

        // Parse the key to determine file and nested key
        $keyParts = explode('.', $key);
        $file = count($keyParts) > 1 ? $keyParts[0] : null;

        // Generate a unique ID for this translation
        $id = 'trans-' . substr(md5($key), 0, 16);

        // Build the data attributes
        $attrs = sprintf(
            'data-cms-editable="true" data-cms-type="translation" data-cms-id="%s" data-translation-key="%s" data-cms-original="%s"',
            htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($translation, ENT_QUOTES, 'UTF-8')
        );

        if ($file) {
            $attrs .= sprintf(' data-translation-file="%s"', htmlspecialchars($file, ENT_QUOTES, 'UTF-8'));
        }

        // Wrap in span with CMS attributes and return as HtmlString to prevent double-escaping
        return new HtmlString(sprintf('<span %s>%s</span>', $attrs, $translation));
    }

    /**
     * Get a translation with choice (pluralization) and optional CMS wrapping
     */
    public function choice($key, $number, array $replace = [], $locale = null)
    {
        // If no original translator, return the key
        if (!$this->originalTranslator) {
            return $key;
        }

        // Get the actual translation
        $translation = $this->originalTranslator->choice($key, $number, $replace, $locale);

        // If CMS is not enabled, return as-is
        if (!$this->enabled || $translation === $key) {
            return $translation;
        }

        // Parse the key
        $keyParts = explode('.', $key);
        $file = count($keyParts) > 1 ? $keyParts[0] : null;

        // Generate a unique ID
        $id = 'trans-' . substr(md5($key), 0, 16);

        // Build the data attributes
        $attrs = sprintf(
            'data-cms-editable="true" data-cms-type="translation" data-cms-id="%s" data-translation-key="%s" data-translation-plural="true" data-cms-original="%s"',
            htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($translation, ENT_QUOTES, 'UTF-8')
        );

        if ($file) {
            $attrs .= sprintf(' data-translation-file="%s"', htmlspecialchars($file, ENT_QUOTES, 'UTF-8'));
        }

        // Wrap in span and return as HtmlString to prevent double-escaping
        return new HtmlString(sprintf('<span %s>%s</span>', $attrs, $translation));
    }

    /**
     * Check if translation exists
     */
    public function has($key, $locale = null)
    {
        return $this->originalTranslator ? $this->originalTranslator->has($key, $locale) : false;
    }

    /**
     * Get the current locale
     */
    public function getLocale()
    {
        return $this->originalTranslator->getLocale();
    }

    /**
     * Set the current locale
     */
    public function setLocale($locale)
    {
        return $this->originalTranslator->setLocale($locale);
    }

    /**
     * Proxy other methods to original translator
     */
    public function __call($method, $parameters)
    {
        return $this->originalTranslator->$method(...$parameters);
    }
}
