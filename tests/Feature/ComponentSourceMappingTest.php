<?php

namespace Webook\LaravelCMS\Tests\Feature;

use Webook\LaravelCMS\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class ComponentSourceMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable component source mapping feature
        Config::set('cms.features.component_source_mapping', true);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $this->cleanupTestFiles();

        parent::tearDown();
    }

    /**
     * Test that component content updates the correct file
     *
     * @test
     */
    public function test_component_content_updates_correct_file()
    {
        // Create a test component
        $componentPath = 'resources/views/components/test-alert.blade.php';
        $componentContent = <<<'BLADE'
@cmsSourceStart
<div class="alert">
    <p>Original component content</p>
</div>
@cmsSourceEnd
BLADE;

        $componentFile = $this->createTestFile($componentPath, $componentContent);

        // Create a test view that uses the component
        $viewPath = 'resources/views/test-view.blade.php';
        $viewContent = <<<'BLADE'
<html>
<body>
    <x-test-alert />
</body>
</html>
BLADE;

        $viewFile = $this->createTestFile($viewPath, $viewContent);

        // Simulate content update via API with source mapping
        $response = $this->postJson('/api/cms/content/save', [
            'element_id' => 'p-test123',
            'content' => 'Updated component content',
            'original_content' => 'Original component content',
            'type' => 'text',
            'page_url' => 'http://localhost/test',
            'file_hint' => $componentPath,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Assert component file was updated, not the view file
        $updatedComponentContent = File::get($componentFile);
        $this->assertStringContainsString('Updated component content', $updatedComponentContent);

        $viewContentAfter = File::get($viewFile);
        $this->assertStringNotContainsString('Updated component content', $viewContentAfter);
    }

    /**
     * Test that invalid source paths are rejected
     *
     * @test
     */
    public function test_invalid_source_path_rejected()
    {
        $response = $this->postJson('/api/cms/content/save', [
            'element_id' => 'test-id',
            'content' => 'Some content',
            'original_content' => 'Original',
            'type' => 'text',
            'page_url' => 'http://localhost/test',
            'file_hint' => '../../../etc/passwd',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'Invalid source file path'
        ]);
    }

    /**
     * Test that non-blade files are rejected
     *
     * @test
     */
    public function test_non_blade_file_rejected()
    {
        $response = $this->postJson('/api/cms/content/save', [
            'element_id' => 'test-id',
            'content' => 'Some content',
            'original_content' => 'Original',
            'type' => 'text',
            'page_url' => 'http://localhost/test',
            'file_hint' => 'config/app.php',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'Invalid source file path'
        ]);
    }

    /**
     * Test that backup is created for component updates
     *
     * @test
     */
    public function test_backup_created_for_component_updates()
    {
        $componentPath = 'resources/views/components/test-backup.blade.php';
        $componentContent = '@cmsSourceStart<p>Original</p>@cmsSourceEnd';

        $componentFile = $this->createTestFile($componentPath, $componentContent);

        $response = $this->postJson('/api/cms/content/save', [
            'element_id' => 'p-test',
            'content' => 'Updated',
            'original_content' => 'Original',
            'type' => 'text',
            'page_url' => 'http://localhost/test',
            'file_hint' => $componentPath,
        ]);

        $response->assertStatus(200);

        // Assert backup was created
        $this->assertBackupExists($componentFile);
    }

    /**
     * Test that source markers are properly handled
     *
     * @test
     */
    public function test_source_markers_are_handled()
    {
        $componentPath = 'resources/views/components/test-markers.blade.php';
        $componentContent = <<<'BLADE'
@cmsSourceStart
<div class="alert">
    <p>Content within markers</p>
</div>
@cmsSourceEnd
BLADE;

        $componentFile = $this->createTestFile($componentPath, $componentContent);

        $response = $this->postJson('/api/cms/content/save', [
            'element_id' => 'p-test',
            'content' => 'Updated content within markers',
            'original_content' => 'Content within markers',
            'type' => 'text',
            'page_url' => 'http://localhost/test',
            'file_hint' => $componentPath,
        ]);

        $response->assertStatus(200);

        $updatedContent = File::get($componentFile);
        $this->assertStringContainsString('@cmsSourceStart', $updatedContent);
        $this->assertStringContainsString('@cmsSourceEnd', $updatedContent);
        $this->assertStringContainsString('Updated content within markers', $updatedContent);
    }

    /**
     * Clean up test files
     */
    protected function cleanupTestFiles()
    {
        $testFiles = [
            'resources/views/components/test-alert.blade.php',
            'resources/views/components/test-backup.blade.php',
            'resources/views/components/test-markers.blade.php',
            'resources/views/test-view.blade.php',
        ];

        foreach ($testFiles as $file) {
            $fullPath = base_path($file);
            if (File::exists($fullPath)) {
                $this->cleanupTestFile($fullPath);
            }
        }
    }
}
