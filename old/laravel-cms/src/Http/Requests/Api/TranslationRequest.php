<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Translation Request
 *
 * Form request for creating and updating translations.
 */
class TranslationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('translate-content');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                'max:10',
                Rule::in(array_keys(config('cms.locales', ['en' => 'English']))),
            ],
            'group' => 'required|string|max:100|regex:/^[a-zA-Z0-9._-]+$/',
            'key' => 'required|string|max:255|regex:/^[a-zA-Z0-9._-]+$/',
            'value' => 'required|string|max:' . config('cms.translations.max_length', 10000),
            'status' => 'sometimes|string|in:active,inactive,pending,review',
            'metadata' => 'sometimes|array',
            'metadata.*' => 'string|max:1000',
            'context' => 'sometimes|string|max:500',
            'source_locale' => 'sometimes|string|max:10',
            'auto_generated' => 'sometimes|boolean',
            'requires_review' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'locale.required' => 'Locale is required.',
            'locale.in' => 'The selected locale is not supported.',
            'group.required' => 'Translation group is required.',
            'group.regex' => 'Group may only contain letters, numbers, dots, underscores, and hyphens.',
            'key.required' => 'Translation key is required.',
            'key.regex' => 'Key may only contain letters, numbers, dots, underscores, and hyphens.',
            'value.required' => 'Translation value is required.',
            'value.max' => 'Translation value exceeds maximum allowed length.',
            'status.in' => 'Status must be one of: active, inactive, pending, review.',
            'context.max' => 'Context must not exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'locale' => 'language',
            'group' => 'translation group',
            'key' => 'translation key',
            'value' => 'translation text',
            'source_locale' => 'source language',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize locale
        if ($this->has('locale')) {
            $this->merge([
                'locale' => strtolower(trim($this->input('locale')))
            ]);
        }

        // Sanitize group and key
        foreach (['group', 'key'] as $field) {
            if ($this->has($field)) {
                $value = preg_replace('/[^a-zA-Z0-9._-]/', '', $this->input($field));
                $value = preg_replace('/\.{2,}/', '.', $value);
                $value = trim($value, '.-_');
                $this->merge([$field => strtolower($value)]);
            }
        }

        // Trim and normalize value
        if ($this->has('value')) {
            $value = trim($this->input('value'));
            $this->merge(['value' => $value]);
        }

        // Set defaults
        $this->merge([
            'status' => $this->input('status', 'active'),
            'auto_generated' => $this->input('auto_generated', false),
            'requires_review' => $this->input('requires_review', false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTranslationKey($validator);
            $this->validateTranslationValue($validator);
            $this->validatePlaceholders($validator);
            $this->validateSpecialCharacters($validator);
        });
    }

    /**
     * Validate translation key format and uniqueness.
     */
    private function validateTranslationKey($validator): void
    {
        $locale = $this->input('locale');
        $group = $this->input('group');
        $key = $this->input('key');

        if (!$locale || !$group || !$key) {
            return;
        }

        // Check key format
        if (strlen($key) < 2) {
            $validator->errors()->add('key', 'Translation key must be at least 2 characters long.');
        }

        // Check for reserved keys
        $reservedKeys = config('cms.translations.reserved_keys', [
            'system', 'internal', 'config', 'debug'
        ]);

        foreach ($reservedKeys as $reserved) {
            if (str_starts_with($key, $reserved . '.') || $key === $reserved) {
                $validator->errors()->add('key', "Key '{$reserved}' is reserved and cannot be used.");
                break;
            }
        }

        // Validate key hierarchy depth
        $maxDepth = config('cms.translations.max_key_depth', 5);
        $depth = substr_count($key, '.') + 1;

        if ($depth > $maxDepth) {
            $validator->errors()->add('key', "Key depth exceeds maximum of {$maxDepth} levels.");
        }
    }

    /**
     * Validate translation value content.
     */
    private function validateTranslationValue($validator): void
    {
        $value = $this->input('value');

        if (empty($value)) {
            return;
        }

        // Check for minimum length for certain types
        $key = $this->input('key', '');

        if (str_contains($key, 'title') && strlen($value) < 3) {
            $validator->errors()->add('value', 'Title translations must be at least 3 characters long.');
        }

        if (str_contains($key, 'description') && strlen($value) < 10) {
            $validator->errors()->add('value', 'Description translations must be at least 10 characters long.');
        }

        // Check for dangerous content
        if ($this->containsDangerousContent($value)) {
            $validator->errors()->add('value', 'Translation contains potentially dangerous content.');
        }

        // Validate encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $validator->errors()->add('value', 'Translation must be valid UTF-8.');
        }
    }

    /**
     * Validate placeholders consistency.
     */
    private function validatePlaceholders($validator): void
    {
        $value = $this->input('value');
        $sourceLocale = $this->input('source_locale');

        if (!$value || !$sourceLocale) {
            return;
        }

        // Extract placeholders from the translation
        preg_match_all('/:\w+|{\w+}|\[\w+\]/', $value, $valuePlaceholders);
        $valuePlaceholders = $valuePlaceholders[0];

        // If we have a source locale, compare placeholders
        if ($sourceLocale) {
            $sourceTranslation = $this->getSourceTranslation($sourceLocale);

            if ($sourceTranslation) {
                preg_match_all('/:\w+|{\w+}|\[\w+\]/', $sourceTranslation, $sourcePlaceholders);
                $sourcePlaceholders = $sourcePlaceholders[0];

                $missingPlaceholders = array_diff($sourcePlaceholders, $valuePlaceholders);
                $extraPlaceholders = array_diff($valuePlaceholders, $sourcePlaceholders);

                if (!empty($missingPlaceholders)) {
                    $validator->errors()->add('value', 'Missing placeholders: ' . implode(', ', $missingPlaceholders));
                }

                if (!empty($extraPlaceholders)) {
                    $validator->errors()->add('value', 'Extra placeholders: ' . implode(', ', $extraPlaceholders));
                }
            }
        }
    }

    /**
     * Validate special characters based on locale.
     */
    private function validateSpecialCharacters($validator): void
    {
        $value = $this->input('value');
        $locale = $this->input('locale');

        if (!$value || !$locale) {
            return;
        }

        // Check for locale-specific character requirements
        $localeRules = config('cms.translations.locale_rules', []);

        if (isset($localeRules[$locale])) {
            $rules = $localeRules[$locale];

            // Check required character sets
            if (isset($rules['required_charset'])) {
                $charset = $rules['required_charset'];

                if (!preg_match("/^[{$charset}\\s\\p{P}\\p{N}]*$/u", $value)) {
                    $validator->errors()->add('value', "Translation contains characters not allowed for locale {$locale}.");
                }
            }

            // Check for RTL languages
            if (isset($rules['rtl']) && $rules['rtl']) {
                if (!$this->containsRtlCharacters($value)) {
                    $validator->errors()->add('value', 'RTL language translation should contain RTL characters.');
                }
            }

            // Check for specific formatting rules
            if (isset($rules['number_format'])) {
                if (preg_match('/\d/', $value) && !$this->validateNumberFormat($value, $rules['number_format'])) {
                    $validator->errors()->add('value', 'Number format does not match locale requirements.');
                }
            }
        }
    }

    /**
     * Get source translation for comparison.
     */
    private function getSourceTranslation(string $sourceLocale): ?string
    {
        $group = $this->input('group');
        $key = $this->input('key');

        if (!$group || !$key) {
            return null;
        }

        $translation = \Webook\LaravelCMS\Models\Translation::where([
            'locale' => $sourceLocale,
            'group' => $group,
            'key' => $key,
        ])->first();

        return $translation?->value;
    }

    /**
     * Check for dangerous content in translation.
     */
    private function containsDangerousContent(string $content): bool
    {
        $dangerousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if text contains RTL characters.
     */
    private function containsRtlCharacters(string $text): bool
    {
        // Unicode ranges for RTL languages
        $rtlPattern = '/[\x{0590}-\x{05FF}\x{0600}-\x{06FF}\x{0700}-\x{074F}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

        return preg_match($rtlPattern, $text) === 1;
    }

    /**
     * Validate number format for specific locale.
     */
    private function validateNumberFormat(string $text, array $format): bool
    {
        // This is a simplified validation
        // In production, you might want more sophisticated number format validation
        $decimalSeparator = $format['decimal'] ?? '.';
        $thousandsSeparator = $format['thousands'] ?? ',';

        // Check if numbers in the text follow the expected format
        $numberPattern = '/\d+([' . preg_quote($thousandsSeparator) . ']\d{3})*([' . preg_quote($decimalSeparator) . ']\d+)?/';

        return preg_match($numberPattern, $text) === 1;
    }
}