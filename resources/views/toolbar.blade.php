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
                <div class="cms-setting-group">
                    <button class="cms-btn cms-btn-primary" id="cms-save-settings">Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Asset Library Modal (placeholder) --}}
    <div class="cms-modal cms-modal-assets" data-modal="assets" style="display: none;">
        <div class="cms-modal-header">
            <h2>Asset Library</h2>
            <button class="cms-modal-close">&times;</button>
        </div>
        <div class="cms-modal-body">
            <div class="cms-assets-placeholder">
                <p>Asset Library will be implemented in the next step.</p>
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
        max-width: 600px;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        color: #e0e0e0;
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

            // Initialize
            init();

            function init() {
                // Set default mode to preview
                window.CMS.mode = 'preview';
                document.body.classList.remove('cms-edit-mode');

                loadLanguages();
                loadPages();
                setupEventListeners();
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
                    btn.addEventListener('click', closeModal);
                });

                // Backdrop click
                document.querySelector('.cms-modal-backdrop')?.addEventListener('click', closeModal);

                // Save button
                toolbar.querySelector('.cms-btn-save')?.addEventListener('click', function() {
                    console.log('Save clicked');
                    // Save functionality will be implemented later
                });

                // Settings save
                document.getElementById('cms-save-settings')?.addEventListener('click', saveSettings);

                // Pages search
                document.getElementById('cms-pages-search')?.addEventListener('input', filterPages);
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

                list.innerHTML = allPages.map(page => `
                    <div class="cms-page-item ${page.is_template ? 'template' : ''} ${page.path === currentPage ? 'active' : ''}"
                         data-path="${page.path}"
                         data-template="${page.is_template}">
                        <div>
                            <div class="cms-page-item-title">${page.title}</div>
                            <div class="cms-page-item-path">${page.path}</div>
                        </div>
                        ${page.is_template ? '<span>Template</span>' : ''}
                    </div>
                `).join('');

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

            // Save Settings
            function saveSettings() {
                const settings = {
                    enabled: document.getElementById('cms-setting-enabled').checked,
                    toolbar: {
                        enabled: document.getElementById('cms-setting-toolbar').checked,
                        position: document.getElementById('cms-setting-position').value,
                        theme: document.getElementById('cms-setting-theme').value
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

            // Make functions available globally
            window.CMS.openModal = openModal;
            window.CMS.closeModal = closeModal;
            window.CMS.navigateToPage = navigateToPage;
            window.CMS.switchLanguage = switchLanguage;
        });
    })();
</script>