<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Update Image Request
 *
 * Form request for updating image metadata and properties.
 */
class UpdateImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('manage-media');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:images,id',
            'alt_text' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'metadata' => 'sometimes|array',
            'metadata.*' => 'string|max:1000',
            'file_path' => 'sometimes|string|max:512',
            'old_path' => 'sometimes|string|max:512',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'category' => 'sometimes|string|max:100',
            'is_featured' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'Image ID is required.',
            'id.exists' => 'Image not found.',
            'alt_text.max' => 'Alt text must not exceed 255 characters.',
            'title.max' => 'Title must not exceed 255 characters.',
            'description.max' => 'Description must not exceed 1000 characters.',
            'metadata.array' => 'Metadata must be an object.',
            'tags.array' => 'Tags must be an array.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
            'sort_order.min' => 'Sort order must be a positive number.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize alt text
        if ($this->has('alt_text')) {
            $this->merge([
                'alt_text' => $this->sanitizeText($this->input('alt_text')),
            ]);
        }

        // Sanitize title
        if ($this->has('title')) {
            $this->merge([
                'title' => $this->sanitizeText($this->input('title')),
            ]);
        }

        // Sanitize description
        if ($this->has('description')) {
            $this->merge([
                'description' => $this->sanitizeText($this->input('description')),
            ]);
        }

        // Sanitize tags
        if ($this->has('tags')) {
            $tags = array_filter(array_map('trim', $this->input('tags', [])));
            $this->merge(['tags' => array_unique($tags)]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateImageExists($validator);
            $this->validateFilePaths($validator);
            $this->validateMetadata($validator);
        });
    }

    /**
     * Validate that the image exists and user has permission.
     */
    private function validateImageExists($validator): void
    {
        if ($this->has('id')) {
            $imageId = $this->input('id');
            $image = \Webook\LaravelCMS\Models\Image::find($imageId);

            if ($image && !$this->user()->can('update', $image)) {
                $validator->errors()->add('id', 'You do not have permission to update this image.');
            }
        }
    }

    /**
     * Validate file paths if provided.
     */
    private function validateFilePaths($validator): void
    {
        if ($this->has('file_path')) {
            $filePath = $this->input('file_path');

            // Check for path traversal
            if (str_contains($filePath, '..') || str_contains($filePath, '//')) {
                $validator->errors()->add('file_path', 'Invalid file path detected.');
                return;
            }

            // Validate file extension
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $allowedExtensions = config('cms.images.allowed_extensions', [
                'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'
            ]);

            if (!in_array($extension, $allowedExtensions)) {
                $validator->errors()->add('file_path', 'File extension is not allowed.');
            }
        }

        if ($this->has('old_path')) {
            $oldPath = $this->input('old_path');

            if (str_contains($oldPath, '..') || str_contains($oldPath, '//')) {
                $validator->errors()->add('old_path', 'Invalid old file path detected.');
            }
        }
    }

    /**
     * Validate metadata structure and content.
     */
    private function validateMetadata($validator): void
    {
        if ($this->has('metadata')) {
            $metadata = $this->input('metadata');

            // Check metadata size
            $serializedSize = strlen(serialize($metadata));
            $maxSize = config('cms.images.max_metadata_size', 10240); // 10KB

            if ($serializedSize > $maxSize) {
                $validator->errors()->add('metadata', "Metadata exceeds maximum size of {$maxSize} bytes.");
            }

            // Validate specific metadata fields
            $this->validateSpecificMetadata($validator, $metadata);
        }
    }

    /**
     * Validate specific metadata fields.
     */
    private function validateSpecificMetadata($validator, array $metadata): void
    {
        // Validate dimensions if provided
        if (isset($metadata['width']) || isset($metadata['height'])) {
            if (!is_numeric($metadata['width'] ?? 0) || !is_numeric($metadata['height'] ?? 0)) {
                $validator->errors()->add('metadata.dimensions', 'Image dimensions must be numeric.');
            }
        }

        // Validate copyright information
        if (isset($metadata['copyright'])) {
            if (strlen($metadata['copyright']) > 500) {
                $validator->errors()->add('metadata.copyright', 'Copyright information is too long.');
            }
        }

        // Validate GPS coordinates if provided
        if (isset($metadata['gps'])) {
            if (!$this->isValidGpsCoordinate($metadata['gps'])) {
                $validator->errors()->add('metadata.gps', 'Invalid GPS coordinates.');
            }
        }

        // Check for sensitive information in metadata
        $sensitiveFields = ['password', 'secret', 'key', 'token'];
        foreach ($metadata as $key => $value) {
            if (is_string($key) && $this->containsSensitiveData($key, $value, $sensitiveFields)) {
                $validator->errors()->add('metadata.' . $key, 'Metadata contains sensitive information.');
            }
        }
    }

    /**
     * Validate GPS coordinates.
     */
    private function isValidGpsCoordinate($gps): bool
    {
        if (!is_array($gps) || !isset($gps['lat']) || !isset($gps['lng'])) {
            return false;
        }

        $lat = $gps['lat'];
        $lng = $gps['lng'];

        return is_numeric($lat) && is_numeric($lng)
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;
    }

    /**
     * Check for sensitive data in metadata.
     */
    private function containsSensitiveData(string $key, $value, array $sensitiveFields): bool
    {
        $key = strtolower($key);

        foreach ($sensitiveFields as $field) {
            if (str_contains($key, $field)) {
                return true;
            }
        }

        if (is_string($value)) {
            // Check for patterns that might indicate sensitive data
            $patterns = [
                '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email
                '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', // Credit card
                '/\b\d{3}[\s-]?\d{2}[\s-]?\d{4}\b/', // SSN
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sanitize text input.
     */
    private function sanitizeText(string $text): string
    {
        // Remove potentially dangerous HTML tags
        $text = strip_tags($text, '<em><strong><i><b>');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}