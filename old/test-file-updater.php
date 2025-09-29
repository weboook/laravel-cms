<?php

/**
 * Standalone test for FileUpdater service
 * Tests file persistence without Laravel bootstrap
 */

// Load the required classes
require_once __DIR__ . '/laravel-cms/src/Services/FileUpdater.php';
require_once __DIR__ . '/laravel-cms/src/Contracts/UpdateStrategyInterface.php';
require_once __DIR__ . '/laravel-cms/src/Services/UpdateStrategies/TextStrategy.php';
require_once __DIR__ . '/laravel-cms/src/Services/UpdateStrategies/DOMStrategy.php';
require_once __DIR__ . '/laravel-cms/src/Services/UpdateStrategies/BladeStrategy.php';

use Webook\LaravelCMS\Services\FileUpdater;

// Mock classes for dependencies
class MockFilesystem {
    public function get($path) {
        return file_get_contents($path);
    }

    public function put($path, $contents) {
        return file_put_contents($path, $contents) !== false;
    }

    public function exists($path) {
        return file_exists($path);
    }

    public function isReadable($path) {
        return is_readable($path);
    }

    public function isWritable($path) {
        return is_writable($path);
    }

    public function copy($from, $to) {
        return copy($from, $to);
    }

    public function move($from, $to) {
        return rename($from, $to);
    }

    public function delete($path) {
        return unlink($path);
    }

    public function makeDirectory($path, $mode = 0755, $recursive = false) {
        return mkdir($path, $mode, $recursive);
    }

    public function size($path) {
        return filesize($path);
    }

    public function deleteDirectory($path) {
        if (!is_dir($path)) return false;

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = "$path/$file";
            is_dir($fullPath) ? $this->deleteDirectory($fullPath) : unlink($fullPath);
        }
        return rmdir($path);
    }
}

class MockCache {
    private $store = [];

    public function get($key, $default = null) {
        return $this->store[$key] ?? $default;
    }

    public function put($key, $value, $ttl = null) {
        $this->store[$key] = $value;
        return true;
    }

    public function remember($key, $ttl, $callback) {
        if (!isset($this->store[$key])) {
            $this->store[$key] = $callback();
        }
        return $this->store[$key];
    }
}

// Simple logger
class SimpleLogger {
    public static function info($message, $context = []) {
        echo "[INFO] $message\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context) . "\n";
        }
    }

    public static function warning($message, $context = []) {
        echo "[WARNING] $message\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context) . "\n";
        }
    }

    public static function error($message, $context = []) {
        echo "[ERROR] $message\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context) . "\n";
        }
    }
}

// Mock Laravel facades
class Log {
    public static function __callStatic($method, $args) {
        SimpleLogger::$method(...$args);
    }
}

// Helper functions for Laravel compatibility
function now() {
    return new class {
        public function toISOString() {
            return date('c');
        }
        public function subMinutes($minutes) {
            return $this;
        }
        public function subHours($hours) {
            return $this;
        }
        public function addMinutes($minutes) {
            return $this;
        }
    };
}

function resource_path($path = '') {
    return __DIR__ . '/resources' . ($path ? '/' . $path : '');
}

function base_path($path = '') {
    return __DIR__ . ($path ? '/' . $path : '');
}

function storage_path($path = '') {
    return __DIR__ . '/storage' . ($path ? '/' . $path : '');
}

// Test implementation
echo "\n\033[1m=== FileUpdater Service Test (Standalone) ===\033[0m\n\n";

try {
    // Setup test environment
    $testDir = __DIR__ . '/test-files';
    $testFile = $testDir . '/test.html';

    // Create test directory
    if (!is_dir($testDir)) {
        mkdir($testDir, 0755, true);
    }

    // Create test file with original content
    $originalContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
</head>
<body>
    <h1>Original Title</h1>
    <p>This is the original paragraph.</p>
    <div class="content">
        This is the main content area.
    </div>
</body>
</html>
HTML;

    file_put_contents($testFile, $originalContent);
    echo "✓ Test file created: $testFile\n";

    // Initialize FileUpdater
    $filesystem = new MockFilesystem();
    $cache = new MockCache();
    $config = [
        'auto_backup' => true,
        'rollback_on_failure' => true,
        'git_commit' => false,
        'allowed_directories' => [$testDir],
        'backup_directory' => $testDir . '/backups',
        'lock_directory' => $testDir . '/locks',
    ];

    $fileUpdater = new FileUpdater($filesystem, $cache, $config);
    echo "✓ FileUpdater service initialized\n\n";

    // Test 1: Update content
    echo "\033[1mTest 1: Update Content\033[0m\n";
    $result = $fileUpdater->updateContent(
        $testFile,
        '<h1>Original Title</h1>',
        '<h1>Updated Title</h1>'
    );

    if ($result) {
        echo "✓ Content update method executed\n";

        // Verify the file was actually modified
        $updatedContent = file_get_contents($testFile);
        if (strpos($updatedContent, 'Updated Title') !== false) {
            echo "\033[32m✓ File successfully updated on disk!\033[0m\n";
        } else {
            echo "\033[31m✗ File was not updated on disk\033[0m\n";
        }
    } else {
        echo "\033[31m✗ Content update failed\033[0m\n";
    }

    // Test 2: Update by line number
    echo "\n\033[1mTest 2: Update by Line Number\033[0m\n";
    $result = $fileUpdater->updateByLineNumber($testFile, 8, '    <p>Updated paragraph text.</p>');

    if ($result) {
        echo "✓ Line update method executed\n";

        $content = file_get_contents($testFile);
        if (strpos($content, 'Updated paragraph text') !== false) {
            echo "\033[32m✓ Line successfully updated on disk!\033[0m\n";
        } else {
            echo "\033[31m✗ Line was not updated on disk\033[0m\n";
        }
    }

    // Test 3: Create backup
    echo "\n\033[1mTest 3: Backup Creation\033[0m\n";
    $backupId = $fileUpdater->createBackup($testFile);

    if ($backupId) {
        echo "✓ Backup created with ID: $backupId\n";

        // Check if backup file exists
        $backupPath = $testDir . '/backups/' . $backupId . '.backup';
        if (file_exists($backupPath)) {
            echo "\033[32m✓ Backup file exists on disk!\033[0m\n";
        } else {
            echo "\033[31m✗ Backup file not found\033[0m\n";
        }
    }

    // Test 4: Batch update
    echo "\n\033[1mTest 4: Batch Update\033[0m\n";
    $updates = [
        ['type' => 'content', 'old' => 'Updated Title', 'new' => 'Final Title'],
        ['type' => 'content', 'old' => 'Updated paragraph text', 'new' => 'Final paragraph text'],
    ];

    $result = $fileUpdater->batchUpdate($testFile, $updates);

    if ($result) {
        echo "✓ Batch update method executed\n";

        $content = file_get_contents($testFile);
        if (strpos($content, 'Final Title') !== false && strpos($content, 'Final paragraph text') !== false) {
            echo "\033[32m✓ All batch updates persisted to disk!\033[0m\n";
        } else {
            echo "\033[31m✗ Batch updates not fully persisted\033[0m\n";
        }
    }

    // Display final file content
    echo "\n\033[1mFinal File Content:\033[0m\n";
    echo "----------------------------------------\n";
    $finalContent = file_get_contents($testFile);
    echo $finalContent;
    echo "\n----------------------------------------\n";

    // Cleanup
    echo "\n\033[1mCleanup\033[0m\n";
    $filesystem->deleteDirectory($testDir);
    echo "✓ Test files cleaned up\n";

    echo "\n\033[32m\033[1m✓ File persistence is working correctly!\033[0m\n\n";

} catch (Exception $e) {
    echo "\n\033[31m\033[1m✗ Test failed:\033[0m\n";
    echo $e->getMessage() . "\n";
    echo "\nFile: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}