<?php

namespace Webook\LaravelCMS\Tests\Browser;

use Webook\LaravelCMS\Tests\TestCase;
use Webook\LaravelCMS\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EditorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createTestUser(['cms_editor']);
    }

    public function test_user_can_login_and_access_editor()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertPathIs('/dashboard')
                    ->visit(route('cms.editor.index'))
                    ->assertSee('CMS Editor')
                    ->assertPresent('.cms-editor-container');
        });
    }

    public function test_editor_interface_loads_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->assertTitle('CMS Editor - Laravel CMS')
                    ->assertPresent('.cms-toolbar')
                    ->assertPresent('.cms-file-browser')
                    ->assertPresent('.cms-editor-main')
                    ->assertPresent('.cms-status-bar')
                    ->assertSee('Files')
                    ->assertSee('Editor')
                    ->assertSee('Preview');
        });
    }

    public function test_file_browser_displays_files()
    {
        $this->createTestBladeFile('browser-test.blade.php');
        $this->createTestHtmlFile('browser-test.html');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->assertSee('browser-test.blade.php')
                    ->assertSee('browser-test.html')
                    ->assertPresent('.file-item[data-type="blade"]')
                    ->assertPresent('.file-item[data-type="html"]');
        });
    }

    public function test_user_can_open_file_from_browser()
    {
        $testFile = $this->createTestBladeFile('open-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="open-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->assertSee('@extends')
                    ->assertSee('{{ $title }}')
                    ->assertPresent('.cms-editor-tabs .tab.active');
        });
    }

    public function test_user_can_edit_file_content()
    {
        $testFile = $this->createTestBladeFile('edit-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="edit-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->click('.cms-editor-textarea')
                    ->keys('.cms-editor-textarea', '{ctrl}', 'a') // Select all
                    ->type('.cms-editor-textarea', '@extends("layouts.app") @section("title", "Updated Title")')
                    ->assertValue('.cms-editor-textarea', '@extends("layouts.app") @section("title", "Updated Title")')
                    ->assertPresent('.cms-status-bar .status-modified');
        });
    }

    public function test_user_can_save_file_changes()
    {
        $testFile = $this->createTestBladeFile('save-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="save-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->click('.cms-editor-textarea')
                    ->keys('.cms-editor-textarea', '{ctrl}', 'a')
                    ->type('.cms-editor-textarea', '@extends("layouts.app") @section("title", "Saved Title")')
                    ->press('.cms-toolbar .btn-save')
                    ->waitFor('.cms-toast.success')
                    ->assertSee('File saved successfully')
                    ->assertMissing('.cms-status-bar .status-modified');
        });
    }

    public function test_user_can_use_keyboard_shortcuts()
    {
        $testFile = $this->createTestBladeFile('shortcuts-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="shortcuts-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->click('.cms-editor-textarea')
                    ->keys('.cms-editor-textarea', '{ctrl}', 'a')
                    ->type('.cms-editor-textarea', 'Updated content via keyboard')
                    ->keys('.cms-editor-textarea', '{ctrl}', 's') // Save shortcut
                    ->waitFor('.cms-toast.success')
                    ->assertSee('File saved successfully');
        });
    }

    public function test_preview_mode_works()
    {
        $testFile = $this->createTestHtmlFile('preview-test.html');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="preview-test.html"]')
                    ->waitFor('.cms-editor-content')
                    ->click('.cms-toolbar .btn-preview')
                    ->waitFor('.cms-preview-panel')
                    ->assertPresent('.cms-preview-iframe')
                    ->assertSee('Preview Mode')
                    ->switchToIFrame('.cms-preview-iframe')
                    ->assertSee('Welcome to Test Page')
                    ->assertSee('Main Content');
        });
    }

    public function test_search_and_replace_functionality()
    {
        $testFile = $this->createTestBladeFile('search-replace-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="search-replace-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->keys('body', '{ctrl}', 'h') // Search shortcut
                    ->waitFor('.cms-search-replace-panel')
                    ->type('.search-input', '{{ $title }}')
                    ->type('.replace-input', '{{ $pageTitle }}')
                    ->press('.btn-replace-all')
                    ->waitFor('.cms-toast.success')
                    ->assertSee('Replaced 1 occurrence');
        });
    }

    public function test_file_upload_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-toolbar .btn-upload')
                    ->waitFor('.cms-upload-modal')
                    ->attach('.upload-input', __DIR__ . '/fixtures/test-upload.blade.php')
                    ->press('.btn-upload-confirm')
                    ->waitFor('.cms-toast.success')
                    ->assertSee('File uploaded successfully')
                    ->waitUntilMissing('.cms-upload-modal')
                    ->assertPresent('.file-item[data-file="test-upload.blade.php"]');
        });
    }

    public function test_theme_switching_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-toolbar .btn-settings')
                    ->waitFor('.cms-settings-panel')
                    ->select('.theme-selector', 'dark')
                    ->press('.btn-apply-settings')
                    ->waitFor('.cms-toast.success')
                    ->assertAttribute('body', 'data-theme', 'dark')
                    ->assertHasClass('.cms-editor-container', 'theme-dark');
        });
    }

    public function test_responsive_design_on_mobile()
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(375, 667) // iPhone size
                    ->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->assertPresent('.cms-mobile-menu-toggle')
                    ->click('.cms-mobile-menu-toggle')
                    ->waitFor('.cms-mobile-menu')
                    ->assertSee('Files')
                    ->assertSee('Editor')
                    ->assertSee('Preview')
                    ->click('.cms-mobile-menu .menu-item[data-panel="files"]')
                    ->waitFor('.cms-file-browser')
                    ->assertVisible('.cms-file-browser');
        });
    }

    public function test_error_handling_for_invalid_files()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->visit(route('cms.editor.preview', ['url' => 'nonexistent/file.blade.php']))
                    ->waitFor('.cms-error-message')
                    ->assertSee('File not found')
                    ->assertPresent('.cms-error-icon');
        });
    }

    public function test_auto_save_functionality()
    {
        $testFile = $this->createTestBladeFile('autosave-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="autosave-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->click('.cms-editor-textarea')
                    ->keys('.cms-editor-textarea', '{ctrl}', 'a')
                    ->type('.cms-editor-textarea', 'Auto-saved content')
                    ->pause(3000) // Wait for auto-save
                    ->assertSee('Auto-saved')
                    ->refresh()
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="autosave-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->assertValue('.cms-editor-textarea', 'Auto-saved content');
        });
    }

    public function test_syntax_highlighting_works()
    {
        $testFile = $this->createTestBladeFile('syntax-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="syntax-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->assertPresent('.cms-syntax-highlight')
                    ->assertPresent('.token.keyword') // @extends, @section etc.
                    ->assertPresent('.token.string')  // Quoted strings
                    ->assertPresent('.token.variable'); // {{ $variables }}
        });
    }

    public function test_line_numbers_display()
    {
        $testFile = $this->createTestBladeFile('line-numbers-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="line-numbers-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->assertPresent('.cms-line-numbers')
                    ->assertSee('1')
                    ->assertSee('2')
                    ->assertSee('3');
        });
    }

    public function test_concurrent_editing_warning()
    {
        $testFile = $this->createTestBladeFile('concurrent-test.blade.php');

        // This test requires setting up multiple browser sessions
        // For simplicity, we'll test the warning mechanism
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="concurrent-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    // Simulate another user editing (via API call)
                    ->script('
                        fetch("/cms/api/content/text/update", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]").content
                            },
                            body: JSON.stringify({
                                file_path: "tests/fixtures/views/concurrent-test.blade.php",
                                search: "test",
                                replace: "modified"
                            })
                        });
                    ')
                    ->pause(1000)
                    ->waitFor('.cms-concurrent-warning')
                    ->assertSee('Another user is editing this file');
        });
    }

    public function test_accessibility_features()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->assertAttribute('.cms-editor-container', 'role', 'application')
                    ->assertAttribute('.cms-editor-textarea', 'aria-label', 'Code editor')
                    ->assertPresent('[aria-expanded]')
                    ->assertPresent('[role="button"]')
                    ->keys('body', '{tab}') // Test keyboard navigation
                    ->assertFocused('.cms-file-browser-toggle');
        });
    }

    public function test_high_contrast_mode()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-toolbar .btn-settings')
                    ->waitFor('.cms-settings-panel')
                    ->check('.high-contrast-toggle')
                    ->press('.btn-apply-settings')
                    ->waitFor('.cms-toast.success')
                    ->assertHasClass('body', 'high-contrast-mode')
                    ->assertAttribute('body', 'data-theme', 'high-contrast');
        });
    }

    public function test_undo_redo_functionality()
    {
        $testFile = $this->createTestBladeFile('undo-redo-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="undo-redo-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->click('.cms-editor-textarea')
                    ->keys('.cms-editor-textarea', '{end}')
                    ->type('.cms-editor-textarea', ' Additional content')
                    ->keys('.cms-editor-textarea', '{ctrl}', 'z') // Undo
                    ->assertNotValue('.cms-editor-textarea', '@extends("layout") Additional content')
                    ->keys('.cms-editor-textarea', '{ctrl}', 'y') // Redo
                    ->assertValue('.cms-editor-textarea', '@extends("layout") Additional content');
        });
    }

    public function test_status_bar_information()
    {
        $testFile = $this->createTestBladeFile('status-bar-test.blade.php');

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('cms.editor.index'))
                    ->click('.cms-file-browser-toggle')
                    ->waitFor('.cms-file-tree')
                    ->click('.file-item[data-file="status-bar-test.blade.php"]')
                    ->waitFor('.cms-editor-content')
                    ->assertPresent('.cms-status-bar')
                    ->assertSeeIn('.cms-status-bar', 'blade.php') // File type
                    ->assertSeeIn('.cms-status-bar', 'Line') // Line number
                    ->assertSeeIn('.cms-status-bar', 'Col') // Column number
                    ->click('.cms-editor-textarea')
                    ->assertSeeIn('.cms-status-bar', 'Line 1, Col 1');
        });
    }

    protected function createFixtureFile(): void
    {
        $content = <<<'BLADE'
@extends('layouts.app')

@section('title', 'Test Upload File')

@section('content')
    <h1>Test Upload</h1>
    <p>This file was uploaded via browser test</p>
@endsection
BLADE;

        $fixturePath = __DIR__ . '/fixtures';
        if (!is_dir($fixturePath)) {
            mkdir($fixturePath, 0755, true);
        }

        file_put_contents($fixturePath . '/test-upload.blade.php', $content);
    }
}