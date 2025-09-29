<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class CMSLogger
{
    protected $logPath;
    protected $enabled;

    public function __construct()
    {
        $this->logPath = storage_path('logs/cms.log');
        $this->enabled = config('cms.logging.enabled', true);
    }

    /**
     * Log an info message
     */
    public function info($message, array $context = [])
    {
        if (!$this->enabled) return;

        $this->log('INFO', $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning($message, array $context = [])
    {
        if (!$this->enabled) return;

        $this->log('WARNING', $message, $context);
    }

    /**
     * Log an error message
     */
    public function error($message, array $context = [])
    {
        if (!$this->enabled) return;

        $this->log('ERROR', $message, $context);
    }

    /**
     * Log a debug message
     */
    public function debug($message, array $context = [])
    {
        if (!$this->enabled || !config('cms.logging.debug', false)) return;

        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log content change
     */
    public function logContentChange($file, $elementId, $oldContent, $newContent, $user = null)
    {
        if (!$this->enabled) return;

        // Handle arrays (for image data)
        if (is_array($oldContent)) {
            $oldContent = json_encode($oldContent);
        }
        if (is_array($newContent)) {
            $newContent = json_encode($newContent);
        }

        $this->log('CONTENT', 'Content updated', [
            'file' => $file,
            'element_id' => $elementId,
            'old_content' => substr((string)$oldContent, 0, 100),
            'new_content' => substr((string)$newContent, 0, 100),
            'user' => $user ?: 'anonymous',
            'ip' => request()->ip()
        ]);
    }

    /**
     * Log file backup
     */
    public function logBackup($originalFile, $backupFile)
    {
        if (!$this->enabled) return;

        $this->log('BACKUP', 'File backed up', [
            'original' => $originalFile,
            'backup' => $backupFile,
            'size' => File::exists($originalFile) ? File::size($originalFile) : 0
        ]);
    }

    /**
     * Core logging method
     */
    protected function log($level, $message, array $context = [])
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context) : '';

        $logEntry = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $message,
            $contextJson
        );

        // Ensure log directory exists
        $logDir = dirname($this->logPath);
        if (!File::exists($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        // Write to log file
        File::append($this->logPath, $logEntry);

        // Also log to Laravel's logger for integration
        Log::channel('single')->info($message, array_merge(['cms_level' => $level], $context));
    }

    /**
     * Get recent log entries
     */
    public function getRecentLogs($lines = 100)
    {
        if (!File::exists($this->logPath)) {
            return [];
        }

        $content = File::get($this->logPath);
        $lines = explode("\n", $content);

        return array_slice($lines, -$lines);
    }
}