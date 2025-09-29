<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Webook\LaravelCMS\Contracts\AssetManagerInterface;

class AssetManager implements AssetManagerInterface
{
    protected $config;
    protected $cache;
    protected $files;

    public function __construct(array $config, CacheRepository $cache, Filesystem $files)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->files = $files;
    }

    public function renderAssets(array $options = []): string
    {
        $assets = [];
        $assets[] = '<link rel="stylesheet" href="' . $this->getAssetUrl('css/cms.css') . '">';
        $assets[] = '<script src="' . $this->getAssetUrl('js/cms.js') . '"></script>';
        
        return implode("\n", $assets);
    }

    public function getAssetUrl(string $asset): string
    {
        $baseUrl = $this->config['cdn']['enabled'] ? $this->config['cdn']['url'] : '/vendor/cms';
        return $baseUrl . '/' . $asset;
    }

    public function minifyAssets(): bool
    {
        return $this->config['minification']['enabled'] ?? false;
    }
}