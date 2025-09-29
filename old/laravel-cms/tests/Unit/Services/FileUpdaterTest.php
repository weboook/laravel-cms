<?php

namespace Webook\LaravelCMS\Tests\Unit\Services;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use PHPUnit\Framework\TestCase;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\UpdateStrategies\BladeStrategy;
use Webook\LaravelCMS\Services\UpdateStrategies\DOMStrategy;
use Webook\LaravelCMS\Services\UpdateStrategies\TextStrategy;

/**
 * File Updater Unit Tests
 *
 * Comprehensive test suite for the FileUpdater service covering:
 * - Safe file modification operations
 * - Strategy selection and execution
 * - Backup and restore functionality
 * - Atomic operations and transactions
 * - Security validation and safety features
 */
class FileUpdaterTest extends TestCase
{
    protected FileUpdater $fileUpdater;
    protected Filesystem $files;
    protected CacheRepository $cache;
    protected array $config;
    protected string $testFile;
    protected string $backupDir;
    protected string $lockDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = Mockery::mock(Filesystem::class);
        $this->cache = Mockery::mock(CacheRepository::class);

        $this->testFile = '/tmp/test-file.blade.php';
        $this->backupDir = '/tmp/cms-backups';
        $this->lockDir = '/tmp/cms-locks';

        $this->config = [
            'auto_backup' => true,
            'rollback_on_failure' => true,
            'git_commit' => false,
            'validate_blade_compilation' => false,
            'allowed_directories' => ['/tmp'],
            'backup_directory' => $this->backupDir,
            'lock_directory' => $this->lockDir,
        ];

        $this->fileUpdater = new FileUpdater($this->files, $this->cache, $this->config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test basic content update functionality.
     */
    public function testUpdateContent(): void
    {
        $originalContent = '<div>{{ $oldVariable }}</div>';
        $expectedContent = '<div>{{ $newVariable }}</div>';

        $this->setupBasicFileMocks($originalContent);
        $this->setupLockingMocks();
        $this->setupBackupMocks();

        // Mock atomic write
        $this->files->shouldReceive('put')->once();
        $this->files->shouldReceive('get')->andReturn($expectedContent);
        $this->files->shouldReceive('move')->once();

        $result = $this->fileUpdater->updateContent(
            $this->testFile,
            '{{ $oldVariable }}',
            '{{ $newVariable }}'
        );

        $this->assertTrue($result);
    }

    /**
     * Test update by line number.
     */
    public function testUpdateByLineNumber(): void
    {
        $originalContent = "Line 1\nLine 2\nLine 3";
        $expectedContent = "Line 1\nUpdated Line 2\nLine 3";

        $this->setupBasicFileMocks($originalContent);
        $this->setupLockingMocks();
        $this->setupBackupMocks();

        // Mock atomic write
        $this->files->shouldReceive('put')->once();
        $this->files->shouldReceive('get')->andReturn($expectedContent);
        $this->files->shouldReceive('move')->once();

        $result = $this->fileUpdater->updateByLineNumber($this->testFile, 2, 'Updated Line 2');

        $this->assertTrue($result);
    }

    /**
     * Test update by CSS selector.
     */
    public function testUpdateBySelector(): void
    {
        $originalContent = '<div class="content">Old Content</div>';
        $expectedContent = '<div class="content">New Content</div>';

        $this->setupBasicFileMocks($originalContent);
        $this->setupLockingMocks();
        $this->setupBackupMocks();

        // Mock atomic write
        $this->files->shouldReceive('put')->once();
        $this->files->shouldReceive('get')->andReturn($expectedContent);
        $this->files->shouldReceive('move')->once();

        $result = $this->fileUpdater->updateBySelector(
            $this->testFile,
            '.content',
            'New Content'
        );

        $this->assertTrue($result);
    }

    /**
     * Test attribute update functionality.
     */
    public function testUpdateAttribute(): void
    {
        $originalContent = '<div class="old-class">Content</div>';
        $expectedContent = '<div class="new-class">Content</div>';

        $this->setupBasicFileMocks($originalContent);
        $this->setupLockingMocks();
        $this->setupBackupMocks();

        // Mock atomic write
        $this->files->shouldReceive('put')->once();
        $this->files->shouldReceive('get')->andReturn($expectedContent);
        $this->files->shouldReceive('move')->once();

        $result = $this->fileUpdater->updateAttribute(
            $this->testFile,
            'div',
            'class',
            'new-class'
        );

        $this->assertTrue($result);
    }

    /**
     * Test batch update functionality.
     */
    public function testBatchUpdate(): void
    {
        $originalContent = '<div>{{ $var1 }}</div><p>{{ $var2 }}</p>';

        $updates = [
            [
                'type' => 'content',
                'old' => '{{ $var1 }}',
                'new' => '{{ $newVar1 }}',
            ],
            [
                'type' => 'content',
                'old' => '{{ $var2 }}',
                'new' => '{{ $newVar2 }}',
            ],
        ];

        $this->setupBasicFileMocks($originalContent);
        $this->setupLockingMocks();
        $this->setupBackupMocks();

        // Mock atomic write
        $this->files->shouldReceive('put')->once();
        $this->files->shouldReceive('get')->andReturn($originalContent);
        $this->files->shouldReceive('move')->once();

        $result = $this->fileUpdater->batchUpdate($this->testFile, $updates);

        $this->assertTrue($result);
    }

    /**
     * Test backup creation functionality.
     */
    public function testCreateBackup(): void
    {
        $content = '<div>Test content</div>';

        $this->setupBasicFileMocks($content);

        // Mock backup directory creation
        $this->files->shouldReceive('exists')->with($this->backupDir)->andReturn(false);
        $this->files->shouldReceive('makeDirectory')->with($this->backupDir, 0755, true)->once();

        // Mock file copy and backup index
        $this->files->shouldReceive('copy')->once();
        $this->files->shouldReceive('size')->with($this->testFile)->andReturn(1024);

        // Mock backup index operations
        $this->files->shouldReceive('exists')->with(Mockery::pattern('/.+\/index\.json$/'))->andReturn(false);
        $this->files->shouldReceive('put')->with(Mockery::pattern('/.+\/index\.json$/'), Mockery::any())->once();

        $backupId = $this->fileUpdater->createBackup($this->testFile);

        $this->assertIsString($backupId);
        $this->assertStringStartsWith('backup_', $backupId);
    }

    /**
     * Test file restore functionality.
     */
    public function testRestore(): void
    {
        $content = '<div>Original content</div>';
        $backupId = 'backup_test_123';

        $this->setupBasicFileMocks($content);
        $this->setupLockingMocks();

        // Create a mock backup first
        $this->fileUpdater = Mockery::mock(FileUpdater::class)->makePartial();
        $this->fileUpdater->shouldReceive('validateFilePath')->once();

        // Mock backup index with test backup
        $backupIndex = [
            $backupId => [
                'original_file' => $this->testFile,
                'backup_path' => $this->backupDir . '/' . $backupId . '.backup',
                'checksum' => md5($content),
            ],
        ];

        $reflection = new \ReflectionClass($this->fileUpdater);
        $property = $reflection->getProperty('backupIndex');
        $property->setAccessible(true);
        $property->setValue($this->fileUpdater, $backupIndex);

        // Mock file operations for restore
        $this->fileUpdater->shouldReceive('executeWithTransaction')->once()->andReturn(true);

        $result = $this->fileUpdater->restore($this->testFile, $backupId);

        $this->assertTrue($result);
    }

    /**
     * Test file diff functionality.
     */
    public function testDiff(): void
    {
        $originalContent = "Line 1\nLine 2\nLine 3";
        $backupContent = "Line 1\nOld Line 2\nLine 3";
        $backupId = 'backup_test_123';

        $this->setupBasicFileMocks($originalContent);

        // Mock backup index
        $backupPath = $this->backupDir . '/' . $backupId . '.backup';
        $backupIndex = [
            $backupId => [
                'original_file' => $this->testFile,
                'backup_path' => $backupPath,
            ],
        ];

        $this->fileUpdater = Mockery::mock(FileUpdater::class)->makePartial();
        $this->fileUpdater->shouldReceive('validateFilePath')->once();

        $reflection = new \ReflectionClass($this->fileUpdater);
        $property = $reflection->getProperty('backupIndex');
        $property->setAccessible(true);
        $property->setValue($this->fileUpdater, $backupIndex);

        // Mock file content retrieval
        $this->fileUpdater->shouldReceive('files->exists')->with($backupPath)->andReturn(true);
        $this->fileUpdater->shouldReceive('files->get')->with($this->testFile)->andReturn($originalContent);
        $this->fileUpdater->shouldReceive('files->get')->with($backupPath)->andReturn($backupContent);

        $diff = $this->fileUpdater->diff($this->testFile, $backupId);

        $this->assertIsArray($diff);
        $this->assertArrayHasKey('modified', $diff);
        $this->assertNotEmpty($diff['modified']);
    }

    /**
     * Test file history functionality.
     */
    public function testHistory(): void
    {
        $this->setupBasicFileMocks('<div>Content</div>');

        // Mock backup index with multiple backups
        $backupIndex = [
            'backup_1' => [
                'original_file' => $this->testFile,
                'created_at' => '2023-01-01T10:00:00Z',
                'size' => 1024,
                'checksum' => 'abc123',
            ],
            'backup_2' => [
                'original_file' => $this->testFile,
                'created_at' => '2023-01-02T10:00:00Z',
                'size' => 1048,
                'checksum' => 'def456',
            ],
            'backup_3' => [
                'original_file' => '/other/file.php',
                'created_at' => '2023-01-03T10:00:00Z',
                'size' => 512,
                'checksum' => 'ghi789',
            ],
        ];

        $reflection = new \ReflectionClass($this->fileUpdater);
        $property = $reflection->getProperty('backupIndex');
        $property->setAccessible(true);
        $property->setValue($this->fileUpdater, $backupIndex);

        $history = $this->fileUpdater->history($this->testFile);

        $this->assertCount(2, $history); // Only backups for this file
        $this->assertEquals('backup_2', $history[0]['backup_id']); // Most recent first
        $this->assertEquals('backup_1', $history[1]['backup_id']);
    }

    /**
     * Test file locking functionality.
     */
    public function testFileLocking(): void
    {
        // Mock lock directory creation
        $this->files->shouldReceive('exists')->with($this->lockDir)->andReturn(false);
        $this->files->shouldReceive('makeDirectory')->with($this->lockDir, 0755, true)->once();

        // Mock lock file creation
        $this->files->shouldReceive('put')->once();

        $result = $this->fileUpdater->lock($this->testFile);
        $this->assertTrue($result);

        // Test double lock (should fail)
        $result2 = $this->fileUpdater->lock($this->testFile);
        $this->assertFalse($result2);

        // Mock lock file deletion
        $this->files->shouldReceive('exists')->andReturn(true);
        $this->files->shouldReceive('delete')->once();

        $unlockResult = $this->fileUpdater->unlock($this->testFile);
        $this->assertTrue($unlockResult);
    }

    /**
     * Test strategy selection.
     */
    public function testStrategySelection(): void
    {
        // Test Blade strategy selection
        $bladeContent = '@extends("layout")\n{{ $variable }}';
        $strategy = $this->callProtectedMethod('selectStrategy', [$bladeContent]);
        $this->assertInstanceOf(BladeStrategy::class, $strategy);

        // Test DOM strategy selection
        $htmlContent = '<div class="content">HTML content</div>';
        $strategy = $this->callProtectedMethod('selectStrategy', [$htmlContent]);
        $this->assertInstanceOf(DOMStrategy::class, $strategy);

        // Test Text strategy fallback
        $textContent = 'Plain text content';
        $strategy = $this->callProtectedMethod('selectStrategy', [$textContent]);
        $this->assertInstanceOf(TextStrategy::class, $strategy);
    }

    /**
     * Test file path validation.
     */
    public function testFilePathValidation(): void
    {
        // Test non-existent file
        $this->files->shouldReceive('exists')->with('/non-existent-file.php')->andReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $this->fileUpdater->updateContent('/non-existent-file.php', 'old', 'new');
    }

    /**
     * Test security validation for file paths.
     */
    public function testSecurityValidation(): void
    {
        $unauthorizedFile = '/etc/passwd';

        $this->files->shouldReceive('exists')->with($unauthorizedFile)->andReturn(true);
        $this->files->shouldReceive('isReadable')->with($unauthorizedFile)->andReturn(true);
        $this->files->shouldReceive('isWritable')->with($unauthorizedFile)->andReturn(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File outside allowed directories');

        $this->fileUpdater->updateContent($unauthorizedFile, 'old', 'new');
    }

    /**
     * Test rollback on failure.
     */
    public function testRollbackOnFailure(): void
    {
        $originalContent = '<div>Original</div>';

        $this->setupBasicFileMocks($originalContent);
        $this->setupLockingMocks();

        // Mock backup creation
        $this->files->shouldReceive('exists')->with($this->backupDir)->andReturn(true);
        $this->files->shouldReceive('copy')->once(); // For backup
        $this->files->shouldReceive('size')->andReturn(1024);
        $this->files->shouldReceive('put')->with(Mockery::pattern('/.+\/index\.json$/'), Mockery::any());

        // Mock atomic write failure
        $this->files->shouldReceive('put')->with(Mockery::pattern('/.+\.tmp\..+/'), Mockery::any())->andThrow(new \Exception('Write failed'));

        // Mock rollback (restore from backup)
        $this->files->shouldReceive('copy')->once(); // For rollback

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Write failed');

        $this->fileUpdater->updateContent($this->testFile, 'old', 'new');
    }

    /**
     * Test atomic write operation.
     */
    public function testAtomicWrite(): void
    {
        $content = '<div>Test content</div>';
        $tempFile = $this->testFile . '.tmp.abc123';

        // Mock temporary file operations
        $this->files->shouldReceive('put')->with(Mockery::pattern('/.+\.tmp\..+/'), $content)->once();
        $this->files->shouldReceive('get')->with(Mockery::pattern('/.+\.tmp\..+/'))->andReturn($content);
        $this->files->shouldReceive('move')->with(Mockery::pattern('/.+\.tmp\..+/'), $this->testFile)->once();

        $this->callProtectedMethod('atomicWrite', [$this->testFile, $content]);

        // Test should pass if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * Test validation and save.
     */
    public function testValidationAndSave(): void
    {
        $content = '<div>Valid content</div>';

        // Mock strategy validation
        $strategy = Mockery::mock(BladeStrategy::class);
        $strategy->shouldReceive('validate')->andReturn([
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ]);

        // Mock atomic write
        $this->files->shouldReceive('put')->once();
        $this->files->shouldReceive('get')->andReturn($content);
        $this->files->shouldReceive('move')->once();

        $this->callProtectedMethod('validateAndSave', [$this->testFile, $content, $strategy]);

        // Test should pass if no exceptions are thrown
        $this->assertTrue(true);
    }

    /**
     * Setup basic file mocks for testing.
     */
    protected function setupBasicFileMocks(string $content): void
    {
        $this->files->shouldReceive('exists')->with($this->testFile)->andReturn(true);
        $this->files->shouldReceive('isReadable')->with($this->testFile)->andReturn(true);
        $this->files->shouldReceive('isWritable')->with($this->testFile)->andReturn(true);
        $this->files->shouldReceive('get')->with($this->testFile)->andReturn($content);
    }

    /**
     * Setup file locking mocks.
     */
    protected function setupLockingMocks(): void
    {
        $this->files->shouldReceive('exists')->with($this->lockDir)->andReturn(true);
        $this->files->shouldReceive('put')->with(Mockery::pattern('/.+\.lock$/'), Mockery::any());
        $this->files->shouldReceive('exists')->with(Mockery::pattern('/.+\.lock$/'))->andReturn(true);
        $this->files->shouldReceive('delete')->with(Mockery::pattern('/.+\.lock$/'));
    }

    /**
     * Setup backup mocks.
     */
    protected function setupBackupMocks(): void
    {
        $this->files->shouldReceive('exists')->with($this->backupDir)->andReturn(true);
        $this->files->shouldReceive('copy')->once(); // For backup
        $this->files->shouldReceive('size')->andReturn(1024);

        // Mock backup index file operations
        $this->files->shouldReceive('exists')->with(Mockery::pattern('/.+\/index\.json$/'))->andReturn(false);
        $this->files->shouldReceive('put')->with(Mockery::pattern('/.+\/index\.json$/'), Mockery::any());
    }

    /**
     * Call protected method for testing.
     */
    protected function callProtectedMethod(string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($this->fileUpdater);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($this->fileUpdater, $args);
    }
}