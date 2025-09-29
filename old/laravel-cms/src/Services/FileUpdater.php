<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Webook\LaravelCMS\Contracts\UpdateStrategyInterface;
use Webook\LaravelCMS\Services\UpdateStrategies\TextStrategy;
use Webook\LaravelCMS\Services\UpdateStrategies\DOMStrategy;
use Webook\LaravelCMS\Services\UpdateStrategies\BladeStrategy;
use Webook\LaravelCMS\Services\CmsLogger;
use Exception;
use InvalidArgumentException;

/**
 * File Updater Service
 *
 * Secure service for safely modifying Blade templates and other files.
 * Provides atomic operations, backup management, and comprehensive
 * validation to prevent file corruption.
 */
class FileUpdater
{
    protected Filesystem $files;
    protected CacheRepository $cache;
    protected array $config;
    protected array $strategies = [];
    protected array $fileLocks = [];
    protected array $backupIndex = [];

    public function __construct(Filesystem $files, CacheRepository $cache, array $config = [])
    {
        $this->files = $files;
        $this->cache = $cache;
        $this->config = array_merge($this->getDefaultConfig(), $config);

        $this->initializeStrategies();
        $this->loadBackupIndex();
    }

    /**
     * Update content in a file safely.
     */
    public function updateContent(string $file, string $old, string $new, array $context = []): bool
    {
        CmsLogger::info('FileUpdater::updateContent called', [
            'file' => $file,
            'old_length' => strlen($old),
            'new_length' => strlen($new),
            'old_preview' => substr($old, 0, 100),
            'new_preview' => substr($new, 0, 100),
            'context_keys' => array_keys($context),
        ]);

        try {
            $this->validateFilePath($file);
            CmsLogger::info('File validation passed', ['file' => $file]);
        } catch (Exception $e) {
            CmsLogger::error('File validation failed', [
                'file' => $file,
                'error' => $e->getMessage(),
                'allowed_dirs' => $this->config['allowed_directories'] ?? [],
            ]);
            throw $e;
        }

        return $this->executeWithTransaction($file, function () use ($file, $old, $new, $context) {
            $content = $this->files->get($file);
            $originalLength = strlen($content);

            CmsLogger::info('File content loaded', [
                'file' => $file,
                'content_length' => $originalLength,
                'contains_old' => strpos($content, $old) !== false,
                'old_position' => strpos($content, $old),
            ]);

            $strategy = $this->selectStrategy($content, $context);

            CmsLogger::info('Strategy selected', [
                'file' => $file,
                'strategy' => $strategy->getName(),
                'file_exists' => $this->files->exists($file),
                'file_writable' => $this->files->isWritable($file),
            ]);

            $updatedContent = $strategy->updateContent($content, $old, $new, $context);

            CmsLogger::info('Content processed by strategy', [
                'original_length' => $originalLength,
                'updated_length' => strlen($updatedContent),
                'content_changed' => $content !== $updatedContent,
                'contains_new' => strpos($updatedContent, $new) !== false,
            ]);

            $this->validateAndSave($file, $updatedContent, $strategy, $context);

            CmsLogger::info('File saved successfully', [
                'file' => $file,
                'final_size' => $this->files->size($file),
            ]);

            return true;
        });
    }

    /**
     * Update content by line number.
     */
    public function updateByLineNumber(string $file, int $line, string $new): bool
    {
        $this->validateFilePath($file);

        return $this->executeWithTransaction($file, function () use ($file, $line, $new) {
            $content = $this->files->get($file);
            $strategy = $this->selectStrategy($content);

            $updatedContent = $strategy->updateByLineNumber($content, $line, $new);
            $this->validateAndSave($file, $updatedContent, $strategy);

            return true;
        });
    }

    /**
     * Update content by selector.
     */
    public function updateBySelector(string $file, string $selector, string $new): bool
    {
        $this->validateFilePath($file);

        return $this->executeWithTransaction($file, function () use ($file, $selector, $new) {
            $content = $this->files->get($file);
            $strategy = $this->selectStrategy($content, ['selector' => $selector]);

            $updatedContent = $strategy->updateBySelector($content, $selector, $new);
            $this->validateAndSave($file, $updatedContent, $strategy);

            return true;
        });
    }

    /**
     * Update element attribute.
     */
    public function updateAttribute(string $file, string $selector, string $attr, string $value): bool
    {
        $this->validateFilePath($file);

        return $this->executeWithTransaction($file, function () use ($file, $selector, $attr, $value) {
            $content = $this->files->get($file);
            $strategy = $this->selectStrategy($content, ['selector' => $selector]);

            $updatedContent = $strategy->updateAttribute($content, $selector, $attr, $value);
            $this->validateAndSave($file, $updatedContent, $strategy);

            return true;
        });
    }

    /**
     * Perform batch updates on a file.
     */
    public function batchUpdate(string $file, array $updates): bool
    {
        $this->validateFilePath($file);

        return $this->executeWithTransaction($file, function () use ($file, $updates) {
            $content = $this->files->get($file);
            $strategy = $this->selectStrategy($content);

            foreach ($updates as $update) {
                $content = $this->applyUpdate($content, $update, $strategy);
            }

            $this->validateAndSave($file, $content, $strategy);
            return true;
        });
    }

    /**
     * Create a backup of a file.
     */
    public function createBackup(string $file): string
    {
        $this->validateFilePath($file);

        $backupId = $this->generateBackupId($file);
        $backupPath = $this->getBackupPath($backupId);

        $backupDir = dirname($backupPath);
        if (!$this->files->exists($backupDir)) {
            $this->files->makeDirectory($backupDir, 0755, true);
        }

        $this->files->copy($file, $backupPath);

        $this->backupIndex[$backupId] = [
            'original_file' => $file,
            'backup_path' => $backupPath,
            'created_at' => now()->toISOString(),
            'size' => $this->files->size($file),
            'checksum' => md5_file($file),
        ];

        $this->saveBackupIndex();

        return $backupId;
    }

    /**
     * Restore a file from backup.
     */
    public function restore(string $file, string $backup): bool
    {
        $this->validateFilePath($file);

        if (!isset($this->backupIndex[$backup])) {
            throw new InvalidArgumentException("Backup not found: {$backup}");
        }

        $backupInfo = $this->backupIndex[$backup];
        $backupPath = $backupInfo['backup_path'];

        if (!$this->files->exists($backupPath)) {
            throw new InvalidArgumentException("Backup file missing: {$backupPath}");
        }

        if (md5_file($backupPath) !== $backupInfo['checksum']) {
            throw new Exception("Backup file corrupted: {$backup}");
        }

        return $this->executeWithTransaction($file, function () use ($file, $backupPath) {
            $this->files->copy($backupPath, $file);
            return true;
        });
    }

    /**
     * Get diff between file and backup.
     */
    public function diff(string $file, string $backup): array
    {
        $this->validateFilePath($file);

        if (!isset($this->backupIndex[$backup])) {
            throw new InvalidArgumentException("Backup not found: {$backup}");
        }

        $backupPath = $this->backupIndex[$backup]['backup_path'];

        if (!$this->files->exists($backupPath)) {
            throw new InvalidArgumentException("Backup file missing: {$backupPath}");
        }

        $currentContent = $this->files->get($file);
        $backupContent = $this->files->get($backupPath);

        return $this->generateDiff($backupContent, $currentContent);
    }

    /**
     * Get file modification history.
     */
    public function history(string $file): array
    {
        $this->validateFilePath($file);

        $history = [];

        foreach ($this->backupIndex as $backupId => $info) {
            if ($info['original_file'] === $file) {
                $history[] = [
                    'backup_id' => $backupId,
                    'created_at' => $info['created_at'],
                    'size' => $info['size'],
                    'checksum' => $info['checksum'],
                ];
            }
        }

        usort($history, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $history;
    }

    /**
     * Lock a file for exclusive access.
     */
    public function lock(string $file): bool
    {
        $lockId = md5($file);

        if (isset($this->fileLocks[$lockId])) {
            return false;
        }

        $lockFile = $this->getLockPath($file);
        $lockDir = dirname($lockFile);

        if (!$this->files->exists($lockDir)) {
            $this->files->makeDirectory($lockDir, 0755, true);
        }

        $lockData = [
            'file' => $file,
            'locked_at' => now()->toISOString(),
            'process_id' => getmypid(),
        ];

        $this->files->put($lockFile, json_encode($lockData));
        $this->fileLocks[$lockId] = $lockFile;

        return true;
    }

    /**
     * Unlock a file.
     */
    public function unlock(string $file): bool
    {
        $lockId = md5($file);

        if (!isset($this->fileLocks[$lockId])) {
            return true;
        }

        $lockFile = $this->fileLocks[$lockId];

        if ($this->files->exists($lockFile)) {
            $this->files->delete($lockFile);
        }

        unset($this->fileLocks[$lockId]);
        return true;
    }

    /**
     * Initialize update strategies.
     */
    protected function initializeStrategies(): void
    {
        $this->strategies = [
            new BladeStrategy($this->config['blade_strategy'] ?? []),
            new DOMStrategy($this->config['dom_strategy'] ?? []),
            new TextStrategy($this->config['text_strategy'] ?? []),
        ];

        usort($this->strategies, function (UpdateStrategyInterface $a, UpdateStrategyInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * Select appropriate update strategy.
     */
    protected function selectStrategy(string $content, array $context = []): UpdateStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->canHandle($content, $context)) {
                return $strategy;
            }
        }

        return new TextStrategy();
    }

    /**
     * Execute operation within a transaction.
     */
    protected function executeWithTransaction(string $file, callable $operation)
    {
        if (!$this->lock($file)) {
            throw new Exception("Cannot acquire lock for file: {$file}");
        }

        $backupId = null;
        if ($this->config['auto_backup']) {
            $backupId = $this->createBackup($file);
        }

        try {
            $result = $operation();
            $this->unlock($file);

            if ($this->config['git_commit'] && $result) {
                $this->commitToGit($file);
            }

            return $result;

        } catch (Exception $e) {
            if ($backupId && $this->config['rollback_on_failure']) {
                try {
                    $this->restore($file, $backupId);
                } catch (Exception $rollbackError) {
                    Log::error('FileUpdater: Rollback failed', [
                        'file' => $file,
                        'backup_id' => $backupId,
                        'error' => $rollbackError->getMessage(),
                    ]);
                }
            }

            $this->unlock($file);
            throw $e;
        }
    }

    /**
     * Validate and save file content.
     */
    protected function validateAndSave(string $file, string $content, UpdateStrategyInterface $strategy, array $context = []): void
    {
        $validation = $strategy->validate($content, $context);

        if (!$validation['valid']) {
            throw new Exception('Content validation failed: ' . implode(', ', $validation['errors']));
        }

        if (!empty($validation['warnings'])) {
            Log::warning('FileUpdater: Content validation warnings', [
                'file' => $file,
                'warnings' => $validation['warnings'],
            ]);
        }

        $this->atomicWrite($file, $content);
    }

    /**
     * Perform atomic write operation.
     */
    protected function atomicWrite(string $file, string $content): void
    {
        $tempFile = $file . '.tmp.' . uniqid();

        CmsLogger::info('atomicWrite starting', [
            'file' => $file,
            'temp_file' => $tempFile,
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200),
        ]);

        try {
            $bytesWritten = $this->files->put($tempFile, $content);

            CmsLogger::info('Temp file written', [
                'temp_file' => $tempFile,
                'bytes_written' => $bytesWritten,
                'success' => $bytesWritten !== false,
                'temp_exists' => $this->files->exists($tempFile),
                'temp_size' => $this->files->exists($tempFile) ? $this->files->size($tempFile) : 0,
            ]);

            // Verify content was written correctly
            $verifyContent = $this->files->get($tempFile);
            if ($verifyContent !== $content) {
                CmsLogger::error('Atomic write verification failed', [
                    'expected_length' => strlen($content),
                    'actual_length' => strlen($verifyContent),
                    'match' => $verifyContent === $content,
                ]);
                throw new Exception('Atomic write verification failed');
            }

            $this->files->move($tempFile, $file);

            // Verify final file
            $finalContent = $this->files->get($file);
            CmsLogger::info('File moved to final location', [
                'from' => $tempFile,
                'to' => $file,
                'final_file_exists' => $this->files->exists($file),
                'final_file_size' => $this->files->size($file),
                'content_matches' => $finalContent === $content,
                'final_preview' => substr($finalContent, 0, 200),
            ]);

        } catch (Exception $e) {
            CmsLogger::exception('atomicWrite', $e);

            if ($this->files->exists($tempFile)) {
                $this->files->delete($tempFile);
            }
            throw $e;
        }
    }

    /**
     * Apply single update operation.
     */
    protected function applyUpdate(string $content, array $update, UpdateStrategyInterface $strategy): string
    {
        $type = $update['type'] ?? 'content';

        return match ($type) {
            'content' => $strategy->updateContent($content, $update['old'], $update['new'], $update['context'] ?? []),
            'line' => $strategy->updateByLineNumber($content, $update['line'], $update['new'], $update['context'] ?? []),
            'selector' => $strategy->updateBySelector($content, $update['selector'], $update['new'], $update['context'] ?? []),
            'attribute' => $strategy->updateAttribute($content, $update['selector'], $update['attribute'], $update['value'], $update['context'] ?? []),
            default => throw new InvalidArgumentException("Unknown update type: {$type}")
        };
    }

    /**
     * Validate file path.
     */
    protected function validateFilePath(string $file): void
    {
        if (!$this->files->exists($file)) {
            throw new InvalidArgumentException("File not found: {$file}");
        }

        if (!$this->files->isReadable($file)) {
            throw new InvalidArgumentException("File not readable: {$file}");
        }

        if (!$this->files->isWritable($file)) {
            throw new InvalidArgumentException("File not writable: {$file}");
        }

        $realPath = realpath($file);
        $allowed = false;

        foreach ($this->config['allowed_directories'] as $dir) {
            if (str_starts_with($realPath, realpath($dir))) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new InvalidArgumentException("File outside allowed directories: {$file}");
        }
    }

    /**
     * Generate diff between two content strings.
     */
    protected function generateDiff(string $old, string $new): array
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);

        $diff = [
            'added' => [],
            'removed' => [],
            'modified' => [],
            'unchanged' => [],
        ];

        $maxLines = max(count($oldLines), count($newLines));

        for ($i = 0; $i < $maxLines; $i++) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$i] ?? null;

            if ($oldLine === null) {
                $diff['added'][] = ['line' => $i + 1, 'content' => $newLine];
            } elseif ($newLine === null) {
                $diff['removed'][] = ['line' => $i + 1, 'content' => $oldLine];
            } elseif ($oldLine !== $newLine) {
                $diff['modified'][] = [
                    'line' => $i + 1,
                    'old' => $oldLine,
                    'new' => $newLine,
                ];
            } else {
                $diff['unchanged'][] = ['line' => $i + 1, 'content' => $oldLine];
            }
        }

        return $diff;
    }

    /**
     * Get default configuration.
     */
    protected function getDefaultConfig(): array
    {
        return [
            'auto_backup' => true,
            'rollback_on_failure' => true,
            'git_commit' => false,
            'validate_blade_compilation' => true,
            'allowed_directories' => [
                resource_path('views'),
                base_path('resources'),
            ],
            'backup_directory' => storage_path('cms/file-backups'),
            'lock_directory' => storage_path('cms/file-locks'),
            'max_backup_age_days' => 30,
            'cleanup_backups' => true,
        ];
    }

    /**
     * Generate backup ID.
     */
    protected function generateBackupId(string $file): string
    {
        return 'backup_' . md5($file) . '_' . date('Y-m-d_H-i-s') . '_' . uniqid();
    }

    /**
     * Get backup file path.
     */
    protected function getBackupPath(string $backupId): string
    {
        return $this->config['backup_directory'] . '/' . $backupId . '.backup';
    }

    /**
     * Get lock file path.
     */
    protected function getLockPath(string $file): string
    {
        return $this->config['lock_directory'] . '/' . md5($file) . '.lock';
    }

    /**
     * Load backup index from storage.
     */
    protected function loadBackupIndex(): void
    {
        $indexFile = $this->config['backup_directory'] . '/index.json';

        if ($this->files->exists($indexFile)) {
            $this->backupIndex = json_decode($this->files->get($indexFile), true) ?? [];
        }
    }

    /**
     * Save backup index to storage.
     */
    protected function saveBackupIndex(): void
    {
        $indexFile = $this->config['backup_directory'] . '/index.json';
        $indexDir = dirname($indexFile);

        if (!$this->files->exists($indexDir)) {
            $this->files->makeDirectory($indexDir, 0755, true);
        }

        $this->files->put($indexFile, json_encode($this->backupIndex, JSON_PRETTY_PRINT));
    }

    /**
     * Commit changes to git.
     */
    protected function commitToGit(string $file): void
    {
        if (!$this->config['git_commit']) {
            return;
        }

        try {
            $gitConfig = $this->config['git'] ?? [];
            $author = $gitConfig['author'] ?? 'FileUpdater';
            $email = $gitConfig['email'] ?? 'fileupdater@example.com';

            $commands = [
                "git add " . escapeshellarg($file),
                "git -c user.name='{$author}' -c user.email='{$email}' commit -m 'FileUpdater: Update " . basename($file) . "'",
            ];

            foreach ($commands as $command) {
                exec($command, $output, $returnCode);
                if ($returnCode !== 0) {
                    Log::warning('FileUpdater: Git command failed', [
                        'command' => $command,
                        'return_code' => $returnCode,
                        'output' => $output,
                    ]);
                }
            }

        } catch (Exception $e) {
            Log::error('FileUpdater: Git commit failed', [
                'file' => $file,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Legacy compatibility methods

    /**
     * Legacy method for backward compatibility.
     */
    public function updateFile(string $filePath, string $content, array $options = []): bool
    {
        return $this->executeWithTransaction($filePath, function () use ($filePath, $content) {
            $this->atomicWrite($filePath, $content);
            return true;
        });
    }

    /**
     * Legacy method for updating CMS regions.
     */
    public function updateRegion(string $filePath, string $regionId, string $content): bool
    {
        $pattern = sprintf(
            '/@cmseditable\s*\([\'"]%s[\'"] \)(.*?)@endcmseditable/s',
            preg_quote($regionId, '/')
        );

        $replacement = sprintf('@cmseditable(\'%s\')%s@endcmseditable', $regionId, $content);

        return $this->updateContent($filePath, $pattern, $replacement, ['regex' => true]);
    }
}