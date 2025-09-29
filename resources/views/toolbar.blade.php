{{-- Toast Notification Container --}}
<div id="cms-toast-container" class="cms-toast-container"></div>

<div id="cms-toolbar" class="cms-toolbar">
    <div class="cms-toolbar-container">
        {{-- Left Section: Edit/Preview Mode Toggle --}}
        <div class="cms-toolbar-section cms-toolbar-left">
            <div class="cms-mode-toggle">
                <button class="cms-btn cms-btn-mode cms-btn-edit" data-mode="edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span>Edit</span>
                </button>
                <button class="cms-btn cms-btn-mode cms-btn-preview active" data-mode="preview">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>Preview</span>
                </button>
            </div>
        </div>

        {{-- Middle Section: Pages/Languages --}}
        <div class="cms-toolbar-section cms-toolbar-middle">
            <button class="cms-btn cms-btn-pages" data-modal="pages">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <span>Pages</span>
            </button>

            <div class="cms-separator cms-languages-separator" style="display: none;"></div>

            <button class="cms-btn cms-btn-languages" data-modal="languages" style="display: none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <span>Languages</span>
            </button>

            {{-- Template Selector (dynamically shown) --}}
            <div class="cms-template-selector" style="display: none;">
                <div class="cms-separator"></div>
                <select class="cms-select cms-template-select">
                    <option>Select Item...</option>
                </select>
            </div>
        </div>

        {{-- Right Section: Asset Library, Save, and Settings --}}
        <div class="cms-toolbar-section cms-toolbar-right">
            <button class="cms-btn cms-btn-assets" data-modal="assets">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                <span>Asset Library</span>
            </button>

            <button class="cms-btn cms-btn-save cms-btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                <span>Save</span>
            </button>

            <button class="cms-btn cms-btn-settings" data-modal="settings">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v6m0 6v6m4.22-13.22l4.24 4.24M1.54 1.54l4.24 4.24M21 12h-6m-6 0H3m13.22 4.22l4.24 4.24M1.54 20.46l4.24-4.24"></path>
                </svg>
            </button>
        </div>
    </div>
</div>

{{-- Modal Container --}}
<div id="cms-modal-container" class="cms-modal-container" style="display: none;">
    <div class="cms-modal-backdrop"></div>

    {{-- Pages Modal --}}
    <div class="cms-modal cms-modal-pages" data-modal="pages" style="display: none;">
        <div class="cms-modal-header">
            <h2>Pages</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-pages-search">
                <input type="text" class="cms-input" placeholder="Search pages..." id="cms-pages-search">
            </div>
            <div class="cms-pages-list" id="cms-pages-list">
                <div class="cms-loading">Loading pages...</div>
            </div>
        </div>
    </div>

    {{-- Languages Modal --}}
    <div class="cms-modal cms-modal-languages" data-modal="languages" style="display: none;">
        <div class="cms-modal-header">
            <h2>Languages</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-languages-list" id="cms-languages-list">
                <div class="cms-loading">Loading languages...</div>
            </div>
        </div>
    </div>

    {{-- Settings Modal --}}
    <div class="cms-modal cms-modal-settings" data-modal="settings" style="display: none;">
        <div class="cms-modal-header">
            <h2>CMS Settings</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-settings-form">
                <div class="cms-setting-group">
                    <label class="cms-label">
                        <input type="checkbox" id="cms-setting-enabled" checked>
                        <span>Enable CMS</span>
                    </label>
                </div>
                <div class="cms-setting-group">
                    <label class="cms-label">
                        <input type="checkbox" id="cms-setting-toolbar" checked>
                        <span>Show Toolbar</span>
                    </label>
                </div>
                <div class="cms-setting-group">
                    <label class="cms-label">Toolbar Position</label>
                    <select class="cms-select" id="cms-setting-position">
                        <option value="bottom">Bottom</option>
                        <option value="top">Top</option>
                    </select>
                </div>
                <div class="cms-setting-group">
                    <label class="cms-label">Theme</label>
                    <select class="cms-select" id="cms-setting-theme">
                        <option value="dark">Dark</option>
                        <option value="light">Light</option>
                    </select>
                </div>

                <div class="cms-setting-section">
                    <h3 class="cms-setting-section-title">Route Exclusions</h3>

                    <div class="cms-setting-group">
                        <label class="cms-label">Excluded Prefixes</label>
                        <div class="cms-tag-input">
                            <div class="cms-tags" id="cms-excluded-prefixes">
                                <span class="cms-tag">admin <button class="cms-tag-remove" data-value="admin">&times;</button></span>
                                <span class="cms-tag">api <button class="cms-tag-remove" data-value="api">&times;</button></span>
                                <span class="cms-tag">telescope <button class="cms-tag-remove" data-value="telescope">&times;</button></span>
                                <span class="cms-tag">horizon <button class="cms-tag-remove" data-value="horizon">&times;</button></span>
                            </div>
                            <input type="text" class="cms-input cms-tag-input-field"
                                   id="cms-add-prefix"
                                   placeholder="Add prefix (e.g., admin)">
                        </div>
                        <small class="cms-help-text">Routes starting with these prefixes won't show the CMS toolbar</small>
                    </div>

                    <div class="cms-setting-group">
                        <label class="cms-label">Excluded Route Patterns</label>
                        <div class="cms-tag-input">
                            <div class="cms-tags" id="cms-excluded-routes"></div>
                            <input type="text" class="cms-input cms-tag-input-field"
                                   id="cms-add-route"
                                   placeholder="Add route pattern (e.g., admin/*, login)">
                        </div>
                        <small class="cms-help-text">Use * as wildcard. Example: admin/* excludes all admin routes</small>
                    </div>

                    <div class="cms-setting-group">
                        <label class="cms-label">Excluded Route Names</label>
                        <div class="cms-tag-input">
                            <div class="cms-tags" id="cms-excluded-names"></div>
                            <input type="text" class="cms-input cms-tag-input-field"
                                   id="cms-add-name"
                                   placeholder="Add route name (e.g., admin.dashboard)">
                        </div>
                        <small class="cms-help-text">Exact route names to exclude</small>
                    </div>
                </div>

                <div class="cms-setting-group">
                    <button class="cms-btn cms-btn-primary" id="cms-save-settings">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Asset Library Modal --}}
    <div class="cms-modal cms-modal-assets" data-modal="assets" style="display: none;">
        <div class="cms-modal-header">
            <h2>Asset Library</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-media-library">
                {{-- Sidebar with folders --}}
                <div class="cms-media-sidebar">
                    <div class="cms-media-sidebar-header">
                        <h3>Folders</h3>
                        <button class="cms-btn-icon cms-btn-create-folder" title="Create Folder">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                <line x1="12" y1="11" x2="12" y2="17"></line>
                                <line x1="9" y1="14" x2="15" y2="14"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="cms-media-folders" id="cms-media-folders">
                        <div class="cms-media-folder active" data-folder-id="0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <span>All Media</span>
                        </div>
                    </div>
                </div>

                {{-- Main content area --}}
                <div class="cms-media-main">
                    <div class="cms-media-toolbar">
                        <div class="cms-media-search">
                            <input type="text" class="cms-input" placeholder="Search media..." id="cms-media-search">
                        </div>
                        <div class="cms-media-actions">
                            <button class="cms-btn cms-btn-primary cms-btn-upload-media">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                Upload
                            </button>
                            <input type="file" id="cms-media-file-input" multiple accept="image/*" style="display: none;">
                        </div>
                    </div>

                    <div class="cms-media-grid" id="cms-media-grid">
                        <div class="cms-media-loading">Loading media...</div>
                    </div>

                    <div class="cms-media-selected-info" id="cms-media-selected-info" style="display: none;">
                        <div class="cms-media-selected-details">
                            <span id="cms-media-selected-count">0 selected</span>
                            <button class="cms-btn cms-btn-sm" id="cms-media-deselect">Deselect All</button>
                        </div>
                        <button class="cms-btn cms-btn-primary" id="cms-media-insert">Insert Selected</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Folder Modal --}}
    <div class="cms-modal cms-modal-create-folder" data-modal="create-folder" style="display: none;">
        <div class="cms-modal-header">
            <h2>Create Folder</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-form-group">
                <label class="cms-label">Folder Name</label>
                <input type="text" class="cms-input" id="cms-new-folder-name" placeholder="Enter folder name">
            </div>
            <div class="cms-form-group">
                <label class="cms-label">Parent Folder</label>
                <select class="cms-select" id="cms-parent-folder">
                    <option value="0">Root</option>
                </select>
            </div>
            <div class="cms-form-actions">
                <button class="cms-btn" id="cms-cancel-folder">Cancel</button>
                <button class="cms-btn cms-btn-primary" id="cms-create-folder">Create</button>
            </div>
        </div>
    </div>

    {{-- Link Editor Modal --}}
    <div class="cms-modal cms-modal-link-editor" data-modal="link-editor" style="display: none;">
        <div class="cms-modal-header">
            <h2>Edit Link</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-link-editor-form">
                <div class="cms-setting-group">
                    <label class="cms-label">Link Text</label>
                    <input type="text" class="cms-input" id="cms-link-text" placeholder="Button or link text...">
                </div>
                <div class="cms-setting-group">
                    <label class="cms-label">URL (href)</label>
                    <input type="text" class="cms-input" id="cms-link-href" placeholder="https://example.com or /page">
                </div>
                <div class="cms-setting-group">
                    <label class="cms-label">
                        <input type="checkbox" id="cms-link-new-tab">
                        <span>Open in new tab</span>
                    </label>
                </div>
                <div class="cms-setting-group">
                    <button class="cms-btn cms-btn-primary" id="cms-save-link">Save Link</button>
                    <button class="cms-btn" id="cms-cancel-link">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Image Editor Modal --}}
    <div class="cms-modal cms-modal-image-editor" data-modal="image-editor" style="display: none;">
        <div class="cms-modal-header">
            <h2>Change Image</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-image-editor-tabs">
                <button class="cms-tab-btn active" data-tab="upload">Upload New</button>
                <button class="cms-tab-btn" data-tab="library">Media Library</button>
            </div>

            <div class="cms-tab-content" data-tab="upload">
                <div class="cms-dropzone" id="cms-image-dropzone">
                    <div class="cms-dropzone-inner">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <p class="cms-dropzone-text">Drop image here or click to upload</p>
                        <p class="cms-dropzone-subtext">JPG, PNG, GIF, SVG, WebP (Max 10MB)</p>
                        <input type="file" id="cms-image-upload" accept="image/*" style="display: none;">
                    </div>
                </div>

                <div class="cms-image-preview" id="cms-image-preview" style="display: none;">
                    <img id="cms-preview-img" src="" alt="Preview">
                    <button class="cms-btn cms-btn-small" id="cms-change-image">Change Image</button>
                </div>

                <div class="cms-setting-group">
                    <label class="cms-label">Alt Text</label>
                    <input type="text" class="cms-input" id="cms-image-alt" placeholder="Describe the image for accessibility...">
                </div>

                <div class="cms-setting-group">
                    <label class="cms-label">Title</label>
                    <input type="text" class="cms-input" id="cms-image-title" placeholder="Image title (optional)">
                </div>
            </div>

            <div class="cms-tab-content" data-tab="library" style="display: none;">
                <button class="cms-btn cms-btn-primary" onclick="window.openModal('assets'); window.CMS.selectingImageForEditor = true;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    Open Media Library
                </button>
                <p style="margin-top: 10px; color: #999; font-size: 13px;">Browse and select from previously uploaded images</p>
            </div>

            <div class="cms-setting-group cms-image-actions">
                <button class="cms-btn cms-btn-primary" id="cms-save-image" disabled>Save Changes</button>
                <button class="cms-btn" id="cms-cancel-image">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
    .cms-toolbar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #1a1a1a;
        border-top: 1px solid #333;
        z-index: 999999;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        font-size: 14px;
        color: #e0e0e0;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.5);
    }

    .cms-toolbar * {
        box-sizing: border-box;
    }

    .cms-toolbar-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 56px;
        padding: 0 20px;
        max-width: 100%;
        margin: 0 auto;
    }

    .cms-toolbar-section {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cms-toolbar-left {
        flex: 0 0 auto;
    }

    .cms-toolbar-middle {
        flex: 0 0 auto;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cms-toolbar-right {
        flex: 0 0 auto;
        margin-left: auto;
    }

    .cms-mode-toggle {
        display: flex;
        background: #2a2a2a;
        border-radius: 6px;
        padding: 3px;
        gap: 4px;
    }

    .cms-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: transparent;
        color: #b0b0b0;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        outline: none;
    }

    .cms-btn:hover {
        background: #2a2a2a;
        color: #fff;
    }

    .cms-btn:active {
        transform: scale(0.98);
    }

    .cms-btn svg {
        flex-shrink: 0;
    }

    .cms-btn-mode {
        padding: 6px 12px;
    }

    .cms-btn-mode.active {
        background: #404040;
        color: #fff;
    }

    .cms-btn-primary {
        background: #0066ff;
        color: #fff;
    }

    .cms-btn-primary:hover {
        background: #0052d4;
        color: #fff;
    }

    .cms-btn-settings {
        padding: 8px;
    }

    .cms-separator {
        width: 1px;
        height: 24px;
        background: #333;
        margin: 0 5px;
    }

    /* Template Selector */
    .cms-template-selector {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cms-select {
        background: #2a2a2a;
        color: #e0e0e0;
        border: 1px solid #404040;
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 13px;
        outline: none;
        cursor: pointer;
    }

    .cms-select:focus {
        border-color: #0066ff;
    }

    /* Modal Styles */
    .cms-modal-container {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000000;
    }

    .cms-modal-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(2px);
    }

    .cms-modal {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #1e1e1e;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        width: 90%;
        max-width: 800px;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        color: #e0e0e0;
    }

    /* Wider modals for image editor and asset library */
    .cms-modal-assets,
    .cms-modal-image-editor {
        max-width: 1200px;
    }

    /* Toast Notification Styles */
    .cms-toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 999999;
        pointer-events: none;
    }

    .cms-toast {
        background: #2a2a2a;
        color: #fff;
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        gap: 12px;
        pointer-events: auto;
        animation: slideIn 0.3s ease-out;
        position: relative;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .cms-toast.removing {
        animation: slideOut 0.3s ease-out forwards;
    }

    .cms-toast-icon {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
    }

    .cms-toast-content {
        flex: 1;
    }

    .cms-toast-title {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .cms-toast-message {
        font-size: 14px;
        opacity: 0.9;
    }

    .cms-toast-close {
        position: absolute;
        top: 8px;
        right: 8px;
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 4px;
        line-height: 1;
        font-size: 18px;
    }

    .cms-toast-close:hover {
        color: #fff;
    }

    /* Toast variants */
    .cms-toast-success {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    }

    .cms-toast-error {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    }

    .cms-toast-warning {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    }

    .cms-toast-info {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }

    .cms-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        border-bottom: 1px solid #333;
    }

    .cms-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #fff;
    }

    .cms-modal-close {
        background: none;
        border: none;
        color: #888;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .cms-modal-close:hover {
        background: #2a2a2a;
        color: #fff;
    }

    .cms-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    /* Pages Modal */
    .cms-pages-search {
        margin-bottom: 20px;
    }

    .cms-input {
        width: 100%;
        background: #2a2a2a;
        border: 1px solid #404040;
        border-radius: 4px;
        padding: 10px;
        color: #e0e0e0;
        font-size: 14px;
        outline: none;
    }

    .cms-input:focus {
        border-color: #0066ff;
    }

    .cms-pages-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cms-page-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: #2a2a2a;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .cms-page-item:hover {
        background: #333;
    }

    .cms-page-item.active {
        background: #0066ff;
    }

    .cms-page-item-title {
        font-weight: 500;
    }

    .cms-page-item-path {
        font-size: 12px;
        color: #888;
        margin-top: 2px;
    }

    .cms-page-item.template {
        border-left: 3px solid #f0b90b;
    }

    /* Languages Modal */
    .cms-languages-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cms-language-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: #2a2a2a;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .cms-language-item:hover {
        background: #333;
    }

    .cms-language-item.active {
        background: #0066ff;
    }

    .cms-language-name {
        font-weight: 500;
    }

    .cms-language-code {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
    }

    /* Settings Modal */
    .cms-settings-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .cms-setting-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cms-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #e0e0e0;
        cursor: pointer;
    }

    .cms-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    /* Loading State */
    .cms-loading {
        text-align: center;
        padding: 40px;
        color: #888;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .cms-modal {
            width: 95%;
            max-height: 90vh;
        }

        .cms-toolbar-middle {
            position: static;
            transform: none;
        }

        .cms-template-selector {
            display: none !important;
        }
    }

    /* Image Editor Modal Styles */
    .cms-image-editor-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid #444;
    }

    .cms-tab-btn {
        background: transparent;
        border: none;
        color: #999;
        padding: 10px 20px;
        cursor: pointer;
        font-size: 14px;
        position: relative;
        transition: all 0.2s;
    }

    .cms-tab-btn:hover {
        color: #fff;
    }

    .cms-tab-btn.active {
        color: #0066ff;
    }

    .cms-tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: #0066ff;
    }

    .cms-tab-content {
        min-height: 200px;
    }

    .cms-dropzone {
        border: 2px dashed #444;
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: #1a1a1a;
        margin-bottom: 20px;
    }

    .cms-dropzone:hover {
        border-color: #0066ff;
        background: #222;
    }

    .cms-dropzone.dragover {
        border-color: #0066ff;
        background: rgba(0, 102, 255, 0.1);
    }

    .cms-dropzone-inner svg {
        color: #666;
        margin-bottom: 12px;
    }

    .cms-dropzone-text {
        color: #e0e0e0;
        font-size: 16px;
        margin: 10px 0 5px 0;
    }

    .cms-dropzone-subtext {
        color: #999;
        font-size: 13px;
        margin: 0;
    }

    .cms-image-preview {
        text-align: center;
        margin-bottom: 20px;
        padding: 20px;
        background: #1a1a1a;
        border-radius: 8px;
    }

    .cms-image-preview img {
        max-width: 100%;
        max-height: 300px;
        border-radius: 4px;
        margin-bottom: 15px;
    }

    .cms-btn-small {
        padding: 6px 12px;
        font-size: 13px;
    }

    .cms-media-library-placeholder {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .cms-media-library-placeholder svg {
        color: #666;
        margin-bottom: 20px;
    }

    .cms-media-library-placeholder h3 {
        color: #e0e0e0;
        margin: 20px 0 10px 0;
    }

    .cms-media-library-placeholder p {
        margin: 0;
        font-size: 14px;
    }

    .cms-image-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #444;
    }

    /* Tag input styles */
    .cms-tag-input {
        margin-top: 8px;
    }

    .cms-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
        min-height: 32px;
    }

    .cms-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #555;
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 13px;
    }

    .cms-tag-remove {
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 0;
        font-size: 18px;
        line-height: 1;
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cms-tag-remove:hover {
        color: #fff;
    }

    .cms-tag-input-field {
        width: 100%;
        background: #444;
        color: #fff;
        border: 1px solid #555;
        padding: 8px 12px;
        border-radius: 4px;
    }

    .cms-help-text {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #999;
    }

    /* Media Library Styles */
    .cms-media-library {
        display: flex;
        height: 500px;
    }

    .cms-media-sidebar {
        width: 200px;
        border-right: 1px solid #444;
        overflow-y: auto;
    }

    .cms-media-sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #444;
    }

    .cms-media-sidebar-header h3 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }

    .cms-btn-icon {
        background: transparent;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cms-btn-icon:hover {
        color: #fff;
    }

    .cms-media-folders {
        padding: 10px;
    }

    .cms-media-folder {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 4px;
        cursor: pointer;
        margin-bottom: 4px;
    }

    .cms-media-folder:hover {
        background: #444;
    }

    .cms-media-folder.active {
        background: #007bff;
        color: #fff;
    }

    .cms-media-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .cms-media-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #444;
    }

    .cms-media-search input {
        width: 250px;
    }

    .cms-media-grid {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 15px;
    }

    .cms-media-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 4px;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.2s;
    }

    .cms-media-item:hover {
        border-color: #666;
    }

    .cms-media-item.selected {
        border-color: #007bff;
    }

    .cms-media-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cms-media-item-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.7) 100%);
        opacity: 0;
        transition: opacity 0.2s;
    }

    .cms-media-item:hover .cms-media-item-overlay {
        opacity: 1;
    }

    .cms-media-item-info {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 8px;
        color: #fff;
        font-size: 11px;
        transform: translateY(100%);
        transition: transform 0.2s;
    }

    .cms-media-item:hover .cms-media-item-info {
        transform: translateY(0);
    }

    .cms-media-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 200px;
        color: #999;
    }

    .cms-media-selected-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-top: 1px solid #444;
        background: #2a2a2a;
    }

    .cms-media-selected-details {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .cms-form-group {
        margin-bottom: 15px;
    }

    .cms-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .cms-select {
        width: 100%;
        background: #444;
        color: #fff;
        border: 1px solid #555;
        padding: 8px 12px;
        border-radius: 4px;
    }

    .cms-btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }
</style>

<script>
    (function() {
        'use strict';

        // CMS object
        window.CMS = window.CMS || {};

        document.addEventListener('DOMContentLoaded', function() {
            const toolbar = document.getElementById('cms-toolbar');
            const modalContainer = document.getElementById('cms-modal-container');
            if (!toolbar || !modalContainer) return;

            // Get base URL
            const baseUrl = window.location.protocol + '//' + window.location.host;
            const apiBaseUrl = baseUrl + '/api/cms';

            // State
            let currentPage = window.location.pathname;
            let currentLanguage = 'en';
            let availableLanguages = [];
            let availablePages = [];
            let templatePages = [];
            let pendingChanges = [];

            // Initialize
            init();

            function init() {
                // Set default mode to preview
                window.CMS.mode = 'preview';
                document.body.classList.remove('cms-edit-mode');

                loadLanguages();
                loadPages();
                setupEventListeners();
                setupToastSystem();
            }

            // Toast Notification System
            function setupToastSystem() {
                window.showToast = function(message, type = 'info', title = null) {
                    const container = document.getElementById('cms-toast-container');
                    if (!container) return;

                    const toast = document.createElement('div');
                    toast.className = `cms-toast cms-toast-${type}`;

                    // Icons for different types
                    const icons = {
                        success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
                        error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
                        warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                        info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
                    };

                    // Default titles
                    const defaultTitles = {
                        success: 'Success',
                        error: 'Error',
                        warning: 'Warning',
                        info: 'Info'
                    };

                    const finalTitle = title || defaultTitles[type] || 'Notification';

                    toast.innerHTML = `
                        <div class="cms-toast-icon">
                            ${icons[type] || icons.info}
                        </div>
                        <div class="cms-toast-content">
                            <div class="cms-toast-title">${finalTitle}</div>
                            <div class="cms-toast-message">${message}</div>
                        </div>
                        <button class="cms-toast-close">&times;</button>
                    `;

                    // Add close functionality
                    const closeBtn = toast.querySelector('.cms-toast-close');
                    closeBtn.addEventListener('click', function() {
                        removeToast(toast);
                    });

                    container.appendChild(toast);

                    // Auto remove after 5 seconds
                    setTimeout(() => {
                        removeToast(toast);
                    }, 5000);
                };

                function removeToast(toast) {
                    if (!toast || toast.classList.contains('removing')) return;

                    toast.classList.add('removing');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }
            }

            // Event Listeners
            function setupEventListeners() {
                // Mode toggle
                const modeButtons = toolbar.querySelectorAll('.cms-btn-mode');
                modeButtons.forEach(btn => {
                    btn.addEventListener('click', function() {
                        modeButtons.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        const mode = this.dataset.mode;
                        window.CMS.mode = mode;

                        // Dispatch mode change event
                        const event = new CustomEvent('cms:modeChanged', {
                            detail: { mode: mode }
                        });
                        document.dispatchEvent(event);

                        // Update body class
                        if (mode === 'edit') {
                            document.body.classList.add('cms-edit-mode');
                        } else {
                            document.body.classList.remove('cms-edit-mode');
                        }

                        console.log('CMS Mode:', mode);
                    });
                });

                // Modal triggers
                document.querySelectorAll('[data-modal]').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const modalName = this.dataset.modal;
                        openModal(modalName);
                    });
                });

                // Modal close buttons
                document.querySelectorAll('.cms-modal-close').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeModal();
                    });
                });

                // Backdrop click
                document.querySelector('.cms-modal-backdrop')?.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeModal();
                });

                // Save button
                toolbar.querySelector('.cms-btn-save')?.addEventListener('click', function() {
                    saveAllChanges();
                });

                // Settings save
                document.getElementById('cms-save-settings')?.addEventListener('click', saveSettings);

                // Setup exclusion tag inputs
                setupExclusionTagInputs();

                // Pages search
                document.getElementById('cms-pages-search')?.addEventListener('input', filterPages);

                // Link editor handlers
                document.addEventListener('cms:openLinkEditor', handleOpenLinkEditor);
                document.getElementById('cms-save-link')?.addEventListener('click', saveLinkChanges);
                document.getElementById('cms-cancel-link')?.addEventListener('click', () => closeModal());

                // Image editor handlers
                document.addEventListener('cms:openImageEditor', handleOpenImageEditor);
                document.getElementById('cms-save-image')?.addEventListener('click', saveImageChanges);
                document.getElementById('cms-cancel-image')?.addEventListener('click', () => closeModal());
                setupImageDropzone();

                // Media library handlers
                setupMediaLibrary();

                // Content change handler
                document.addEventListener('cms:contentChanged', handleContentChanged);
            }

            // Load Languages
            function loadLanguages() {
                fetch(apiBaseUrl + '/languages')
                    .then(response => response.json())
                    .then(data => {
                        availableLanguages = data.available;
                        currentLanguage = data.current;

                        // Show/hide languages button
                        const langBtn = toolbar.querySelector('.cms-btn-languages');
                        const langSeparator = toolbar.querySelector('.cms-languages-separator');

                        if (data.multilingual) {
                            langBtn.style.display = '';
                            langSeparator.style.display = '';
                        }

                        renderLanguages();
                    })
                    .catch(error => {
                        console.error('Failed to load languages:', error);
                    });
            }

            // Load Pages
            function loadPages() {
                fetch(apiBaseUrl + '/pages')
                    .then(response => response.json())
                    .then(data => {
                        availablePages = data.pages || [];
                        templatePages = data.templates || [];
                        currentPage = data.current_path;

                        renderPages();
                        checkForTemplatePages();
                    })
                    .catch(error => {
                        console.error('Failed to load pages:', error);
                    });
            }

            // Render Languages
            function renderLanguages() {
                const list = document.getElementById('cms-languages-list');
                if (!list) return;

                if (availableLanguages.length === 0) {
                    list.innerHTML = '<div class="cms-loading">No languages available</div>';
                    return;
                }

                list.innerHTML = availableLanguages.map(lang => `
                    <div class="cms-language-item ${lang.active ? 'active' : ''}" data-lang="${lang.code}">
                        <div>
                            <div class="cms-language-name">${lang.native_name}</div>
                            <div class="cms-language-code">${lang.code}</div>
                        </div>
                        ${lang.active ? '<span>✓</span>' : ''}
                    </div>
                `).join('');

                // Add click handlers
                list.querySelectorAll('.cms-language-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const langCode = this.dataset.lang;
                        switchLanguage(langCode);
                    });
                });
            }

            // Render Pages
            function renderPages() {
                const list = document.getElementById('cms-pages-list');
                if (!list) return;

                const allPages = [...availablePages, ...templatePages];

                if (allPages.length === 0) {
                    list.innerHTML = '<div class="cms-loading">No pages available</div>';
                    return;
                }

                list.innerHTML = allPages.map(page => {
                    // Filter out storage routes
                    if (page.path && page.path.includes('/storage/')) {
                        return '';
                    }

                    return `
                    <div class="cms-page-item ${page.is_template ? 'template' : ''} ${page.path === currentPage ? 'active' : ''}"
                         data-path="${page.path}"
                         data-template="${page.is_template}">
                        <div>
                            <div class="cms-page-item-title">${page.title}</div>
                            <div class="cms-page-item-path">${page.path}</div>
                        </div>
                        ${page.is_template ? '<span>Template</span>' : ''}
                    </div>`;
                }).join('');

                // Add click handlers
                list.querySelectorAll('.cms-page-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const path = this.dataset.path;
                        const isTemplate = this.dataset.template === 'true';

                        if (isTemplate) {
                            showTemplateSelector(path);
                        } else {
                            navigateToPage(path);
                        }
                    });
                });
            }

            // Filter Pages
            function filterPages(e) {
                const search = e.target.value.toLowerCase();
                const items = document.querySelectorAll('.cms-page-item');

                items.forEach(item => {
                    const title = item.querySelector('.cms-page-item-title').textContent.toLowerCase();
                    const path = item.querySelector('.cms-page-item-path').textContent.toLowerCase();

                    if (title.includes(search) || path.includes(search)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            // Check for Template Pages
            function checkForTemplatePages() {
                // Check if current page matches a template
                templatePages.forEach(template => {
                    const pattern = template.uri.replace(/\{[^}]+\}/g, '([^/]+)');
                    const regex = new RegExp('^' + pattern + '$');

                    if (regex.test(currentPage)) {
                        showTemplateSelector(template.uri);
                    }
                });
            }

            // Show Template Selector
            function showTemplateSelector(templatePath) {
                const selector = toolbar.querySelector('.cms-template-selector');
                const select = toolbar.querySelector('.cms-template-select');

                if (!selector || !select) return;

                // Find template
                const template = templatePages.find(t => t.uri === templatePath);
                if (!template) return;

                selector.style.display = '';

                // Populate select
                select.innerHTML = '<option>Select ' + template.title + '...</option>';

                if (template.sample_items) {
                    template.sample_items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.value;
                        option.textContent = item.title;
                        select.appendChild(option);
                    });
                }

                // Add change handler
                select.addEventListener('change', function() {
                    if (this.value) {
                        const url = templatePath.replace(/\{[^}]+\}/, this.value);
                        navigateToPage('/' + url);
                    }
                });

                closeModal();
            }

            // Navigate to Page
            function navigateToPage(path) {
                console.log('Navigating to:', path);
                window.location.href = path;
            }

            // Switch Language
            function switchLanguage(langCode) {
                console.log('Switching language to:', langCode);
                // This would typically update the locale in the session or URL
                // For now, just close the modal
                closeModal();
            }

            // Open Modal
            function openModal(modalName) {
                modalContainer.style.display = '';

                // Hide all modals first
                document.querySelectorAll('.cms-modal').forEach(m => {
                    m.style.display = 'none';
                });

                // Show specific modal
                const modal = document.querySelector(`.cms-modal-${modalName}`);
                if (modal) {
                    modal.style.display = '';

                    // Load data if needed
                    if (modalName === 'settings') {
                        loadSettings();
                    }
                }
            }

            // Close Modal
            function closeModal() {
                modalContainer.style.display = 'none';
                document.querySelectorAll('.cms-modal').forEach(m => {
                    m.style.display = 'none';
                });
            }

            // Load Settings
            function loadSettings() {
                fetch(apiBaseUrl + '/settings')
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('cms-setting-enabled').checked = data.enabled;
                        document.getElementById('cms-setting-toolbar').checked = data.toolbar.enabled;
                        document.getElementById('cms-setting-position').value = data.toolbar.position || 'bottom';
                        document.getElementById('cms-setting-theme').value = data.toolbar.theme || 'dark';
                    })
                    .catch(error => {
                        console.error('Failed to load settings:', error);
                    });
            }

            // Setup exclusion tag inputs
            function setupExclusionTagInputs() {
                // Load current exclusions
                loadExclusions();

                // Setup tag input handlers for prefixes
                const prefixInput = document.getElementById('cms-add-prefix');
                const prefixTags = document.getElementById('cms-excluded-prefixes');

                if (prefixInput) {
                    prefixInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const value = this.value.trim();
                            if (value) {
                                addTag(prefixTags, value);
                                this.value = '';
                            }
                        }
                    });
                }

                // Setup tag input handlers for route patterns
                const routeInput = document.getElementById('cms-add-route');
                const routeTags = document.getElementById('cms-excluded-routes');

                if (routeInput) {
                    routeInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const value = this.value.trim();
                            if (value) {
                                addTag(routeTags, value);
                                this.value = '';
                            }
                        }
                    });
                }

                // Setup tag input handlers for route names
                const nameInput = document.getElementById('cms-add-name');
                const nameTags = document.getElementById('cms-excluded-names');

                if (nameInput) {
                    nameInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const value = this.value.trim();
                            if (value) {
                                addTag(nameTags, value);
                                this.value = '';
                            }
                        }
                    });
                }

                // Handle tag removal clicks
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('cms-tag-remove')) {
                        e.preventDefault();
                        const tag = e.target.closest('.cms-tag');
                        if (tag) {
                            tag.remove();
                        }
                    }
                });
            }

            // Add tag to container
            function addTag(container, value) {
                if (!container) return;

                // Check if tag already exists
                const existing = Array.from(container.querySelectorAll('.cms-tag')).find(tag => {
                    return tag.querySelector('.cms-tag-remove')?.dataset.value === value;
                });

                if (existing) return;

                const tag = document.createElement('span');
                tag.className = 'cms-tag';
                tag.innerHTML = `${value} <button class="cms-tag-remove" data-value="${value}">&times;</button>`;
                container.appendChild(tag);
            }

            // Load current exclusions from backend
            function loadExclusions() {
                fetch(apiBaseUrl + '/settings/exclusions')
                    .then(response => response.json())
                    .then(data => {
                        // Clear existing tags
                        document.getElementById('cms-excluded-prefixes').innerHTML = '';
                        document.getElementById('cms-excluded-routes').innerHTML = '';
                        document.getElementById('cms-excluded-names').innerHTML = '';

                        // Add prefixes
                        if (data.prefixes && Array.isArray(data.prefixes)) {
                            const prefixContainer = document.getElementById('cms-excluded-prefixes');
                            data.prefixes.forEach(prefix => {
                                addTag(prefixContainer, prefix);
                            });
                        }

                        // Add routes
                        if (data.routes && Array.isArray(data.routes)) {
                            const routeContainer = document.getElementById('cms-excluded-routes');
                            data.routes.forEach(route => {
                                addTag(routeContainer, route);
                            });
                        }

                        // Add names
                        if (data.names && Array.isArray(data.names)) {
                            const nameContainer = document.getElementById('cms-excluded-names');
                            data.names.forEach(name => {
                                addTag(nameContainer, name);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Failed to load exclusions:', error);
                    });
            }

            // Get tags from container
            function getTagValues(containerId) {
                const container = document.getElementById(containerId);
                if (!container) return [];

                return Array.from(container.querySelectorAll('.cms-tag-remove')).map(btn => {
                    return btn.dataset.value;
                });
            }

            // Save Settings
            function saveSettings() {
                const settings = {
                    enabled: document.getElementById('cms-setting-enabled').checked,
                    toolbar: {
                        enabled: document.getElementById('cms-setting-toolbar').checked,
                        position: document.getElementById('cms-setting-position').value,
                        theme: document.getElementById('cms-setting-theme').value
                    },
                    exclusions: {
                        prefixes: getTagValues('cms-excluded-prefixes'),
                        routes: getTagValues('cms-excluded-routes'),
                        names: getTagValues('cms-excluded-names')
                    }
                };

                fetch(apiBaseUrl + '/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify(settings)
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Settings saved');
                    closeModal();
                })
                .catch(error => {
                    console.error('Failed to save settings:', error);
                });
            }

            // Handle open link editor event
            function handleOpenLinkEditor(e) {
                const detail = e.detail;

                // Fill the form
                document.getElementById('cms-link-text').value = detail.text || '';
                document.getElementById('cms-link-href').value = detail.href || '';
                document.getElementById('cms-link-new-tab').checked = detail.newTab || false;

                // Open the modal
                openModal('link-editor');
            }

            // Save link changes
            function saveLinkChanges() {
                const element = window.CMS.currentLinkElement;
                if (!element) return;

                const newText = document.getElementById('cms-link-text').value;
                const newHref = document.getElementById('cms-link-href').value;
                const newTab = document.getElementById('cms-link-new-tab').checked;

                // Update the element
                if (newText) element.textContent = newText;
                if (newHref) element.setAttribute('href', newHref);

                if (newTab) {
                    element.setAttribute('target', '_blank');
                    element.setAttribute('rel', 'noopener noreferrer');
                } else {
                    element.removeAttribute('target');
                    element.removeAttribute('rel');
                }

                // Trigger save event
                const event = new CustomEvent('cms:contentChanged', {
                    detail: {
                        id: element.getAttribute('data-cms-id'),
                        type: 'link',
                        text: newText,
                        href: newHref,
                        target: newTab ? '_blank' : '',
                        element: element
                    }
                });
                document.dispatchEvent(event);

                closeModal();
            }

            // Handle open image editor
            function handleOpenImageEditor(e) {
                const detail = e.detail;

                // Fill the form
                document.getElementById('cms-image-alt').value = detail.alt || '';
                document.getElementById('cms-image-title').value = detail.title || '';

                // Show current image preview
                const previewImg = document.getElementById('cms-preview-img');
                if (detail.src) {
                    previewImg.src = detail.src;
                    document.getElementById('cms-image-preview').style.display = 'block';
                    document.getElementById('cms-image-dropzone').style.display = 'none';
                }

                // Open the modal
                openModal('image-editor');
            }

            // Setup image dropzone
            function setupImageDropzone() {
                const dropzone = document.getElementById('cms-image-dropzone');
                const fileInput = document.getElementById('cms-image-upload');
                const preview = document.getElementById('cms-image-preview');
                const previewImg = document.getElementById('cms-preview-img');
                const saveBtn = document.getElementById('cms-save-image');
                const changeBtn = document.getElementById('cms-change-image');

                if (!dropzone || !fileInput) return;

                let selectedFile = null;

                // Tab switching
                document.querySelectorAll('.cms-tab-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const tab = this.dataset.tab;

                        // Update active tab button
                        document.querySelectorAll('.cms-tab-btn').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');

                        // Show/hide tab content
                        document.querySelectorAll('.cms-tab-content').forEach(content => {
                            if (content.dataset.tab === tab) {
                                content.style.display = 'block';
                            } else {
                                content.style.display = 'none';
                            }
                        });
                    });
                });

                // Click to upload
                dropzone.addEventListener('click', () => fileInput.click());

                // Change image button
                changeBtn?.addEventListener('click', () => {
                    dropzone.style.display = 'block';
                    preview.style.display = 'none';
                    selectedFile = null;
                    saveBtn.disabled = true;
                });

                // File input change
                fileInput.addEventListener('change', handleFileSelect);

                // Drag and drop
                dropzone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropzone.classList.add('dragover');
                });

                dropzone.addEventListener('dragleave', () => {
                    dropzone.classList.remove('dragover');
                });

                dropzone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('dragover');

                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        handleFile(files[0]);
                    }
                });

                function handleFileSelect(e) {
                    const files = e.target.files;
                    if (files.length > 0) {
                        handleFile(files[0]);
                    }
                }

                function handleFile(file) {
                    // Validate file type
                    if (!file.type.startsWith('image/')) {
                        showToast('Please select an image file', 'warning');
                        return;
                    }

                    // Validate file size (10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        showToast('File size must be less than 10MB', 'warning');
                        return;
                    }

                    selectedFile = file;

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        dropzone.style.display = 'none';
                        preview.style.display = 'block';
                        saveBtn.disabled = false;
                    };
                    reader.readAsDataURL(file);
                }

                window.CMS.selectedImageFile = () => selectedFile;
            }

            // Setup media library
            function setupMediaLibrary() {
                let currentFolder = 0;
                let selectedMedia = [];
                let mediaItems = [];
                let folders = [];

                // Load folders
                loadFolders();

                // Load media on modal open
                document.querySelector('[data-modal="assets"]')?.addEventListener('click', function() {
                    loadMedia();
                });

                // Upload button
                document.querySelector('.cms-btn-upload-media')?.addEventListener('click', function() {
                    document.getElementById('cms-media-file-input')?.click();
                });

                // File input change
                document.getElementById('cms-media-file-input')?.addEventListener('change', function(e) {
                    const files = e.target.files;
                    if (files.length > 0) {
                        uploadMediaFiles(files);
                    }
                });

                // Create folder button
                document.querySelector('.cms-btn-create-folder')?.addEventListener('click', function() {
                    openModal('create-folder');
                    loadFolderOptions();
                });

                // Create folder submit
                document.getElementById('cms-create-folder')?.addEventListener('click', function() {
                    createFolder();
                });

                // Cancel folder creation
                document.getElementById('cms-cancel-folder')?.addEventListener('click', function() {
                    closeModal();
                });

                // Folder selection
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.cms-media-folder')) {
                        const folder = e.target.closest('.cms-media-folder');
                        selectFolder(folder);
                    }
                });

                // Media item selection
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.cms-media-item')) {
                        const item = e.target.closest('.cms-media-item');
                        toggleMediaSelection(item);
                    }
                });

                // Search
                let searchTimeout;
                document.getElementById('cms-media-search')?.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterMedia(e.target.value);
                    }, 300);
                });

                // Deselect all
                document.getElementById('cms-media-deselect')?.addEventListener('click', function() {
                    deselectAllMedia();
                });

                // Insert selected media
                document.getElementById('cms-media-insert')?.addEventListener('click', function() {
                    insertSelectedMedia();
                });

                // Load folders from API
                function loadFolders() {
                    fetch(apiBaseUrl + '/media/folders')
                        .then(response => response.json())
                        .then(data => {
                            folders = data.folders || [];
                            renderFolders();
                        })
                        .catch(error => {
                            console.error('Failed to load folders:', error);
                        });
                }

                // Render folders
                function renderFolders() {
                    const container = document.getElementById('cms-media-folders');
                    if (!container) return;

                    let html = `
                        <div class="cms-media-folder ${currentFolder === 0 ? 'active' : ''}" data-folder-id="0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <span>All Media</span>
                        </div>
                    `;

                    folders.forEach(folder => {
                        html += `
                            <div class="cms-media-folder ${currentFolder === folder.id ? 'active' : ''}" data-folder-id="${folder.id}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <span>${folder.name}</span>
                            </div>
                        `;
                    });

                    container.innerHTML = html;
                }

                // Select folder
                function selectFolder(folderEl) {
                    document.querySelectorAll('.cms-media-folder').forEach(f => {
                        f.classList.remove('active');
                    });
                    folderEl.classList.add('active');
                    currentFolder = parseInt(folderEl.dataset.folderId);
                    loadMedia();
                }

                // Load media
                function loadMedia() {
                    const grid = document.getElementById('cms-media-grid');
                    if (!grid) return;

                    grid.innerHTML = '<div class="cms-media-loading">Loading media...</div>';

                    let url = apiBaseUrl + '/media';
                    // For root folder (All Media), explicitly request items with no folder
                    if (currentFolder === 0) {
                        url += '?include_root=true';
                    } else {
                        url += `?folder_id=${currentFolder}`;
                    }

                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && Array.isArray(data.media)) {
                                mediaItems = data.media;
                            } else {
                                mediaItems = [];
                            }
                            renderMedia();
                        })
                        .catch(error => {
                            console.error('Failed to load media:', error);
                            grid.innerHTML = '<div class="cms-media-loading">Failed to load media</div>';
                        });
                }

                // Render media
                function renderMedia() {
                    const grid = document.getElementById('cms-media-grid');
                    if (!grid) return;

                    if (mediaItems.length === 0) {
                        grid.innerHTML = '<div class="cms-media-loading">No media found</div>';
                        return;
                    }

                    grid.innerHTML = mediaItems.map(item => `
                        <div class="cms-media-item" data-media-id="${item.id}" data-url="${item.url}">
                            <img src="${item.url}" alt="${item.alt || ''}">
                            <div class="cms-media-item-overlay"></div>
                            <div class="cms-media-item-info">
                                ${item.filename}
                            </div>
                        </div>
                    `).join('');
                }

                // Toggle media selection
                function toggleMediaSelection(item) {
                    const mediaId = parseInt(item.dataset.mediaId);
                    const isSelected = item.classList.contains('selected');

                    if (isSelected) {
                        item.classList.remove('selected');
                        selectedMedia = selectedMedia.filter(id => id !== mediaId);
                    } else {
                        item.classList.add('selected');
                        selectedMedia.push(mediaId);
                    }

                    updateSelectionInfo();
                }

                // Update selection info
                function updateSelectionInfo() {
                    const info = document.getElementById('cms-media-selected-info');
                    const count = document.getElementById('cms-media-selected-count');

                    if (selectedMedia.length > 0) {
                        info.style.display = 'flex';
                        count.textContent = `${selectedMedia.length} selected`;
                    } else {
                        info.style.display = 'none';
                    }
                }

                // Deselect all media
                function deselectAllMedia() {
                    document.querySelectorAll('.cms-media-item.selected').forEach(item => {
                        item.classList.remove('selected');
                    });
                    selectedMedia = [];
                    updateSelectionInfo();
                }

                // Filter media
                function filterMedia(query) {
                    const items = document.querySelectorAll('.cms-media-item');
                    const lowerQuery = query.toLowerCase();

                    items.forEach(item => {
                        const info = item.querySelector('.cms-media-item-info');
                        const filename = info ? info.textContent.toLowerCase() : '';

                        if (filename.includes(lowerQuery)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }

                // Upload media files
                function uploadMediaFiles(files) {
                    const formData = new FormData();

                    for (let i = 0; i < files.length; i++) {
                        formData.append('images[]', files[i]);
                    }

                    if (currentFolder > 0) {
                        formData.append('folder_id', currentFolder);
                    }

                    // Show loading
                    const grid = document.getElementById('cms-media-grid');
                    grid.innerHTML = '<div class="cms-media-loading">Uploading...</div>';

                    fetch(apiBaseUrl + '/media/upload-multiple', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Files uploaded successfully', 'success');
                            loadMedia();
                        } else {
                            showToast('Upload failed: ' + (data.message || 'Unknown error'), 'error');
                            loadMedia();
                        }
                    })
                    .catch(error => {
                        console.error('Upload failed:', error);
                        showToast('Upload failed. Please try again.', 'error');
                        loadMedia();
                    });
                }

                // Load folder options for create modal
                function loadFolderOptions() {
                    const select = document.getElementById('cms-parent-folder');
                    if (!select) return;

                    let html = '<option value="0">Root</option>';
                    folders.forEach(folder => {
                        html += `<option value="${folder.id}">${folder.name}</option>`;
                    });
                    select.innerHTML = html;
                }

                // Create folder
                function createFolder() {
                    const name = document.getElementById('cms-new-folder-name')?.value.trim();
                    const parentId = document.getElementById('cms-parent-folder')?.value || 0;

                    if (!name) {
                        showToast('Please enter a folder name', 'warning');
                        return;
                    }

                    fetch(apiBaseUrl + '/media/folders', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            name: name,
                            parent_id: parentId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Folder created successfully', 'success');
                            loadFolders();
                            closeModal();
                            document.getElementById('cms-new-folder-name').value = '';
                        } else {
                            showToast('Failed to create folder: ' + (data.message || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Failed to create folder:', error);
                        showToast('Failed to create folder. Please try again.', 'error');
                    });
                }

                // Insert selected media
                function insertSelectedMedia() {
                    const selected = document.querySelectorAll('.cms-media-item.selected');

                    if (selected.length === 0) {
                        showToast('Please select at least one image', 'warning');
                        return;
                    }

                    // Check if we're selecting for the image editor
                    if (window.CMS.selectingImageForEditor) {
                        if (selected.length > 1) {
                            showToast('Please select only one image for the editor', 'warning');
                            return;
                        }

                        const selectedItem = selected[0];
                        const url = selectedItem.dataset.url;

                        // Update the image editor preview
                        const previewImg = document.getElementById('cms-preview-img');
                        const dropzone = document.getElementById('cms-image-dropzone');
                        const preview = document.getElementById('cms-image-preview');

                        if (previewImg && dropzone && preview) {
                            previewImg.src = url;
                            dropzone.style.display = 'none';
                            preview.style.display = 'block';
                            document.getElementById('cms-save-image').disabled = false;

                            // Store the selected URL for saving
                            window.CMS.selectedLibraryImage = url;
                        }

                        window.CMS.selectingImageForEditor = false;
                    } else {
                        // Regular insertion (for future content editor integration)
                        const urls = Array.from(selected).map(item => item.dataset.url);
                        console.log('Selected media URLs:', urls);
                    }

                    closeModal();
                    deselectAllMedia();
                }
            }

            // Save image changes
            function saveImageChanges() {
                const element = window.CMS.currentImageElement;
                if (!element) return;

                const newAlt = document.getElementById('cms-image-alt').value;
                const newTitle = document.getElementById('cms-image-title').value;
                const selectedFile = window.CMS.selectedImageFile?.();
                const selectedLibraryUrl = window.CMS.selectedLibraryImage;

                // Update alt and title
                if (newAlt !== undefined) element.setAttribute('alt', newAlt);
                if (newTitle !== undefined) element.setAttribute('title', newTitle);

                // If a new file was selected, upload it
                if (selectedFile) {
                    uploadImage(selectedFile, element);
                } else if (selectedLibraryUrl) {
                    // Use image from library
                    element.setAttribute('src', selectedLibraryUrl);
                    const event = new CustomEvent('cms:contentChanged', {
                        detail: {
                            id: element.getAttribute('data-cms-id'),
                            type: 'image',
                            src: selectedLibraryUrl,
                            alt: newAlt,
                            title: newTitle,
                            element: element
                        }
                    });
                    document.dispatchEvent(event);
                    window.CMS.selectedLibraryImage = null;
                    closeModal();
                } else {
                    // Just save the alt/title changes
                    const event = new CustomEvent('cms:contentChanged', {
                        detail: {
                            id: element.getAttribute('data-cms-id'),
                            type: 'image',
                            alt: newAlt,
                            title: newTitle,
                            element: element
                        }
                    });
                    document.dispatchEvent(event);
                    closeModal();
                }
            }

            // Upload image
            function uploadImage(file, element) {
                const formData = new FormData();
                formData.append('image', file);
                formData.append('element_id', element.getAttribute('data-cms-id'));

                // Show loading state
                const saveBtn = document.getElementById('cms-save-image');
                const originalText = saveBtn.textContent;
                saveBtn.textContent = 'Uploading...';
                saveBtn.disabled = true;

                fetch(apiBaseUrl + '/media/upload', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.url) {
                        // Update the image src
                        element.setAttribute('src', data.url);

                        // Trigger save event
                        const event = new CustomEvent('cms:contentChanged', {
                            detail: {
                                id: element.getAttribute('data-cms-id'),
                                type: 'image',
                                src: data.url,
                                alt: document.getElementById('cms-image-alt').value,
                                title: document.getElementById('cms-image-title').value,
                                element: element
                            }
                        });
                        document.dispatchEvent(event);

                        closeModal();
                    } else {
                        showToast('Failed to upload image: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    showToast('Failed to upload image', 'error');
                })
                .finally(() => {
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                });
            }

            // Handle content changed event
            function handleContentChanged(e) {
                const change = e.detail;

                // Add to pending changes
                const existingIndex = pendingChanges.findIndex(c => c.id === change.id);
                if (existingIndex >= 0) {
                    pendingChanges[existingIndex] = change;
                } else {
                    pendingChanges.push(change);
                }

                // Update save button to show pending changes
                const saveBtn = toolbar.querySelector('.cms-btn-save');
                if (saveBtn && pendingChanges.length > 0) {
                    saveBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Save (${pendingChanges.length})</span>
                    `;
                    saveBtn.style.background = '#ff6600';
                }
            }

            // Save all changes
            function saveAllChanges() {
                if (pendingChanges.length === 0) {
                    showToast('No changes to save', 'info');
                    return;
                }

                const saveBtn = toolbar.querySelector('.cms-btn-save');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<span>Saving...</span>';
                }

                // Save each change
                Promise.all(pendingChanges.map(change => saveContentChange(change)))
                    .then(results => {
                        const successful = results.filter(r => r.success).length;
                        const failed = results.filter(r => !r.success).length;

                        if (failed > 0) {
                            showToast(`Saved ${successful} changes, ${failed} failed`, failed > 0 ? 'warning' : 'success');
                        } else {
                            // Clear pending changes
                            pendingChanges = [];

                            // Reset save button
                            if (saveBtn) {
                                saveBtn.disabled = false;
                                saveBtn.innerHTML = `
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    <span>Saved!</span>
                                `;
                                saveBtn.style.background = '#00b341';

                                setTimeout(() => {
                                    saveBtn.innerHTML = `
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                        <span>Save</span>
                                    `;
                                    saveBtn.style.background = '#0066ff';
                                }, 2000);
                            }

                            // Show success toast
                            showToast(`All ${successful} changes saved successfully!`, 'success');

                            // Reload after delay to show toast
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        }
                    })
                    .catch(error => {
                        console.error('Save failed:', error);
                        showToast('Failed to save changes. Check console for details.', 'error');
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = `
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                <span>Save (${pendingChanges.length})</span>
                            `;
                        }
                    });
            }

            // Save single content change
            function saveContentChange(change) {
                return fetch(apiBaseUrl + '/content/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        element_id: change.id,
                        content: change.content || change,
                        original_content: change.originalContent || '',
                        type: change.type || 'text',
                        page_url: window.location.href.split('#')[0], // Remove hash fragment
                        file_hint: null
                    })
                })
                .then(response => response.json())
                .catch(error => {
                    console.error('Error saving content:', error);
                    return { success: false, error: error.message };
                });
            }

            // Make functions available globally
            window.CMS.openModal = openModal;
            window.CMS.closeModal = closeModal;
            window.CMS.navigateToPage = navigateToPage;
            window.CMS.switchLanguage = switchLanguage;
            window.CMS.saveAllChanges = saveAllChanges;
        });
    })();
</script>