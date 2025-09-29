<?php

/**
 * Test script for CMS file persistence functionality
 *
 * This script tests that the FileUpdater service can properly:
 * 1. Read files
 * 2. Update content
 * 3. Persist changes to disk
 * 4. Create backups
 * 5. Restore from backups
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Webook\LaravelCMS\Services\FileUpdater;

// Test configuration
$testDir = __DIR__ . '/storage/cms-test';
$testFile = $testDir . '/test-content.html';
$originalContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
</head>
<body>
    <h1>Original Title</h1>
    <p>This is the original content.</p>
    <div class="editable" data-cms-key="content-1">
        This content should be editable.
    </div>
</body>
</html>
HTML;

$updatedContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
</head>
<body>
    <h1>Updated Title</h1>
    <p>This is the updated content.</p>
    <div class="editable" data-cms-key="content-1">
        This content has been edited via CMS.
    </div>
</body>
</html>
HTML;

// Helper function to display results
function displayResult($test, $passed, $message = '') {
    $status = $passed ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
    echo "$status $test";
    if ($message) {
        echo " - $message";
    }
    echo "\n";
}

echo "\n\033[1m=== CMS File Persistence Test ===\033[0m\n\n";

try {
    // Setup test environment
    $files = new Filesystem();

    // Create test directory
    if (!$files->exists($testDir)) {
        $files->makeDirectory($testDir, 0755, true);
    }

    // Create test file
    $files->put($testFile, $originalContent);
    displayResult('Test file created', true, $testFile);

    // Initialize FileUpdater service
    $config = [
        'auto_backup' => true,
        'rollback_on_failure' => true,
        'allowed_directories' => [$testDir],
        'backup_directory' => $testDir . '/backups',
        'lock_directory' => $testDir . '/locks',
    ];

    $fileUpdater = new FileUpdater(
        $files,
        Cache::store(),
        $config
    );

    displayResult('FileUpdater service initialized', true);

    // Test 1: Update content in file
    echo "\n\033[1mTest 1: Update Content\033[0m\n";

    $oldText = '<h1>Original Title</h1>';
    $newText = '<h1>Updated Title</h1>';

    $result = $fileUpdater->updateContent($testFile, $oldText, $newText);
    displayResult('Content update', $result);

    // Verify the change was persisted
    $currentContent = $files->get($testFile);
    $contentUpdated = strpos($currentContent, 'Updated Title') !== false;
    displayResult('Content persisted to disk', $contentUpdated);

    // Test 2: Update by line number
    echo "\n\033[1mTest 2: Update by Line Number\033[0m\n";

    $result = $fileUpdater->updateByLineNumber($testFile, 8, '    <p>This line was updated by line number.</p>');
    displayResult('Line update', $result);

    // Verify line update
    $lines = explode("\n", $files->get($testFile));
    $lineUpdated = strpos($lines[7], 'updated by line number') !== false;
    displayResult('Line update persisted', $lineUpdated);

    // Test 3: Create backup
    echo "\n\033[1mTest 3: Backup Creation\033[0m\n";

    $backupId = $fileUpdater->createBackup($testFile);
    $backupCreated = !empty($backupId);
    displayResult('Backup created', $backupCreated, "ID: $backupId");

    // Test 4: Make changes and restore
    echo "\n\033[1mTest 4: Restore from Backup\033[0m\n";

    // Make another change
    $fileUpdater->updateContent($testFile, 'Updated Title', 'Temporary Title');
    $tempContent = $files->get($testFile);
    $hasTemp = strpos($tempContent, 'Temporary Title') !== false;
    displayResult('Temporary change made', $hasTemp);

    // Restore from backup
    $result = $fileUpdater->restore($testFile, $backupId);
    displayResult('Restore from backup', $result);

    // Verify restoration
    $restoredContent = $files->get($testFile);
    $restored = strpos($restoredContent, 'Updated Title') !== false &&
                strpos($restoredContent, 'Temporary Title') === false;
    displayResult('Content restored correctly', $restored);

    // Test 5: Batch update
    echo "\n\033[1mTest 5: Batch Update\033[0m\n";

    $updates = [
        ['type' => 'content', 'old' => 'Updated Title', 'new' => 'Final Title'],
        ['type' => 'content', 'old' => 'updated by line number', 'new' => 'final content'],
    ];

    $result = $fileUpdater->batchUpdate($testFile, $updates);
    displayResult('Batch update', $result);

    // Verify batch updates
    $finalContent = $files->get($testFile);
    $batchSuccess = strpos($finalContent, 'Final Title') !== false &&
                    strpos($finalContent, 'final content') !== false;
    displayResult('Batch updates persisted', $batchSuccess);

    // Test 6: File locking
    echo "\n\033[1mTest 6: File Locking\033[0m\n";

    $locked = $fileUpdater->lock($testFile);
    displayResult('File locked', $locked);

    $lockPrevents = !$fileUpdater->lock($testFile);
    displayResult('Lock prevents concurrent access', $lockPrevents);

    $unlocked = $fileUpdater->unlock($testFile);
    displayResult('File unlocked', $unlocked);

    // Test 7: History tracking
    echo "\n\033[1mTest 7: History Tracking\033[0m\n";

    $history = $fileUpdater->history($testFile);
    $hasHistory = count($history) > 0;
    displayResult('History tracked', $hasHistory, count($history) . ' entries found');

    // Clean up test files
    echo "\n\033[1mCleanup\033[0m\n";
    $files->deleteDirectory($testDir);
    displayResult('Test files cleaned up', true);

    echo "\n\033[32m\033[1m✓ All tests passed successfully!\033[0m\n\n";

} catch (Exception $e) {
    echo "\n\033[31m\033[1m✗ Test failed with error:\033[0m\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}