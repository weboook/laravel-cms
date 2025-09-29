<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * Asset Manager Interface
 */
interface AssetManagerInterface
{
    public function renderAssets(array $options = []): string;
    public function getAssetUrl(string $asset): string;
    public function minifyAssets(): bool;
}