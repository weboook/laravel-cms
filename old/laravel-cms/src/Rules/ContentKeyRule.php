<?php

namespace Webook\LaravelCMS\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Content Key Validation Rule
 *
 * Validates content key format for CMS operations.
 * Content keys must be alphanumeric with specific allowed characters.
 */
class ContentKeyRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Content key must be a string
        if (!is_string($value)) {
            return false;
        }

        // Check length constraints
        if (strlen($value) < 1 || strlen($value) > 255) {
            return false;
        }

        // Allow alphanumeric characters, hyphens, underscores, dots, and forward slashes
        // This supports nested content structures like 'page.hero.title' or 'blog/post-1'
        return preg_match('/^[a-zA-Z0-9\-_.\/]+$/', $value) === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be a valid content key (alphanumeric characters, hyphens, underscores, dots, and forward slashes only).';
    }
}