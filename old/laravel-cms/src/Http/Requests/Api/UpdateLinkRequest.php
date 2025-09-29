<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Update Link Request
 *
 * Form request for updating link metadata and properties.
 */
class UpdateLinkRequest extends FormRequest
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
            'identifier' => 'required|string|max:255|regex:/^[a-zA-Z0-9._-]+$/',
            'url' => 'required|url|max:2048',
            'text' => 'sometimes|string|max:500',
            'title' => 'sometimes|string|max:255',
            'target' => 'sometimes|string|in:_self,_blank,_parent,_top',
            'rel' => 'sometimes|string|max:100',
            'metadata' => 'sometimes|array',
            'metadata.*' => 'string|max:1000',
            'validate' => 'sometimes|boolean',
            'tags' => 'sometimes|array|max:10',
            'tags.*' => 'string|max:50',
            'category' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'Link identifier is required.',
            'identifier.regex' => 'Identifier may only contain letters, numbers, dots, underscores, and hyphens.',
            'url.required' => 'URL is required.',
            'url.url' => 'URL must be a valid URL.',
            'url.max' => 'URL must not exceed 2048 characters.',
            'text.max' => 'Link text must not exceed 500 characters.',
            'title.max' => 'Title must not exceed 255 characters.',
            'target.in' => 'Target must be one of: _self, _blank, _parent, _top.',
            'rel.max' => 'Rel attribute must not exceed 100 characters.',
            'tags.max' => 'Maximum 10 tags allowed.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize identifier
        if ($this->has('identifier')) {
            $identifier = preg_replace('/[^a-zA-Z0-9._-]/', '', $this->input('identifier'));
            $this->merge(['identifier' => strtolower($identifier)]);
        }

        // Normalize URL
        if ($this->has('url')) {
            $url = trim($this->input('url'));

            // Add protocol if missing
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }

            $this->merge(['url' => $url]);
        }

        // Sanitize text fields
        foreach (['text', 'title'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => $this->sanitizeText($this->input($field))
                ]);
            }
        }

        // Sanitize tags
        if ($this->has('tags')) {
            $tags = array_filter(array_map('trim', $this->input('tags', [])));
            $this->merge(['tags' => array_unique($tags)]);
        }

        // Set defaults
        $this->merge([
            'target' => $this->input('target', '_self'),
            'is_active' => $this->input('is_active', true),
            'validate' => $this->input('validate', false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateUrl($validator);
            $this->validateRelAttribute($validator);
            $this->validateSecurity($validator);
        });
    }

    /**
     * Validate URL security and format.
     */
    private function validateUrl($validator): void
    {
        if (!$this->has('url')) {
            return;
        }

        $url = $this->input('url');

        // Check for suspicious URLs
        $suspiciousPatterns = [
            '/javascript:/i',
            '/data:/i',
            '/file:/i',
            '/ftp:/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                $validator->errors()->add('url', 'URL scheme is not allowed.');
                return;
            }
        }

        // Check for malicious domains
        $blockedDomains = config('cms.links.blocked_domains', []);
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['host'])) {
            $domain = strtolower($parsedUrl['host']);

            foreach ($blockedDomains as $blockedDomain) {
                if (str_contains($domain, strtolower($blockedDomain))) {
                    $validator->errors()->add('url', 'Domain is not allowed.');
                    return;
                }
            }
        }

        // Check for shortened URLs if not allowed
        if (!config('cms.links.allow_shortened_urls', true)) {
            $shorteners = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'short.link'];

            if (isset($parsedUrl['host'])) {
                foreach ($shorteners as $shortener) {
                    if (str_contains($parsedUrl['host'], $shortener)) {
                        $validator->errors()->add('url', 'Shortened URLs are not allowed.');
                        return;
                    }
                }
            }
        }
    }

    /**
     * Validate rel attribute.
     */
    private function validateRelAttribute($validator): void
    {
        if (!$this->has('rel')) {
            return;
        }

        $rel = $this->input('rel');
        $allowedValues = [
            'alternate', 'author', 'bookmark', 'external', 'help',
            'license', 'next', 'nofollow', 'noopener', 'noreferrer',
            'prev', 'search', 'tag', 'ugc', 'sponsored'
        ];

        $relValues = array_filter(array_map('trim', explode(' ', $rel)));

        foreach ($relValues as $value) {
            if (!in_array(strtolower($value), $allowedValues)) {
                $validator->errors()->add('rel', "Invalid rel value: {$value}");
                break;
            }
        }
    }

    /**
     * Validate security aspects.
     */
    private function validateSecurity($validator): void
    {
        // Check if external links should have security attributes
        if ($this->input('target') === '_blank') {
            $rel = $this->input('rel', '');

            if (!str_contains($rel, 'noopener')) {
                $currentRel = trim($rel);
                $newRel = $currentRel ? $currentRel . ' noopener' : 'noopener';
                $this->merge(['rel' => $newRel]);
            }
        }

        // Validate metadata for sensitive information
        if ($this->has('metadata')) {
            $metadata = $this->input('metadata');

            foreach ($metadata as $key => $value) {
                if ($this->containsSensitiveData($key, $value)) {
                    $validator->errors()->add("metadata.{$key}", 'Contains sensitive information.');
                }
            }
        }
    }

    /**
     * Check for sensitive data.
     */
    private function containsSensitiveData(string $key, string $value): bool
    {
        $sensitivePatterns = [
            '/password/i',
            '/secret/i',
            '/key/i',
            '/token/i',
            '/api[_-]?key/i',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $key) || preg_match($pattern, $value)) {
                return true;
            }
        }

        // Check for patterns that might indicate credentials
        $credentialPatterns = [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email
            '/\b[A-Z0-9]{20,}\b/', // API keys
        ];

        foreach ($credentialPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize text input.
     */
    private function sanitizeText(string $text): string
    {
        // Remove dangerous HTML tags but allow basic formatting
        $text = strip_tags($text, '<em><strong><i><b>');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}