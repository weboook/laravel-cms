<?php

namespace Webook\LaravelCMS\Contracts;

use Illuminate\Http\UploadedFile;
use Webook\LaravelCMS\Models\Asset;
use Webook\LaravelCMS\Models\AssetFolder;

interface MediaAssetManagerInterface
{
    public function upload(UploadedFile $file, array $options = []): Asset;
    public function uploadFromUrl(string $url, array $options = []): Asset;
    public function uploadFromBase64(string $base64, string $filename, array $options = []): Asset;
    public function uploadChunk(string $uploadId, UploadedFile $chunk, int $chunkNumber, int $totalChunks, array $metadata = []): array;
    public function assembleChunks(string $uploadId): Asset;
    public function processAsset(Asset $asset): bool;
    public function generateThumbnails(Asset $asset, array $sizes = []): array;
    public function optimizeImage(Asset $asset): bool;
    public function moveAsset(Asset $asset, AssetFolder $folder = null): bool;
    public function duplicateAsset(Asset $asset, array $options = []): Asset;
    public function search(array $criteria): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function getFolder(int $folderId = null): ?AssetFolder;
    public function createFolder(string $name, AssetFolder $parent = null, array $options = []): AssetFolder;
    public function deleteFolder(AssetFolder $folder, bool $moveAssets = true): bool;
    public function batchMove(array $assetIds, AssetFolder $folder = null): array;
    public function batchDelete(array $assetIds): array;
    public function cleanup(): int;
}