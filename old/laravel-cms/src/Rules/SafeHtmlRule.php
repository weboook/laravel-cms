<?php

namespace Webook\LaravelCMS\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Safe HTML Validation Rule
 *
 * Validates HTML content according to CMS security settings.
 * Checks for allowed tags and prevents potentially dangerous content.
 */
class SafeHtmlRule implements Rule
{
    /**
     * Allowed HTML tags from configuration.
     *
     * @var array
     */
    protected $allowedTags;

    /**
     * Allowed HTML attributes from configuration.
     *
     * @var array
     */
    protected $allowedAttributes;

    /**
     * Create a new rule instance.
     *
     * @param array|null $allowedTags
     * @param array|null $allowedAttributes
     */
    public function __construct(array $allowedTags = null, array $allowedAttributes = null)
    {
        $this->allowedTags = $allowedTags ?? config('cms.security.allowed_tags', []);
        $this->allowedAttributes = $allowedAttributes ?? config('cms.security.allowed_attributes', []);
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

        // Check for potentially dangerous patterns
        if ($this->containsDangerousPatterns($value)) {
            return false;
        }

        // If HTML purifier is enabled, validate against allowed tags
        if (config('cms.security.html_purifier.enabled', true)) {
            return $this->validateAllowedTags($value);
        }

        return true;
    }

    /**
     * Check for dangerous patterns in HTML content.
     *
     * @param string $content
     * @return bool
     */
    protected function containsDangerousPatterns(string $content): bool
    {
        $dangerousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',  // Script tags
            '/javascript:/i',                                          // JavaScript URLs
            '/on\w+\s*=/i',                                           // Event handlers
            '/<iframe\b/i',                                           // Iframes
            '/<object\b/i',                                           // Object tags
            '/<embed\b/i',                                            // Embed tags
            '/<form\b/i',                                             // Form tags
            '/data:text\/html/i',                                     // Data URLs
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that only allowed tags are used.
     *
     * @param string $content
     * @return bool
     */
    protected function validateAllowedTags(string $content): bool
    {
        // Extract all HTML tags from content
        preg_match_all('/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/i', $content, $matches);

        if (empty($matches[1])) {
            return true; // No tags found
        }

        $usedTags = array_unique(array_map('strtolower', $matches[1]));

        // Check if all used tags are allowed
        foreach ($usedTags as $tag) {
            if (!in_array($tag, $this->allowedTags)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute contains invalid or potentially dangerous HTML content.';
    }
}