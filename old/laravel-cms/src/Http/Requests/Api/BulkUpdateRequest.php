<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Bulk Update Request
 *
 * Form request for bulk updating multiple content items.
 */
class BulkUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('edit-content');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'updates' => 'required|array|min:1|max:' . config('cms.bulk.max_items', 100),
            'updates.*.key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],
            'updates.*.value' => [
                'required',
                'string',
                'max:' . config('cms.content.max_text_length', 65535),
            ],
            'updates.*.locale' => [
                'sometimes',
                'string',
                'max:10',
                Rule::in(array_keys(config('cms.locales', ['en' => 'English']))),
            ],
            'updates.*.metadata' => 'sometimes|array',
            'updates.*.metadata.*' => 'string|max:1000',
            'validate_all' => 'sometimes|boolean',
            'continue_on_error' => 'sometimes|boolean',
            'create_backup' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'updates.required' => 'Updates array is required.',
            'updates.array' => 'Updates must be an array.',
            'updates.min' => 'At least one update is required.',
            'updates.max' => 'Maximum of ' . config('cms.bulk.max_items', 100) . ' updates allowed.',
            'updates.*.key.required' => 'Content key is required for all updates.',
            'updates.*.key.regex' => 'Content key may only contain letters, numbers, dots, underscores, and hyphens.',
            'updates.*.value.required' => 'Content value is required for all updates.',
            'updates.*.value.max' => 'Content value exceeds maximum allowed length.',
            'updates.*.locale.in' => 'Invalid locale specified.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $updates = $this->input('updates', []);

        // Sanitize and normalize updates
        $sanitizedUpdates = array_map(function ($update) {
            // Sanitize key
            if (isset($update['key'])) {
                $update['key'] = $this->sanitizeKey($update['key']);
            }

            // Trim value
            if (isset($update['value'])) {
                $update['value'] = trim($update['value']);
            }

            // Set default locale
            if (!isset($update['locale'])) {
                $update['locale'] = app()->getLocale();
            }

            return $update;
        }, $updates);

        $this->merge([
            'updates' => $sanitizedUpdates,
            'validate_all' => $this->input('validate_all', true),
            'continue_on_error' => $this->input('continue_on_error', false),
            'create_backup' => $this->input('create_backup', true),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateUniqueKeys($validator);
            $this->validateBulkLimits($validator);
            $this->validateContentSecurity($validator);
        });
    }

    /**
     * Validate that keys are unique within the update set.
     */
    private function validateUniqueKeys($validator): void
    {
        $updates = $this->input('updates', []);
        $keyLocaleMap = [];

        foreach ($updates as $index => $update) {
            $key = $update['key'] ?? '';
            $locale = $update['locale'] ?? app()->getLocale();
            $identifier = "{$key}:{$locale}";

            if (isset($keyLocaleMap[$identifier])) {
                $validator->errors()->add(
                    "updates.{$index}.key",
                    "Duplicate key '{$key}' for locale '{$locale}' found in updates."
                );
            }

            $keyLocaleMap[$identifier] = $index;
        }
    }

    /**
     * Validate bulk operation limits.
     */
    private function validateBulkLimits($validator): void
    {
        $updates = $this->input('updates', []);

        // Check total content size
        $totalSize = 0;
        foreach ($updates as $update) {
            $totalSize += strlen($update['value'] ?? '');
        }

        $maxTotalSize = config('cms.bulk.max_total_size', 1048576); // 1MB default
        if ($totalSize > $maxTotalSize) {
            $validator->errors()->add(
                'updates',
                "Total content size exceeds limit of {$maxTotalSize} bytes."
            );
        }

        // Check for rate limiting
        $maxUpdatesPerMinute = config('cms.bulk.max_per_minute', 1000);
        $recentUpdates = cache()->get(
            'bulk_updates:' . $this->user()->id,
            0
        );

        if ($recentUpdates + count($updates) > $maxUpdatesPerMinute) {
            $validator->errors()->add(
                'updates',
                'Rate limit exceeded. Too many bulk updates in the last minute.'
            );
        }
    }

    /**
     * Validate content security for all updates.
     */
    private function validateContentSecurity($validator): void
    {
        $updates = $this->input('updates', []);

        foreach ($updates as $index => $update) {
            $value = $update['value'] ?? '';

            // Check for dangerous content
            if ($this->containsDangerousContent($value)) {
                $validator->errors()->add(
                    "updates.{$index}.value",
                    'Content contains potentially dangerous elements.'
                );
            }

            // Check for PHP code injection
            if (!config('cms.content.allow_php', false) && $this->containsPhpCode($value)) {
                $validator->errors()->add(
                    "updates.{$index}.value",
                    'PHP code is not allowed in content.'
                );
            }
        }
    }

    /**
     * Check if content contains dangerous elements.
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
     * Check if content contains PHP code.
     */
    private function containsPhpCode(string $content): bool
    {
        return preg_match('/<\?php|<\?=|\?>/i', $content) === 1;
    }

    /**
     * Sanitize content key.
     */
    private function sanitizeKey(string $key): string
    {
        $key = preg_replace('/[^a-zA-Z0-9._-]/', '', $key);
        $key = preg_replace('/\.{2,}/', '.', $key);
        $key = trim($key, '.-_');

        return strtolower($key);
    }

    /**
     * Get validated data with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set rate limiting cache
        if (isset($validated['updates'])) {
            $currentCount = cache()->get('bulk_updates:' . $this->user()->id, 0);
            cache()->put(
                'bulk_updates:' . $this->user()->id,
                $currentCount + count($validated['updates']),
                now()->addMinutes(1)
            );
        }

        return $validated;
    }
}