<?php

namespace Webook\LaravelCMS\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

/**
 * Asset Type Validation Rule
 *
 * Validates uploaded file types according to CMS configuration.
 * Checks both file extensions and MIME types for security.
 */
class AssetTypeRule implements Rule
{
    /**
     * Allowed file types from configuration.
     *
     * @var array
     */
    protected $allowedTypes;

    /**
     * Create a new rule instance.
     *
     * @param array|null $allowedTypes
     */
    public function __construct(array $allowedTypes = null)
    {
        $this->allowedTypes = $allowedTypes ?? config('cms.storage.uploads.allowed_types', []);
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
        if (!($value instanceof UploadedFile)) {
            return false;
        }

        // Check file extension
        $extension = strtolower($value->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedTypes)) {
            return false;
        }

        // Additional MIME type validation for security
        return $this->validateMimeType($value, $extension);
    }

    /**
     * Validate MIME type matches expected type for extension.
     *
     * @param UploadedFile $file
     * @param string $extension
     * @return bool
     */
    protected function validateMimeType(UploadedFile $file, string $extension): bool
    {
        $mimeType = $file->getMimeType();

        // Define expected MIME types for common extensions
        $expectedMimeTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'svg' => ['image/svg+xml'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'application/csv', 'text/plain'],
            'mp4' => ['video/mp4'],
            'mp3' => ['audio/mpeg'],
            'zip' => ['application/zip'],
        ];

        if (isset($expectedMimeTypes[$extension])) {
            return in_array($mimeType, $expectedMimeTypes[$extension]);
        }

        // If extension is not in our predefined list, allow it
        // (this provides flexibility for additional file types)
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        $allowedTypes = implode(', ', $this->allowedTypes);
        return "The :attribute must be a file of type: {$allowedTypes}";
    }
}