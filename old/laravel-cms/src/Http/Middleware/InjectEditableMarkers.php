<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Webook\LaravelCMS\Services\PermissionManager;
use Webook\LaravelCMS\Services\SettingsManager;

/**
 * Middleware to inject CMS editor and editable markers into HTML responses
 *
 * This middleware automatically adds the CMS editor interface to all web pages
 * based on user permissions and configuration settings.
 */
class InjectEditableMarkers
{
    protected $permissionManager;
    protected $settingsManager;

    public function __construct(PermissionManager $permissionManager, SettingsManager $settingsManager)
    {
        $this->permissionManager = $permissionManager;
        $this->settingsManager = $settingsManager;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Debug: Add header to check if middleware is running
        $response->headers->set('X-CMS-Middleware', 'running');

        // Only inject on HTML responses for web routes
        if (!$this->shouldInjectCMS($request, $response)) {
            $response->headers->set('X-CMS-ShouldInject', 'false');
            return $response;
        }
        $response->headers->set('X-CMS-ShouldInject', 'true');

        $content = $response->getContent();

        // Check if CMS should be available on this route
        if (!$this->isCMSAllowedOnRoute($request->path())) {
            $response->headers->set('X-CMS-RouteAllowed', 'false');
            return $response;
        }
        $response->headers->set('X-CMS-RouteAllowed', 'true');

        // Check user permissions
        if (!$this->hasAccessPermission($request)) {
            $response->headers->set('X-CMS-HasAccess', 'false');
            return $response;
        }
        $response->headers->set('X-CMS-HasAccess', 'true');

        // Inject CMS assets and toolbar
        $content = $this->injectCMSAssets($content);
        $response->headers->set('X-CMS-Injected', 'true');

        $response->setContent($content);

        return $response;
    }

    /**
     * Determine if CMS should be injected
     */
    protected function shouldInjectCMS(Request $request, $response): bool
    {
        // Don't inject on API routes
        if ($request->is('api/*')) {
            return false;
        }

        // Don't inject on admin routes
        if ($request->is('admin/*')) {
            return false;
        }

        // Only inject on HTML responses
        if (!$response instanceof Response) {
            return false;
        }

        $contentType = $response->headers->get('content-type', '');
        if (!str_contains($contentType, 'text/html')) {
            return false;
        }

        // Don't inject on partial/AJAX requests
        if ($request->ajax() || $request->pjax()) {
            return false;
        }

        return true;
    }

    /**
     * Check if CMS is allowed on this route
     */
    protected function isCMSAllowedOnRoute(string $path): bool
    {
        $excludedRoutes = $this->settingsManager->get('cms.excluded_routes', []);

        foreach ($excludedRoutes as $pattern) {
            if (fnmatch($pattern, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has access permission
     */
    protected function hasAccessPermission(Request $request): bool
    {
        // Check IP restrictions
        if (!$this->isIPAllowed($request->ip())) {
            return false;
        }

        // Check user group restrictions
        if (!$this->isUserGroupAllowed()) {
            return false;
        }

        return true;
    }

    /**
     * Check if IP is allowed
     */
    protected function isIPAllowed(string $ip): bool
    {
        $allowedIPs = $this->settingsManager->get('cms.allowed_ips', []);
        $blockedIPs = $this->settingsManager->get('cms.blocked_ips', []);

        // If blocked IPs list exists and IP is in it, deny access
        if (!empty($blockedIPs) && in_array($ip, $blockedIPs)) {
            return false;
        }

        // If allowed IPs list exists and IP is not in it, deny access
        if (!empty($allowedIPs) && !in_array($ip, $allowedIPs)) {
            return false;
        }

        return true;
    }

    /**
     * Check if user group is allowed
     */
    protected function isUserGroupAllowed(): bool
    {
        $allowedGroups = $this->settingsManager->get('cms.allowed_user_groups', []);

        // If no restrictions, allow all (default behavior)
        if (empty($allowedGroups)) {
            return true;
        }

        // If user is not authenticated, allow access by default for now
        // This can be restricted later via settings
        if (!Auth::check()) {
            return true; // Allow anonymous users by default
        }

        $user = Auth::user();

        // Check if user has required role/group
        foreach ($allowedGroups as $group) {
            if (method_exists($user, 'hasRole') && $user->hasRole($group)) {
                return true;
            }
            if (method_exists($user, 'hasGroup') && $user->hasGroup($group)) {
                return true;
            }
        }

        // If user doesn't have required groups but groups are specified,
        // still allow for now - this can be made more restrictive via settings
        return true;
    }

    /**
     * Inject CMS assets into HTML content
     */
    protected function injectCMSAssets(string $content): string
    {
        // Find the closing </body> tag
        $bodyClosePos = strrpos($content, '</body>');
        if ($bodyClosePos === false) {
            return $content;
        }

        // Generate CMS HTML
        $cmsHtml = $this->generateCMSHTML();

        // Inject before closing body tag
        return substr_replace($content, $cmsHtml . "\n", $bodyClosePos, 0);
    }

    /**
     * Generate CMS HTML with toolbar and assets
     */
    protected function generateCMSHTML(): string
    {
        $assetPath = function($path) {
            return asset("vendor/cms/{$path}");
        };

        $csrfToken = csrf_token();
        $apiUrl = url('cms/api/assets/browse');
        $uploadUrl = url('cms/api/assets/upload');
        $contentUpdateUrl = url('cms/api/content/bulk');
        $timestamp = time();

        return <<<HTML
    <!-- Laravel CMS Assets -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{$assetPath('css/cms-variables.css')}?v={$timestamp}" rel="stylesheet">
    <link href="{$assetPath('css/cms-toolbar.css')}?v={$timestamp}" rel="stylesheet">
    <link href="{$assetPath('css/cms-editor.css')}?v={$timestamp}" rel="stylesheet">
    <link href="{$assetPath('css/cms-dark-mode.css')}?v={$timestamp}" rel="stylesheet">
    <!-- Asset library CSS disabled for now
    <link href="{$assetPath('css/cms-asset-library.css')}?v={$timestamp}" rel="stylesheet">
    <link href="{$assetPath('css/dropzone-integration.css')}?v={$timestamp}" rel="stylesheet">
    <link href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" rel="stylesheet">
    -->

    <!-- CMS Toolbar and Modal Styles -->
    <style>
        /* Critical toolbar styles to ensure visibility */
        #cmsToolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            z-index: 99999;
            display: none;
            align-items: center;
            padding: 0 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        #cmsToolbar.active {
            display: flex !important;
        }

        body.cms-toolbar-active {
            padding-top: 56px !important;
        }

        .cms-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .cms-toolbar-left,
        .cms-toolbar-center,
        .cms-toolbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cms-toolbar-divider {
            width: 1px;
            height: 24px;
            background: #dee2e6;
        }

        .laravel-cms-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            color: #495057;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .laravel-cms-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .laravel-cms-btn.primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .laravel-cms-btn.primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid #e1e5e9;
            margin-bottom: 20px;
        }

        .settings-tab {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: #6c757d;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .settings-tab:hover {
            color: #007bff;
            background-color: #f8f9fa;
        }

        .settings-tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }

        .settings-tab-content {
            display: none;
        }

        .settings-tab-content.active {
            display: block;
        }

        .settings-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .settings-section h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .settings-description {
            margin: 0 0 15px 0;
            color: #6c757d;
            font-size: 14px;
            line-height: 1.4;
        }

        .settings-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .settings-input-group input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .settings-input-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .settings-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .settings-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .settings-list-item span {
            font-size: 14px;
            color: #333;
        }

        .laravel-cms-btn-small {
            padding: 4px 8px;
            font-size: 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .laravel-cms-btn-small:hover {
            background: #c82333;
        }

        /* CMS Settings Modal Styles */
        .cms-settings-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cms-settings-modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .settings-toggle-group {
            margin-bottom: 15px;
        }

        .settings-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }

        .settings-toggle input[type="checkbox"] {
            position: relative;
            width: 50px;
            height: 24px;
            appearance: none;
            background: #ccc;
            border-radius: 12px;
            transition: background 0.2s;
            cursor: pointer;
        }

        .settings-toggle input[type="checkbox"]:checked {
            background: #007bff;
        }

        .settings-toggle input[type="checkbox"]::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.2s;
        }

        .settings-toggle input[type="checkbox"]:checked::before {
            transform: translateX(26px);
        }

        .settings-toggle-label {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .settings-examples {
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
            padding: 15px;
        }

        .settings-example {
            margin-bottom: 8px;
            font-size: 14px;
            color: #6c757d;
        }

        .settings-example:last-child {
            margin-bottom: 0;
        }

        .settings-example code {
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 13px;
            color: #d63384;
        }

        .settings-info {
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
            padding: 15px;
        }

        .settings-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .settings-info-item:last-child {
            border-bottom: none;
        }

        .settings-info-label {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .settings-info-value {
            color: #6c757d;
            font-size: 14px;
        }

        /* Button success/error states */
        .laravel-cms-btn.success {
            background: #28a745 !important;
            border-color: #28a745 !important;
        }

        .laravel-cms-btn.error {
            background: #dc3545 !important;
            border-color: #dc3545 !important;
        }

        /* Laravel CMS Component Styles */
        .laravel-cms-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #333;
        }

        .laravel-cms-toggle {
            display: flex;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 2px;
            gap: 2px;
        }

        .laravel-cms-toggle-option {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            color: #6c757d;
        }

        .laravel-cms-toggle-option:hover {
            background: #e9ecef;
            color: #495057;
        }

        .laravel-cms-toggle-option.active {
            background: #007bff;
            color: white;
        }

        .laravel-cms-dropdown {
            position: relative;
            display: inline-block;
        }

        .laravel-cms-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }

        .laravel-cms-dropdown-toggle:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .laravel-cms-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
            max-height: 300px;
            overflow-y: auto;
        }

        .laravel-cms-dropdown.active .laravel-cms-dropdown-menu {
            display: block;
        }

        .laravel-cms-dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 14px;
            border-bottom: 1px solid #f1f3f4;
        }

        .laravel-cms-dropdown-item:last-child {
            border-bottom: none;
        }

        .laravel-cms-dropdown-item:hover {
            background: #f8f9fa;
        }

        .laravel-cms-dropdown-item.active {
            background: #e3f2fd;
            color: #1976d2;
        }

        .template-page {
            flex-direction: column;
            align-items: flex-start !important;
            padding: 8px 14px !important;
        }

        .template-page > span {
            margin-bottom: 6px;
            font-weight: 500;
        }

        .template-post-selector {
            width: 100%;
        }

        .post-selector {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: white;
            font-size: 13px;
            color: #495057;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .post-selector:hover {
            border-color: #007bff;
        }

        .post-selector:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        /* Asset Library Modal Styles */
        .asset-library-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10001;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .asset-library-modal.active {
            display: flex;
        }

        .asset-library-content {
            background: #fff;
            border-radius: 12px;
            width: 95vw;
            height: 90vh;
            max-width: 1400px;
            max-height: 900px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .asset-library-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .asset-library-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #111827;
        }

        .cms-modal-tabs {
            display: flex;
            gap: 8px;
        }

        .asset-library-tab {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            color: #6b7280;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .asset-library-tab.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .asset-library-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #6b7280;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .asset-library-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .cms-modal-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .cms-modal-sidebar {
            width: 280px;
            background: #f9fafb;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
            padding: 20px;
        }

        .cms-modal-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .cms-modal-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: white;
        }

        .cms-assets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .cms-asset-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .cms-asset-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .cms-asset-preview {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
        }

        .cms-asset-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cms-asset-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .cms-asset-item:hover .cms-asset-overlay {
            opacity: 1;
        }

        .cms-asset-quick-actions {
            display: flex;
            gap: 8px;
        }

        .cms-asset-quick-actions button {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 6px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cms-asset-info {
            padding: 12px;
        }

        .cms-asset-name {
            font-size: 14px;
            font-weight: 500;
            margin: 0 0 4px 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cms-asset-meta {
            font-size: 12px;
            color: #6b7280;
            margin: 0;
        }

        .cms-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .cms-select-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cms-select-btn:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }

        .cms-cancel-btn {
            background: none;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
    </style>

    <!-- CMS Toolbar -->
    <div id="cmsToolbar" class="cms-toolbar">
        <div class="cms-toolbar-left">
            <div class="laravel-cms-brand">
                <i class="fas fa-edit"></i>
                <span>CMS</span>
            </div>

            <div class="cms-toolbar-divider"></div>

            <div id="editModeToggle" class="laravel-cms-toggle">
                <div class="laravel-cms-toggle-option active" data-mode="preview">
                    <i class="fas fa-eye"></i> Preview
                </div>
                <div class="laravel-cms-toggle-option" data-mode="edit">
                    <i class="fas fa-edit"></i> Edit
                </div>
            </div>
        </div>

        <div class="cms-toolbar-center">
            <button class="laravel-cms-btn" onclick="openPageSelector()">
                <i class="fas fa-file"></i>
                <span id="currentPageTitle">Home</span>
            </button>

            <div class="cms-toolbar-divider"></div>

            <button class="laravel-cms-btn" onclick="openLanguageSelector()">
                <span id="currentLanguage">🇺🇸 English</span>
            </button>
        </div>

        <div class="cms-toolbar-right">
            <!-- Assets button disabled for now
            <button class="laravel-cms-btn" onclick="openAssetLibrary()" style="display: none;">
                <i class="fas fa-images"></i> Assets
            </button>
            -->

            <div class="cms-toolbar-divider"></div>

            <button class="laravel-cms-btn primary" onclick="cmsEditor.saveChanges()" id="saveBtn">
                <i class="fas fa-save"></i> Save
            </button>

            <button class="laravel-cms-btn" onclick="openSettings()">
                <i class="fas fa-cog"></i>
            </button>
        </div>
    </div>

    <!-- CMS Settings Modal -->
    <div id="cmsSettingsModal" class="cms-settings-modal" style="display: none;">
        <div class="cms-settings-modal-content">
            <div class="asset-library-header">
                <h2><i class="fas fa-cog"></i> CMS Settings</h2>
                <button class="asset-library-close" onclick="closeSettings()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="asset-library-body">
                <div class="settings-tabs">
                    <div class="settings-tab active" data-tab="access">
                        <i class="fas fa-shield-alt"></i> Access Control
                    </div>
                    <div class="settings-tab" data-tab="routes">
                        <i class="fas fa-route"></i> Routes
                    </div>
                    <div class="settings-tab" data-tab="general">
                        <i class="fas fa-cog"></i> General
                    </div>
                </div>

                <div class="settings-tab-content active" id="accessTab">
                    <div class="settings-section">
                        <h3><i class="fas fa-users"></i> User Groups</h3>
                        <p class="settings-description">Control which user groups can access the CMS editor.</p>
                        <div class="settings-input-group">
                            <input type="text" id="newUserGroupInput" placeholder="Enter user group name" />
                            <button type="button" class="laravel-cms-btn" onclick="addUserGroup()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <div id="userGroupsList" class="settings-list"></div>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-globe"></i> IP Restrictions</h3>
                        <p class="settings-description">Limit access to specific IP addresses. Leave empty to allow all IPs.</p>
                        <div class="settings-input-group">
                            <input type="text" id="newIPInput" placeholder="Enter IP address (e.g., 192.168.1.1)" />
                            <button type="button" class="laravel-cms-btn" onclick="addAllowedIP()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <div id="allowedIPsList" class="settings-list"></div>
                    </div>
                </div>

                <div class="settings-tab-content" id="routesTab">
                    <div class="settings-section">
                        <h3><i class="fas fa-ban"></i> Excluded Routes</h3>
                        <p class="settings-description">Pages where the CMS editor should not appear. Use wildcards (*) for pattern matching.</p>
                        <div class="settings-input-group">
                            <input type="text" id="newRouteInput" placeholder="Enter route pattern (e.g., admin/*, api/*)" />
                            <button type="button" class="laravel-cms-btn" onclick="addExcludedRoute()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <div id="excludedRoutesList" class="settings-list"></div>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-info-circle"></i> Examples</h3>
                        <div class="settings-examples">
                            <div class="settings-example">
                                <code>admin/*</code> - Excludes all admin pages
                            </div>
                            <div class="settings-example">
                                <code>api/*</code> - Excludes all API endpoints
                            </div>
                            <div class="settings-example">
                                <code>login</code> - Excludes the login page
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-tab-content" id="generalTab">
                    <div class="settings-section">
                        <h3><i class="fas fa-toggle-on"></i> Auto-Injection</h3>
                        <div class="settings-toggle-group">
                            <label class="settings-toggle">
                                <input type="checkbox" id="autoInjectToggle" checked />
                                <span class="settings-toggle-slider"></span>
                                <span class="settings-toggle-label">Automatically inject CMS editor on all pages</span>
                            </label>
                        </div>
                        <p class="settings-description">When enabled, the CMS editor will automatically appear on all pages (except excluded routes).</p>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-info-circle"></i> System Information</h3>
                        <div class="settings-info">
                            <div class="settings-info-item">
                                <span class="settings-info-label">CMS Version:</span>
                                <span class="settings-info-value">1.0.0</span>
                            </div>
                            <div class="settings-info-item">
                                <span class="settings-info-label">Laravel Version:</span>
                                <span class="settings-info-value">N/A</span>
                            </div>
                            <div class="settings-info-item">
                                <span class="settings-info-label">Current User:</span>
                                <span class="settings-info-value">Guest</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="asset-library-footer">
                <button type="button" class="laravel-cms-btn" onclick="closeSettings()">
                    Cancel
                </button>
                <button type="button" class="laravel-cms-btn primary" id="saveSettingsBtn" onclick="saveSettings()">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Page Selector Modal -->
    <div id="cmsPageModal" class="cms-modal-overlay">
        <div class="cms-selector-modal">
            <div class="cms-modal-header">
                <h3 class="cms-modal-title">
                    <i class="fas fa-file"></i> Select Page
                </h3>
                <button class="cms-modal-close" onclick="closePageSelector()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="cms-modal-search">
                <input type="text" class="cms-search-input" id="pageSearchInput" placeholder="Search pages...">
            </div>
            <div class="cms-modal-content">
                <div class="cms-selector-grid">
                    <div class="cms-selector-item" data-page="home" onclick="selectPage('home', 'Home')">
                        <i class="fas fa-home cms-selector-icon"></i>
                        <span class="cms-selector-label">Home</span>
                    </div>
                    <div class="cms-selector-item" data-page="about" onclick="selectPage('about', 'About')">
                        <i class="fas fa-info-circle cms-selector-icon"></i>
                        <span class="cms-selector-label">About</span>
                    </div>
                    <div class="cms-selector-item" data-page="services" onclick="selectPage('services', 'Services')">
                        <i class="fas fa-cogs cms-selector-icon"></i>
                        <span class="cms-selector-label">Services</span>
                    </div>
                    <div class="cms-selector-item" data-page="portfolio" onclick="selectPage('portfolio', 'Portfolio')">
                        <i class="fas fa-briefcase cms-selector-icon"></i>
                        <span class="cms-selector-label">Portfolio</span>
                    </div>
                    <div class="cms-selector-item" data-page="blog" onclick="selectPage('blog', 'Blog')">
                        <i class="fas fa-blog cms-selector-icon"></i>
                        <span class="cms-selector-label">Blog</span>
                    </div>
                    <div class="cms-selector-item" data-page="contact" onclick="selectPage('contact', 'Contact')">
                        <i class="fas fa-envelope cms-selector-icon"></i>
                        <span class="cms-selector-label">Contact</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Language Selector Modal -->
    <div id="cmsLanguageModal" class="cms-modal-overlay">
        <div class="cms-selector-modal">
            <div class="cms-modal-header">
                <h3 class="cms-modal-title">
                    <i class="fas fa-globe"></i> Select Language
                </h3>
                <button class="cms-modal-close" onclick="closeLanguageSelector()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="cms-modal-search">
                <input type="text" class="cms-search-input" id="languageSearchInput" placeholder="Search languages...">
            </div>
            <div class="cms-modal-content">
                <div class="cms-language-list">
                    <div class="cms-language-item" data-lang="en" onclick="selectLanguage('en', '🇺🇸 English')">
                        <span class="cms-language-flag">🇺🇸</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">English</span>
                            <span class="cms-language-code">en</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="de" onclick="selectLanguage('de', '🇩🇪 Deutsch')">
                        <span class="cms-language-flag">🇩🇪</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">Deutsch</span>
                            <span class="cms-language-code">de</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="fr" onclick="selectLanguage('fr', '🇫🇷 Français')">
                        <span class="cms-language-flag">🇫🇷</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">Français</span>
                            <span class="cms-language-code">fr</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="es" onclick="selectLanguage('es', '🇪🇸 Español')">
                        <span class="cms-language-flag">🇪🇸</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">Español</span>
                            <span class="cms-language-code">es</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="it" onclick="selectLanguage('it', '🇮🇹 Italiano')">
                        <span class="cms-language-flag">🇮🇹</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">Italiano</span>
                            <span class="cms-language-code">it</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="pt" onclick="selectLanguage('pt', '🇵🇹 Português')">
                        <span class="cms-language-flag">🇵🇹</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">Português</span>
                            <span class="cms-language-code">pt</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="ru" onclick="selectLanguage('ru', '🇷🇺 Русский')">
                        <span class="cms-language-flag">🇷🇺</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">Русский</span>
                            <span class="cms-language-code">ru</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="zh" onclick="selectLanguage('zh', '🇨🇳 中文')">
                        <span class="cms-language-flag">🇨🇳</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">中文</span>
                            <span class="cms-language-code">zh</span>
                        </div>
                    </div>
                    <div class="cms-language-item" data-lang="ja" onclick="selectLanguage('ja', '🇯🇵 日本語')">
                        <span class="cms-language-flag">🇯🇵</span>
                        <div class="cms-language-info">
                            <span class="cms-language-name">日本語</span>
                            <span class="cms-language-code">ja</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CMS Scripts -->
    <script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/basic.js"></script>
    <!-- Asset library components disabled for now
    <script src="{$assetPath('js/components/AssetLibrary.js')}?v={$timestamp}"></script>
    <script src="{$assetPath('js/components/DropzoneIntegration.js')}?v={$timestamp}"></script>
    <script src="{$assetPath('js/components/ImageEditor.js')}?v={$timestamp}"></script>
    <script src="{$assetPath('js/components/AssetLibraryTouch.js')}?v={$timestamp}"></script>
    <script src="{$assetPath('js/cms-editor-asset-integration.js')}?v={$timestamp}"></script>
    -->

    <!-- CMS Editor with File Persistence -->
    <script src="{$assetPath('js/cms-editor.js')}"></script>

    <script>
        // Configure CMS API endpoints for external JavaScript
        window.CMS_API_URL = '{$contentUpdateUrl}';
        window.CMS_CSRF_TOKEN = '{$csrfToken}';

        // Initialize CMS functionality
        let isEditMode = false; // Start in preview mode by default
        let assetLibrary = null;
        let currentPage = window.location.pathname;

        // LaravelCMSEditor constructor and methods moved to external cms-editor.js
        // This ensures proper file persistence functionality is loaded

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show CMS toolbar and add body class
            console.log('Initializing CMS toolbar...');
            const toolbar = document.getElementById('cmsToolbar');
            if (toolbar) {
                toolbar.classList.add('active');
                document.body.classList.add('cms-toolbar-active');
                console.log('CMS toolbar activated');
            } else {
                console.error('CMS toolbar element not found!');
            }

            // Initialize edit mode toggle functionality
            initializeEditModeToggle();

            // Show post selector on blog page
            if (currentPage.includes('/blog')) {
                document.getElementById('postSelector').style.display = 'block';
            }

            // Asset library disabled for now
            // Will be re-enabled after content editor is working
            /*
            assetLibrary = new AssetLibrary({
                apiUrl: '{$apiUrl}',
                uploadUrl: '{$uploadUrl}',
                mode: 'multiple',
                allowedTypes: ['image', 'video', 'document'],
                maxFileSize: 10 * 1024 * 1024, // 10MB
                debug: true,
                onSelect: function(assets) {
                    console.log('Selected assets:', assets);
                },
                onUpload: function(asset) {
                    console.log('Uploaded asset:', asset);
                }
            });
            window.assetLibrary = assetLibrary;
            */

            // Initialize in preview mode after external JS loads
            if (window.cmsEditor && window.cmsEditor.setEditMode) {
                window.cmsEditor.setEditMode(false);
            } else {
                // Fallback initialization
                setEditMode(false);
            }

            // Initialize settings modal tabs
            initializeSettingsTabs();

            // Initialize search functionality for modals
            initializeModalSearch();
        });

        // Initialize Modal Search Functionality
        function initializeModalSearch() {
            // Page search
            const pageSearchInput = document.getElementById('pageSearchInput');
            if (pageSearchInput) {
                pageSearchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const items = document.querySelectorAll('#cmsPageModal .cms-selector-item');

                    items.forEach(item => {
                        const label = item.querySelector('.cms-selector-label');
                        if (label) {
                            const text = label.textContent.toLowerCase();
                            item.style.display = text.includes(searchTerm) ? '' : 'none';
                        }
                    });
                });
            }

            // Language search
            const languageSearchInput = document.getElementById('languageSearchInput');
            if (languageSearchInput) {
                languageSearchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const items = document.querySelectorAll('#cmsLanguageModal .cms-language-item');

                    items.forEach(item => {
                        const name = item.querySelector('.cms-language-name');
                        const code = item.querySelector('.cms-language-code');
                        if (name && code) {
                            const text = (name.textContent + ' ' + code.textContent).toLowerCase();
                            item.style.display = text.includes(searchTerm) ? '' : 'none';
                        }
                    });
                });
            }

            // Close modals on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePageSelector();
                    closeLanguageSelector();
                    closeSettings();
                }
            });

            // Close modals on overlay click
            document.querySelectorAll('.cms-modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        if (overlay.id === 'cmsPageModal') closePageSelector();
                        if (overlay.id === 'cmsLanguageModal') closeLanguageSelector();
                        if (overlay.id === 'cmsSettingsModal') closeSettings();
                    }
                });
            });
        }

        // Settings Modal Tab Functionality
        function initializeSettingsTabs() {
            const tabs = document.querySelectorAll('.settings-tab');
            const tabContents = document.querySelectorAll('.settings-tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;

                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));

                    // Add active class to clicked tab
                    this.classList.add('active');

                    // Show corresponding content
                    const targetContent = document.getElementById(targetTab + 'Tab');
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });
        }

        // Edit Mode Toggle Functionality
        function initializeEditModeToggle() {
            const toggleOptions = document.querySelectorAll('#editModeToggle .laravel-cms-toggle-option');

            toggleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const mode = this.dataset.mode;

                    // Update toggle UI
                    toggleOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');

                    // Set edit mode
                    setEditMode(mode === 'edit');
                });
            });
        }

        // Set Edit Mode - Delegated to external cms-editor.js
        function setEditMode(enabled) {
            if (window.cmsEditor && window.cmsEditor.setEditMode) {
                window.cmsEditor.setEditMode(enabled);
            } else {
                console.warn('CMS Editor not yet initialized');
            }
        }

        // Asset Library disabled for now
        function openAssetLibrary() {
            console.log('Asset library is temporarily disabled');
            alert('Asset library is temporarily disabled while we work on the content editor.');
        }

        // Page Selector Modal Functions
        function openPageSelector() {
            const modal = document.getElementById('cmsPageModal');
            if (modal) {
                modal.classList.add('active');
                // Focus search input
                setTimeout(() => {
                    const searchInput = document.getElementById('pageSearchInput');
                    if (searchInput) searchInput.focus();
                }, 100);
            }
        }

        function closePageSelector() {
            const modal = document.getElementById('cmsPageModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        function selectPage(pageId, pageTitle) {
            const currentPageTitle = document.getElementById('currentPageTitle');
            if (currentPageTitle) {
                currentPageTitle.textContent = pageTitle;
            }

            // Update active state
            document.querySelectorAll('#cmsPageModal .cms-selector-item').forEach(item => {
                item.classList.remove('active');
            });
            const selectedItem = document.querySelector(`#cmsPageModal .cms-selector-item[data-page="\${pageId}"]`);
            if (selectedItem) {
                selectedItem.classList.add('active');
            }

            console.log('Page selected:', pageId, pageTitle);
            closePageSelector();

            // Here you can add logic to load the selected page content
        }

        // Language Selector Modal Functions
        function openLanguageSelector() {
            const modal = document.getElementById('cmsLanguageModal');
            if (modal) {
                modal.classList.add('active');
                // Focus search input
                setTimeout(() => {
                    const searchInput = document.getElementById('languageSearchInput');
                    if (searchInput) searchInput.focus();
                }, 100);
            }
        }

        function closeLanguageSelector() {
            const modal = document.getElementById('cmsLanguageModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        function selectLanguage(langCode, langDisplay) {
            const currentLanguage = document.getElementById('currentLanguage');
            if (currentLanguage) {
                currentLanguage.textContent = langDisplay;
            }

            // Update active state
            document.querySelectorAll('#cmsLanguageModal .cms-language-item').forEach(item => {
                item.classList.remove('active');
            });
            const selectedItem = document.querySelector(`#cmsLanguageModal .cms-language-item[data-lang="\${langCode}"]`);
            if (selectedItem) {
                selectedItem.classList.add('active');
            }

            console.log('Language selected:', langCode, langDisplay);
            closeLanguageSelector();

            // Here you can add logic to switch the language
        }

        // Save Changes - Moved to external cms-editor.js for proper file persistence
        // The saveChanges function is now handled by cms-editor.js which includes
        // proper file path and original content tracking for actual file updates

        // Settings Modal
        function openSettings() {
            const modal = document.getElementById('cmsSettingsModal');
            if (modal) {
                modal.style.display = 'flex';
                loadCurrentSettings();
            }
        }

        function closeSettings() {
            const modal = document.getElementById('cmsSettingsModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        async function loadCurrentSettings() {
            try {
                const response = await fetch('/cms/settings/json', {
                    headers: {
                        'X-CSRF-TOKEN': '{$csrfToken}'
                    }
                });

                if (response.ok) {
                    const settings = await response.json();
                    populateSettingsForm(settings);
                }
            } catch (error) {
                console.error('Failed to load settings:', error);
            }
        }

        function populateSettingsForm(settings) {
            // Populate excluded routes
            const excludedRoutes = settings.excluded_routes || [];
            const routesList = document.getElementById('excludedRoutesList');
            routesList.innerHTML = '';
            excludedRoutes.forEach(route => {
                addRouteToList(route);
            });

            // Populate IP settings
            const allowedIPs = settings.allowed_ips || [];
            const ipsList = document.getElementById('allowedIPsList');
            ipsList.innerHTML = '';
            allowedIPs.forEach(ip => {
                addIPToList(ip);
            });

            // Populate user groups
            const allowedGroups = settings.allowed_user_groups || [];
            const groupsList = document.getElementById('userGroupsList');
            groupsList.innerHTML = '';
            allowedGroups.forEach(group => {
                addGroupToList(group);
            });

            // Set auto-inject toggle
            const autoInject = settings.auto_inject !== false; // default to true
            document.getElementById('autoInjectToggle').checked = autoInject;
        }

        function addExcludedRoute() {
            const input = document.getElementById('newRouteInput');
            const route = input.value.trim();
            if (route) {
                addRouteToList(route);
                input.value = '';
            }
        }

        function addRouteToList(route) {
            const list = document.getElementById('excludedRoutesList');
            const item = document.createElement('div');
            item.className = 'settings-list-item';
            item.innerHTML = '<span>' + route + '</span>' +
                '<button type="button" onclick="removeRouteFromList(this)" class="laravel-cms-btn-small">' +
                    '<i class="fas fa-times"></i>' +
                '</button>';
            list.appendChild(item);
        }

        function removeRouteFromList(button) {
            button.parentElement.remove();
        }

        function addAllowedIP() {
            const input = document.getElementById('newIPInput');
            const ip = input.value.trim();
            if (ip) {
                addIPToList(ip);
                input.value = '';
            }
        }

        function addIPToList(ip) {
            const list = document.getElementById('allowedIPsList');
            const item = document.createElement('div');
            item.className = 'settings-list-item';
            item.innerHTML = '<span>' + ip + '</span>' +
                '<button type="button" onclick="removeIPFromList(this)" class="laravel-cms-btn-small">' +
                    '<i class="fas fa-times"></i>' +
                '</button>';
            list.appendChild(item);
        }

        function removeIPFromList(button) {
            button.parentElement.remove();
        }

        function addUserGroup() {
            const input = document.getElementById('newUserGroupInput');
            const group = input.value.trim();
            if (group) {
                addGroupToList(group);
                input.value = '';
            }
        }

        function addGroupToList(group) {
            const list = document.getElementById('userGroupsList');
            const item = document.createElement('div');
            item.className = 'settings-list-item';
            item.innerHTML = '<span>' + group + '</span>' +
                '<button type="button" onclick="removeGroupFromList(this)" class="laravel-cms-btn-small">' +
                    '<i class="fas fa-times"></i>' +
                '</button>';
            list.appendChild(item);
        }

        function removeGroupFromList(button) {
            button.parentElement.remove();
        }

        async function saveSettings() {
            const btn = document.getElementById('saveSettingsBtn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                // Collect settings from form
                const excludedRoutes = Array.from(document.querySelectorAll('#excludedRoutesList .settings-list-item span'))
                    .map(span => span.textContent);

                const allowedIPs = Array.from(document.querySelectorAll('#allowedIPsList .settings-list-item span'))
                    .map(span => span.textContent);

                const allowedGroups = Array.from(document.querySelectorAll('#userGroupsList .settings-list-item span'))
                    .map(span => span.textContent);

                const settings = {
                    excluded_routes: excludedRoutes,
                    allowed_ips: allowedIPs,
                    allowed_user_groups: allowedGroups,
                    auto_inject: document.getElementById('autoInjectToggle').checked
                };

                const response = await fetch('/cms/settings', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{$csrfToken}'
                    },
                    body: JSON.stringify(settings)
                });

                if (response.ok) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Saved!';
                    btn.classList.add('success');

                    setTimeout(() => {
                        btn.innerHTML = originalContent;
                        btn.classList.remove('success');
                        btn.disabled = false;
                        closeSettings();
                    }, 1500);
                } else {
                    throw new Error('Failed to save settings');
                }
            } catch (error) {
                console.error('Settings save error:', error);
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error!';
                btn.classList.add('error');

                setTimeout(() => {
                    btn.innerHTML = originalContent;
                    btn.classList.remove('error');
                    btn.disabled = false;
                }, 3000);
            }
        }

        // Initialize Dropdown Functionality
        function initializeDropdowns() {
            // Page selector dropdown
            const pageSelector = document.getElementById('pageSelector');
            const pageToggle = pageSelector?.querySelector('.laravel-cms-dropdown-toggle');
            const pageItems = pageSelector?.querySelectorAll('.laravel-cms-dropdown-item');

            if (pageToggle) {
                pageToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleDropdown(pageSelector);
                });
            }

            if (pageItems) {
                pageItems.forEach(item => {
                    item.addEventListener('click', function(e) {
                        // Don't handle click if it's on a post selector
                        if (e.target.matches('.post-selector') || e.target.closest('.template-post-selector')) {
                            e.stopPropagation();
                            return;
                        }

                        e.preventDefault();
                        e.stopPropagation();

                        const pageName = this.querySelector('span').textContent;
                        const currentPageTitle = document.getElementById('currentPageTitle');
                        if (currentPageTitle) {
                            currentPageTitle.textContent = pageName;
                        }
                        pageSelector.classList.remove('active');
                        console.log('Page selected:', pageName);
                    });

                    // Handle post selector changes for template pages
                    const postSelector = item.querySelector('.post-selector');
                    if (postSelector) {
                        postSelector.addEventListener('change', function(e) {
                            e.stopPropagation();
                            const selectedPost = this.options[this.selectedIndex].text;
                            const templateType = this.dataset.template;

                            console.log('Template ' + templateType + ' - Post selected:', selectedPost);

                            // Update page title to show template + post
                            const currentPageTitle = document.getElementById('currentPageTitle');
                            if (currentPageTitle && selectedPost && this.value) {
                                currentPageTitle.textContent = item.querySelector('span').textContent + ': ' + selectedPost;
                            }

                            // Close dropdown after selection
                            pageSelector.classList.remove('active');
                        });

                        postSelector.addEventListener('click', function(e) {
                            e.stopPropagation();
                        });
                    }
                });
            }

            // Language selector dropdown
            const languageSelector = document.getElementById('languageSelector');
            const languageToggle = languageSelector?.querySelector('.laravel-cms-dropdown-toggle');
            const languageItems = languageSelector?.querySelectorAll('.laravel-cms-dropdown-item');

            if (languageToggle) {
                languageToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleDropdown(languageSelector);
                });
            }

            if (languageItems) {
                languageItems.forEach(item => {
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const language = this.textContent;
                        const currentLanguage = document.getElementById('currentLanguage');
                        if (currentLanguage) {
                            currentLanguage.textContent = language;
                        }
                        languageSelector.classList.remove('active');
                        console.log('Language selected:', language);
                    });
                });
            }
        }

        function toggleDropdown(dropdown) {
            // Close all other dropdowns
            document.querySelectorAll('.laravel-cms-dropdown').forEach(dd => {
                if (dd !== dropdown) {
                    dd.classList.remove('active');
                }
            });

            // Toggle the clicked dropdown
            dropdown.classList.toggle('active');
        }

        // Initialize dropdowns
        initializeDropdowns();

        // Post Selection
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('laravel-cms-dropdown-item') && e.target.closest('#postSelector')) {
                const postTitle = e.target.textContent;
                document.getElementById('currentPostTitle').textContent = postTitle;
                document.getElementById('postSelector').classList.remove('active');

                // Here you would load the selected post content
                console.log('Loading post:', postTitle);
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.laravel-cms-dropdown')) {
                document.querySelectorAll('.laravel-cms-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });
    </script>
    <!-- End Laravel CMS -->
HTML;
    }
}