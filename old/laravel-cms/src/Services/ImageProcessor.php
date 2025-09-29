<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Intervention\Image\Image as ImageInstance;
use Webook\LaravelCMS\Models\Asset;

class ImageProcessor
{
    protected $config;
    protected $quality;
    protected $formats;

    public function __construct()
    {
        $this->config = config('cms-assets.image_processing', []);
        $this->quality = $this->config['quality'] ?? 85;
        $this->formats = $this->config['output_formats'] ?? ['jpg', 'webp'];
    }

    public function resize(Asset $asset, int $width, int $height = null, bool $maintainAspectRatio = true): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);

        if ($maintainAspectRatio) {
            $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        } else {
            $image->resize($width, $height);
        }

        return $this->saveProcessedImage($asset, $image, "resized_{$width}x{$height}");
    }

    public function crop(Asset $asset, int $width, int $height, int $x = null, int $y = null): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);

        if ($x !== null && $y !== null) {
            $image->crop($width, $height, $x, $y);
        } else {
            // Center crop
            $image->fit($width, $height);
        }

        return $this->saveProcessedImage($asset, $image, "cropped_{$width}x{$height}");
    }

    public function createThumbnail(Asset $asset, int $width, int $height = null, string $method = 'fit'): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);
        $height = $height ?: $width;

        switch ($method) {
            case 'fit':
                $image->fit($width, $height, function ($constraint) {
                    $constraint->upsize();
                });
                break;
            case 'resize':
                $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                break;
            case 'crop':
                $image->crop($width, $height);
                break;
            default:
                throw new \InvalidArgumentException("Unknown thumbnail method: {$method}");
        }

        return $this->saveProcessedImage($asset, $image, "thumb_{$width}x{$height}");
    }

    public function generateResponsiveImages(Asset $asset, array $sizes = []): array
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $sizes = $sizes ?: [
            'small' => [480, null],
            'medium' => [768, null],
            'large' => [1024, null],
            'xlarge' => [1440, null],
        ];

        $responsiveImages = [];

        foreach ($sizes as $size => $dimensions) {
            [$width, $height] = $dimensions;

            try {
                $path = $this->resize($asset, $width, $height);
                $responsiveImages[$size] = [
                    'path' => $path,
                    'width' => $width,
                    'height' => $height,
                    'url' => Storage::disk($asset->disk)->url($path),
                ];
            } catch (\Exception $e) {
                // Log error but continue with other sizes
                logger()->error("Failed to create responsive image {$size} for asset {$asset->id}: " . $e->getMessage());
            }
        }

        // Update asset with responsive images data
        $metadata = $asset->metadata ?? [];
        $metadata['responsive_images'] = $responsiveImages;
        $asset->update(['responsive_images' => $responsiveImages, 'metadata' => $metadata]);

        return $responsiveImages;
    }

    public function convertFormat(Asset $asset, string $format, int $quality = null): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $supportedFormats = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array(strtolower($format), $supportedFormats)) {
            throw new \InvalidArgumentException("Unsupported format: {$format}");
        }

        $image = $this->loadImage($asset);
        $quality = $quality ?: $this->quality;

        // Apply format-specific optimizations
        switch (strtolower($format)) {
            case 'jpg':
            case 'jpeg':
                $image->encode('jpg', $quality);
                break;
            case 'png':
                // PNG doesn't use quality, but we can optimize
                $image->encode('png');
                break;
            case 'webp':
                $image->encode('webp', $quality);
                break;
            case 'gif':
                $image->encode('gif');
                break;
        }

        return $this->saveProcessedImage($asset, $image, "converted_{$format}", $format);
    }

    public function optimize(Asset $asset, array $options = []): bool
    {
        if ($asset->type !== 'image') {
            return false;
        }

        try {
            $image = $this->loadImage($asset);
            $originalSize = $asset->size;

            // Apply optimization based on format
            $extension = strtolower($asset->extension);
            $quality = $options['quality'] ?? $this->quality;

            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image->encode('jpg', $quality);
                    break;
                case 'png':
                    // For PNG, we can apply some optimizations
                    $image->encode('png');
                    break;
                case 'webp':
                    $image->encode('webp', $quality);
                    break;
                default:
                    // For other formats, just re-encode
                    $image->encode($extension);
            }

            // Save optimized version
            Storage::disk($asset->disk)->put($asset->path, $image->encoded);

            // Update asset size
            $newSize = Storage::disk($asset->disk)->size($asset->path);
            $asset->update(['size' => $newSize]);

            // Calculate compression ratio
            $compressionRatio = $originalSize > 0 ? (($originalSize - $newSize) / $originalSize) * 100 : 0;

            // Update metadata
            $metadata = $asset->metadata ?? [];
            $metadata['optimization'] = [
                'original_size' => $originalSize,
                'optimized_size' => $newSize,
                'compression_ratio' => round($compressionRatio, 2),
                'optimized_at' => now()->toISOString(),
            ];
            $asset->update(['metadata' => $metadata]);

            return true;
        } catch (\Exception $e) {
            logger()->error("Failed to optimize image for asset {$asset->id}: " . $e->getMessage());
            return false;
        }
    }

    public function addWatermark(Asset $asset, string $watermarkPath, array $options = []): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);

        // Load watermark
        $watermark = Image::make($watermarkPath);

        // Configure watermark
        $position = $options['position'] ?? 'bottom-right';
        $opacity = $options['opacity'] ?? 50;
        $margin = $options['margin'] ?? 10;

        // Apply opacity
        $watermark->opacity($opacity);

        // Position watermark
        switch ($position) {
            case 'top-left':
                $image->insert($watermark, 'top-left', $margin, $margin);
                break;
            case 'top-right':
                $image->insert($watermark, 'top-right', $margin, $margin);
                break;
            case 'bottom-left':
                $image->insert($watermark, 'bottom-left', $margin, $margin);
                break;
            case 'bottom-right':
                $image->insert($watermark, 'bottom-right', $margin, $margin);
                break;
            case 'center':
                $image->insert($watermark, 'center');
                break;
            default:
                $image->insert($watermark, 'bottom-right', $margin, $margin);
        }

        return $this->saveProcessedImage($asset, $image, 'watermarked');
    }

    public function blur(Asset $asset, int $amount = 5): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);
        $image->blur($amount);

        return $this->saveProcessedImage($asset, $image, "blurred_{$amount}");
    }

    public function sharpen(Asset $asset, int $amount = 10): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);
        $image->sharpen($amount);

        return $this->saveProcessedImage($asset, $image, "sharpened_{$amount}");
    }

    public function adjustBrightness(Asset $asset, int $level = 0): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);
        $image->brightness($level);

        return $this->saveProcessedImage($asset, $image, "brightness_{$level}");
    }

    public function adjustContrast(Asset $asset, int $level = 0): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);
        $image->contrast($level);

        return $this->saveProcessedImage($asset, $image, "contrast_{$level}");
    }

    public function grayscale(Asset $asset): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);
        $image->greyscale();

        return $this->saveProcessedImage($asset, $image, 'grayscale');
    }

    public function rotate(Asset $asset, float $angle, string $backgroundColor = '#ffffff'): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);
        $image->rotate($angle, $backgroundColor);

        return $this->saveProcessedImage($asset, $image, "rotated_{$angle}");
    }

    public function flip(Asset $asset, string $direction = 'h'): string
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);

        if ($direction === 'h' || $direction === 'horizontal') {
            $image->flip('h');
            $suffix = 'flipped_h';
        } elseif ($direction === 'v' || $direction === 'vertical') {
            $image->flip('v');
            $suffix = 'flipped_v';
        } else {
            throw new \InvalidArgumentException("Invalid flip direction: {$direction}");
        }

        return $this->saveProcessedImage($asset, $image, $suffix);
    }

    public function createWebPVersion(Asset $asset, int $quality = null): ?string
    {
        if ($asset->type !== 'image') {
            return null;
        }

        try {
            return $this->convertFormat($asset, 'webp', $quality);
        } catch (\Exception $e) {
            logger()->error("Failed to create WebP version for asset {$asset->id}: " . $e->getMessage());
            return null;
        }
    }

    public function batchProcess(array $assetIds, string $operation, array $parameters = []): array
    {
        $results = [];
        $assets = Asset::whereIn('id', $assetIds)->where('type', 'image')->get();

        foreach ($assets as $asset) {
            try {
                $result = $this->processOperation($asset, $operation, $parameters);
                $results[] = [
                    'asset_id' => $asset->id,
                    'success' => true,
                    'result' => $result,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'asset_id' => $asset->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getImageInfo(Asset $asset): array
    {
        if ($asset->type !== 'image') {
            throw new \InvalidArgumentException('Asset must be an image');
        }

        $image = $this->loadImage($asset);

        return [
            'width' => $image->width(),
            'height' => $image->height(),
            'mime_type' => $image->mime(),
            'file_size' => $asset->size,
            'aspect_ratio' => round($image->width() / $image->height(), 2),
            'format' => $asset->extension,
        ];
    }

    // Protected helper methods

    protected function loadImage(Asset $asset): ImageInstance
    {
        $path = Storage::disk($asset->disk)->path($asset->path);

        if (!file_exists($path)) {
            throw new \Exception("Image file not found: {$path}");
        }

        return Image::make($path);
    }

    protected function saveProcessedImage(Asset $asset, ImageInstance $image, string $suffix, string $format = null): string
    {
        $pathInfo = pathinfo($asset->path);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $format ?: $pathInfo['extension'];

        $newFilename = "{$filename}_{$suffix}.{$extension}";
        $newPath = "{$directory}/{$newFilename}";

        // Save the processed image
        Storage::disk($asset->disk)->put($newPath, $image->encoded);

        return $newPath;
    }

    protected function processOperation(Asset $asset, string $operation, array $parameters): string
    {
        switch ($operation) {
            case 'resize':
                return $this->resize($asset, $parameters['width'], $parameters['height'] ?? null);
            case 'crop':
                return $this->crop($asset, $parameters['width'], $parameters['height']);
            case 'thumbnail':
                return $this->createThumbnail($asset, $parameters['width'], $parameters['height'] ?? null);
            case 'optimize':
                $this->optimize($asset, $parameters);
                return $asset->path;
            case 'convert':
                return $this->convertFormat($asset, $parameters['format'], $parameters['quality'] ?? null);
            case 'watermark':
                return $this->addWatermark($asset, $parameters['watermark_path'], $parameters);
            case 'blur':
                return $this->blur($asset, $parameters['amount'] ?? 5);
            case 'sharpen':
                return $this->sharpen($asset, $parameters['amount'] ?? 10);
            case 'brightness':
                return $this->adjustBrightness($asset, $parameters['level'] ?? 0);
            case 'contrast':
                return $this->adjustContrast($asset, $parameters['level'] ?? 0);
            case 'grayscale':
                return $this->grayscale($asset);
            case 'rotate':
                return $this->rotate($asset, $parameters['angle'], $parameters['background'] ?? '#ffffff');
            case 'flip':
                return $this->flip($asset, $parameters['direction'] ?? 'h');
            case 'webp':
                return $this->createWebPVersion($asset, $parameters['quality'] ?? null);
            default:
                throw new \InvalidArgumentException("Unknown operation: {$operation}");
        }
    }
}