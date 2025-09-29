<?php

namespace Webook\LaravelCMS\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Upload Image Request
 *
 * Form request for uploading new images with comprehensive validation.
 */
class UploadImageRequest extends FormRequest
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
        $maxFileSize = config('cms.images.max_file_size', 10240); // KB
        $allowedMimes = implode(',', config('cms.images.allowed_mimes', [
            'jpeg', 'jpg', 'png', 'gif', 'svg', 'webp'
        ]));

        return [
            'image' => [
                'required',
                'file',
                'image',
                "max:{$maxFileSize}",
                "mimes:{$allowedMimes}",
                'dimensions:min_width=10,min_height=10,max_width=8192,max_height=8192',
            ],
            'alt_text' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'metadata' => 'sometimes|array',
            'metadata.*' => 'string|max:1000',
            'tags' => 'sometimes|array|max:20',
            'tags.*' => 'string|max:50',
            'category' => 'sometimes|string|max:100',
            'is_featured' => 'sometimes|boolean',
            'generate_thumbnails' => 'sometimes|boolean',
            'optimize' => 'sometimes|boolean',
            'watermark' => 'sometimes|boolean',
            'folder' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\/_-]+$/',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Image file is required.',
            'image.file' => 'The uploaded file must be a valid file.',
            'image.image' => 'The uploaded file must be an image.',
            'image.max' => 'Image file size must not exceed ' . config('cms.images.max_file_size', 10240) . 'KB.',
            'image.mimes' => 'Image must be of type: ' . implode(', ', config('cms.images.allowed_mimes', [])),
            'image.dimensions' => 'Image dimensions must be between 10x10 and 8192x8192 pixels.',
            'alt_text.max' => 'Alt text must not exceed 255 characters.',
            'title.max' => 'Title must not exceed 255 characters.',
            'description.max' => 'Description must not exceed 1000 characters.',
            'tags.max' => 'Maximum 20 tags allowed.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
            'folder.regex' => 'Folder path contains invalid characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default alt text from filename if not provided
        if (!$this->has('alt_text') && $this->hasFile('image')) {
            $filename = $this->file('image')->getClientOriginalName();
            $altText = pathinfo($filename, PATHINFO_FILENAME);
            $altText = str_replace(['_', '-'], ' ', $altText);
            $altText = ucwords($altText);

            $this->merge(['alt_text' => $altText]);
        }

        // Set default title from alt text if not provided
        if (!$this->has('title') && $this->has('alt_text')) {
            $this->merge(['title' => $this->input('alt_text')]);
        }

        // Sanitize folder path
        if ($this->has('folder')) {
            $folder = trim($this->input('folder'), '/');
            $folder = preg_replace('/\/+/', '/', $folder); // Remove double slashes
            $this->merge(['folder' => $folder]);
        }

        // Sanitize tags
        if ($this->has('tags')) {
            $tags = array_filter(array_map('trim', $this->input('tags', [])));
            $tags = array_unique(array_map('strtolower', $tags));
            $this->merge(['tags' => array_values($tags)]);
        }

        // Set defaults
        $this->merge([
            'generate_thumbnails' => $this->input('generate_thumbnails', true),
            'optimize' => $this->input('optimize', true),
            'watermark' => $this->input('watermark', false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateImageFile($validator);
            $this->validateUploadLimits($validator);
            $this->validateFolderPath($validator);
            $this->validateDuplicates($validator);
        });
    }

    /**
     * Validate the uploaded image file.
     */
    private function validateImageFile($validator): void
    {
        if (!$this->hasFile('image')) {
            return;
        }

        $file = $this->file('image');

        // Check if file was uploaded successfully
        if (!$file->isValid()) {
            $validator->errors()->add('image', 'File upload failed: ' . $file->getErrorMessage());
            return;
        }

        // Additional security checks
        $this->validateImageSecurity($validator, $file);
        $this->validateImageContent($validator, $file);
    }

    /**
     * Validate image security.
     */
    private function validateImageSecurity($validator, $file): void
    {
        // Check MIME type against file extension
        $detectedMime = $file->getMimeType();
        $expectedMimes = [
            'jpg' => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'svg' => ['image/svg+xml'],
            'webp' => ['image/webp'],
        ];

        $extension = strtolower($file->getClientOriginalExtension());
        if (isset($expectedMimes[$extension])) {
            if (!in_array($detectedMime, $expectedMimes[$extension])) {
                $validator->errors()->add('image', 'File content does not match extension.');
                return;
            }
        }

        // Check for malicious content in SVG files
        if ($extension === 'svg') {
            $content = file_get_contents($file->getPathname());
            if ($this->containsMaliciousSvgContent($content)) {
                $validator->errors()->add('image', 'SVG file contains malicious content.');
            }
        }
    }

    /**
     * Validate image content and properties.
     */
    private function validateImageContent($validator, $file): void
    {
        $imagePath = $file->getPathname();
        $imageInfo = getimagesize($imagePath);

        if ($imageInfo === false) {
            $validator->errors()->add('image', 'Unable to read image file.');
            return;
        }

        [$width, $height, $type] = $imageInfo;

        // Check aspect ratio limits
        $aspectRatio = $width / $height;
        $minAspectRatio = config('cms.images.min_aspect_ratio', 0.1);
        $maxAspectRatio = config('cms.images.max_aspect_ratio', 10);

        if ($aspectRatio < $minAspectRatio || $aspectRatio > $maxAspectRatio) {
            $validator->errors()->add('image', 'Image aspect ratio is outside allowed range.');
        }

        // Check for minimum quality (for JPEG)
        if ($type === IMAGETYPE_JPEG) {
            $quality = $this->estimateJpegQuality($imagePath);
            $minQuality = config('cms.images.min_jpeg_quality', 60);

            if ($quality < $minQuality) {
                $validator->errors()->add('image', "JPEG quality is too low (minimum {$minQuality}%).");
            }
        }
    }

    /**
     * Validate upload limits.
     */
    private function validateUploadLimits($validator): void
    {
        $userId = $this->user()->id;

        // Check daily upload limit
        $dailyLimit = config('cms.images.daily_upload_limit', 100);
        $dailyCount = cache()->get("daily_uploads:{$userId}:" . date('Y-m-d'), 0);

        if ($dailyCount >= $dailyLimit) {
            $validator->errors()->add('image', 'Daily upload limit exceeded.');
        }

        // Check hourly upload limit
        $hourlyLimit = config('cms.images.hourly_upload_limit', 20);
        $hourlyCount = cache()->get("hourly_uploads:{$userId}:" . date('Y-m-d-H'), 0);

        if ($hourlyCount >= $hourlyLimit) {
            $validator->errors()->add('image', 'Hourly upload limit exceeded.');
        }

        // Check storage quota
        $userStorage = $this->calculateUserStorageUsage($userId);
        $storageLimit = config('cms.images.user_storage_limit', 1073741824); // 1GB

        if ($this->hasFile('image')) {
            $fileSize = $this->file('image')->getSize();
            if ($userStorage + $fileSize > $storageLimit) {
                $validator->errors()->add('image', 'Storage quota exceeded.');
            }
        }
    }

    /**
     * Validate folder path.
     */
    private function validateFolderPath($validator): void
    {
        if (!$this->has('folder')) {
            return;
        }

        $folder = $this->input('folder');

        // Check for restricted folder names
        $restrictedFolders = config('cms.images.restricted_folders', [
            'system', 'admin', 'config', 'cache', 'tmp'
        ]);

        $folderParts = explode('/', $folder);
        foreach ($folderParts as $part) {
            if (in_array(strtolower($part), $restrictedFolders)) {
                $validator->errors()->add('folder', "Folder name '{$part}' is restricted.");
                break;
            }
        }

        // Check folder depth
        $maxDepth = config('cms.images.max_folder_depth', 5);
        if (count($folderParts) > $maxDepth) {
            $validator->errors()->add('folder', "Folder depth exceeds maximum of {$maxDepth} levels.");
        }
    }

    /**
     * Check for duplicate files.
     */
    private function validateDuplicates($validator): void
    {
        if (!$this->hasFile('image') || !config('cms.images.prevent_duplicates', true)) {
            return;
        }

        $file = $this->file('image');
        $hash = hash_file('md5', $file->getPathname());

        $existingImage = \Webook\LaravelCMS\Models\Image::where('file_hash', $hash)->first();
        if ($existingImage) {
            $validator->errors()->add('image', 'This image has already been uploaded.');
        }
    }

    /**
     * Check for malicious SVG content.
     */
    private function containsMaliciousSvgContent(string $content): bool
    {
        $maliciousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
            '/<foreignObject\b/i',
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Estimate JPEG quality.
     */
    private function estimateJpegQuality(string $imagePath): int
    {
        // This is a simplified quality estimation
        // In production, you might want to use a more sophisticated method
        $fileSize = filesize($imagePath);
        $imageInfo = getimagesize($imagePath);

        if ($imageInfo === false) {
            return 0;
        }

        [$width, $height] = $imageInfo;
        $pixels = $width * $height;

        // Rough estimation based on file size vs pixel count
        $bytesPerPixel = $fileSize / $pixels;

        if ($bytesPerPixel > 3) return 95;
        if ($bytesPerPixel > 2) return 85;
        if ($bytesPerPixel > 1.5) return 75;
        if ($bytesPerPixel > 1) return 65;
        if ($bytesPerPixel > 0.5) return 55;

        return 45;
    }

    /**
     * Calculate user's storage usage.
     */
    private function calculateUserStorageUsage(int $userId): int
    {
        return \Webook\LaravelCMS\Models\Image::where('uploaded_by', $userId)
            ->sum('size') ?? 0;
    }

    /**
     * Get validated data with upload tracking.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Update upload counters
        $userId = $this->user()->id;
        $today = date('Y-m-d');
        $currentHour = date('Y-m-d-H');

        $dailyKey = "daily_uploads:{$userId}:{$today}";
        $hourlyKey = "hourly_uploads:{$userId}:{$currentHour}";

        cache()->increment($dailyKey, 1);
        cache()->put($dailyKey, cache()->get($dailyKey), now()->endOfDay());

        cache()->increment($hourlyKey, 1);
        cache()->put($hourlyKey, cache()->get($hourlyKey), now()->endOfHour());

        return $validated;
    }
}