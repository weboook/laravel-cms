<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Update Text Request
 *
 * Form request for updating text content with comprehensive validation.
 */
class UpdateTextRequest extends FormRequest
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
            'key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],
            'value' => [
                'required',
                'string',
                'max:' . config('cms.content.max_text_length', 65535),
            ],
            'locale' => [
                'sometimes',
                'string',
                'max:10',
                Rule::in(array_keys(config('cms.locales', ['en' => 'English']))),
            ],
            'metadata' => 'sometimes|array',
            'metadata.*' => 'string|max:1000',
            'file_path' => 'sometimes|string|max:512',
            'line_number' => 'sometimes|integer|min:1',
            'selector' => 'sometimes|string|max:255',
            'backup' => 'sometimes|boolean',
            'validate_syntax' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'key.required' => 'Content key is required.',
            'key.regex' => 'Content key may only contain letters, numbers, dots, underscores, and hyphens.',
            'value.required' => 'Content value is required.',
            'value.max' => 'Content value exceeds maximum allowed length.',
            'locale.in' => 'The selected locale is not supported.',
            'metadata.array' => 'Metadata must be an object.',
            'file_path.string' => 'File path must be a string.',
            'line_number.integer' => 'Line number must be an integer.',
            'line_number.min' => 'Line number must be at least 1.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'key' => 'content key',
            'value' => 'content value',
            'locale' => 'language',
            'file_path' => 'file path',
            'line_number' => 'line number',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize key
        if ($this->has('key')) {
            $this->merge([
                'key' => $this->sanitizeKey($this->input('key')),
            ]);
        }

        // Trim value
        if ($this->has('value')) {
            $this->merge([
                'value' => trim($this->input('value')),
            ]);
        }

        // Set default locale
        if (!$this->has('locale')) {
            $this->merge([
                'locale' => app()->getLocale(),
            ]);
        }

        // Set default backup option
        if (!$this->has('backup')) {
            $this->merge([
                'backup' => config('cms.content.auto_backup', true),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check content length for specific content types
            if ($this->has('value')) {
                $this->validateContentLength($validator);
            }

            // Validate file path if provided
            if ($this->has('file_path')) {
                $this->validateFilePath($validator);
            }

            // Check for malicious content
            if ($this->has('value')) {
                $this->validateContentSecurity($validator);
            }
        });
    }

    /**
     * Sanitize content key.
     */
    private function sanitizeKey(string $key): string
    {
        // Remove invalid characters and normalize
        $key = preg_replace('/[^a-zA-Z0-9._-]/', '', $key);
        $key = preg_replace('/\.{2,}/', '.', $key); // Remove consecutive dots
        $key = trim($key, '.-_'); // Remove leading/trailing separators

        return strtolower($key);
    }

    /**
     * Validate content length based on type.
     */
    private function validateContentLength($validator): void
    {
        $value = $this->input('value');
        $maxLength = config('cms.content.max_text_length', 65535);

        // Check for extremely long content
        if (strlen($value) > $maxLength) {
            $validator->errors()->add('value', "Content exceeds maximum length of {$maxLength} characters.");
        }

        // Check for minimum content for certain keys
        $key = $this->input('key');
        if (str_contains($key, 'title') && strlen($value) < 3) {
            $validator->errors()->add('value', 'Title content must be at least 3 characters long.');
        }
    }

    /**
     * Validate file path security.
     */
    private function validateFilePath($validator): void
    {
        $filePath = $this->input('file_path');

        // Check for path traversal attacks
        if (str_contains($filePath, '..') || str_contains($filePath, '//')) {
            $validator->errors()->add('file_path', 'Invalid file path detected.');
            return;
        }

        // Check if path is within allowed directories
        $allowedPaths = config('cms.content.allowed_paths', [
            'resources/views',
            'resources/lang',
            'public',
        ]);

        $isAllowed = false;
        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($filePath, $allowedPath)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            $validator->errors()->add('file_path', 'File path is not in an allowed directory.');
        }

        // Check file extension
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $allowedExtensions = config('cms.content.allowed_extensions', [
            'blade.php', 'php', 'html', 'txt', 'md', 'json',
        ]);

        if (!in_array($extension, $allowedExtensions) && !str_ends_with($filePath, '.blade.php')) {
            $validator->errors()->add('file_path', 'File extension is not allowed.');
        }
    }

    /**
     * Validate content for security issues.
     */
    private function validateContentSecurity($validator): void
    {
        $value = $this->input('value');

        // Check for potentially dangerous content
        $dangerousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $validator->errors()->add('value', 'Content contains potentially dangerous elements.');
                break;
            }
        }

        // Check for PHP code injection (unless explicitly allowed)
        if (!config('cms.content.allow_php', false)) {
            if (preg_match('/<\?php|<\?=|\?>/i', $value)) {
                $validator->errors()->add('value', 'PHP code is not allowed in content.');
            }
        }
    }
}