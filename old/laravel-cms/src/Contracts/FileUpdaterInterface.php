<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * File Updater Interface
 *
 * Defines the contract for updating content files
 * with version control and backup support.
 */
interface FileUpdaterInterface
{
    /**
     * Update content in a file.
     *
     * @param string $filePath
     * @param string $content
     * @param array $options
     * @return bool
     */
    public function updateFile(string $filePath, string $content, array $options = []): bool;

    /**
     * Update specific content region in a file.
     *
     * @param string $filePath
     * @param string $regionId
     * @param string $content
     * @return bool
     */
    public function updateRegion(string $filePath, string $regionId, string $content): bool;

    /**
     * Create a backup before updating.
     *
     * @param string $filePath
     * @return string|null
     */
    public function createBackup(string $filePath): ?string;
}