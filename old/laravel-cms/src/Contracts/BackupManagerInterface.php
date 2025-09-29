<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * Backup Manager Interface
 *
 * Defines the contract for managing content backups and restoration.
 */
interface BackupManagerInterface
{
    /**
     * Create a backup of specified files or directories.
     *
     * @param array|string $paths
     * @param array $options
     * @return string Backup identifier
     */
    public function create($paths, array $options = []): string;

    /**
     * Restore from a specific backup.
     *
     * @param string $backupId
     * @param array $options
     * @return bool
     */
    public function restore(string $backupId, array $options = []): bool;

    /**
     * Backup a single file.
     *
     * @param string $filePath
     * @return string|null
     */
    public function backupFile(string $filePath): ?string;

    /**
     * List available backups.
     *
     * @return array
     */
    public function list(): array;
}