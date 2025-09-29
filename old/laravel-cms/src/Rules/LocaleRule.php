<?php

namespace Webook\LaravelCMS\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Locale Validation Rule
 *
 * Validates locale codes according to CMS configuration.
 * Supports both standard locale formats and checks against configured available locales.
 */
class LocaleRule implements Rule
{
    /**
     * Available locales from configuration.
     *
     * @var array
     */
    protected $availableLocales;

    /**
     * Create a new rule instance.
     *
     * @param array|null $availableLocales
     */
    public function __construct(array $availableLocales = null)
    {
        $this->availableLocales = $availableLocales ?? config('cms.locale.available', ['en']);
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Check if locale is in the configured available locales
        if (!in_array($value, $this->availableLocales)) {
            return false;
        }

        // Validate locale format: language code (2 chars) optionally followed by country code
        // Examples: en, en-US, fr, fr-CA, de-DE
        return preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $value) === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        $availableLocales = implode(', ', $this->availableLocales);
        return "The :attribute must be a valid locale code. Available locales: {$availableLocales}";
    }
}