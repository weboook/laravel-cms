<?php

namespace Webook\LaravelCMS;

use Webook\LaravelCMS\Services\ContentScanner;
use Webook\LaravelCMS\Services\TranslationManager;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\BackupManager;
use Webook\LaravelCMS\Services\AssetManager;
use Webook\LaravelCMS\Services\PermissionManager;

/**
 * Main CMS Service
 *
 * Coordinates all CMS functionality and provides a unified interface.
 */
class CMS
{
    protected $contentScanner;
    protected $translationManager;
    protected $fileUpdater;
    protected $backupManager;
    protected $assetManager;
    protected $permissionManager;

    public function __construct(
        ContentScanner $contentScanner,
        TranslationManager $translationManager,
        FileUpdater $fileUpdater,
        BackupManager $backupManager,
        AssetManager $assetManager,
        PermissionManager $permissionManager
    ) {
        $this->contentScanner = $contentScanner;
        $this->translationManager = $translationManager;
        $this->fileUpdater = $fileUpdater;
        $this->backupManager = $backupManager;
        $this->assetManager = $assetManager;
        $this->permissionManager = $permissionManager;
    }

    public function renderEditableContent(string $id, string $content = ''): string
    {
        if (!$this->permissionManager->check('cms.edit')) {
            return $content;
        }

        return sprintf(
            '<div class="cms-editable" data-cms-id="%s" data-cms-editable="true">%s</div>',
            htmlspecialchars($id),
            $content
        );
    }

    public function closeEditableContent(): string
    {
        return '';
    }

    public function renderToolbar(array $options = []): string
    {
        if (!$this->permissionManager->check()) {
            return '';
        }

        return '<div class="cms-toolbar"><!-- CMS Toolbar --></div>';
    }

    public function renderAssets(array $options = []): string
    {
        return $this->assetManager->renderAssets($options);
    }

    public function renderConfig(array $options = []): string
    {
        $config = [
            'enabled' => true,
            'endpoints' => [
                'save' => route('cms.api.save'),
                'upload' => route('cms.api.upload'),
            ],
        ];

        return '<script>window.CMS = ' . json_encode($config) . ';</script>';
    }
}