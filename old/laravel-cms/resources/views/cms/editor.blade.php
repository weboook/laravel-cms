<!DOCTYPE html>
<html lang="{{ $locale }}" data-theme="{{ $preferences['theme'] ?? 'light' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ $csrfToken }}">
    <meta name="cms-config" content="{{ json_encode($config) }}">
    <title>CMS Editor - {{ $targetUrl }}</title>

    <!-- CSS Assets -->
    <link rel="stylesheet" href="{{ asset('cms/css/editor.css') }}">
    <link rel="stylesheet" href="{{ asset('cms/css/themes/' . ($preferences['theme'] ?? 'light') . '.css') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="cms-editor {{ $preferences['theme'] ?? 'light' }}-theme">
    <!-- Main Container -->
    <div id="cms-app" class="cms-app">
        <!-- Header Toolbar -->
        <header class="cms-header">
            <div class="cms-header-left">
                <div class="cms-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                    <span>CMS Editor</span>
                </div>

                <!-- Toolbar Groups -->
                <div class="cms-toolbar">
                    @foreach($toolbar['groups'] as $groupKey => $group)
                        <div class="toolbar-group" data-group="{{ $groupKey }}">
                            <span class="group-label">{{ $group['label'] }}</span>
                            <div class="group-buttons">
                                @foreach($group['buttons'] as $buttonKey => $button)
                                    @if($button)
                                        <button
                                            class="toolbar-btn"
                                            data-action="{{ $buttonKey }}"
                                            @if(isset($button['shortcut']))
                                                title="{{ $button['label'] }} ({{ $button['shortcut'] }})"
                                            @else
                                                title="{{ $button['label'] }}"
                                            @endif
                                        >
                                            <i class="icon icon-{{ $button['icon'] }}"></i>
                                            <span class="btn-label">{{ $button['label'] }}</span>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="cms-header-right">
                <!-- Locale Selector -->
                <div class="locale-selector">
                    <select id="locale-select" class="form-select">
                        @foreach($locales as $code => $localeData)
                            <option value="{{ $code }}" {{ $code === $locale ? 'selected' : '' }}>
                                {{ $localeData['flag'] }} {{ $localeData['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Indicators -->
                <div class="status-indicators">
                    <div class="status-item" id="save-status">
                        <i class="icon icon-check"></i>
                        <span>Saved</span>
                    </div>

                    <div class="status-item" id="connection-status">
                        <i class="icon icon-wifi"></i>
                        <span>Connected</span>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="user-menu">
                    <div class="user-avatar">
                        <img src="{{ $user->avatar ?? asset('cms/images/default-avatar.png') }}"
                             alt="{{ $user->name }}"
                             class="avatar-img">
                    </div>
                    <span class="user-name">{{ $user->name }}</span>

                    <div class="user-dropdown">
                        <a href="#" class="dropdown-item" data-action="preferences">
                            <i class="icon icon-settings"></i> Preferences
                        </a>
                        <a href="#" class="dropdown-item" data-action="help">
                            <i class="icon icon-help"></i> Help
                        </a>
                        <hr class="dropdown-divider">
                        <a href="{{ route('logout') }}" class="dropdown-item">
                            <i class="icon icon-logout"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="cms-main">
            <!-- Sidebar -->
            <aside class="cms-sidebar" id="cms-sidebar">
                <!-- Recent Edits -->
                <div class="sidebar-section">
                    <h3 class="section-title">Recent Edits</h3>
                    <div class="recent-edits">
                        @forelse($recentEdits as $edit)
                            <div class="recent-edit-item">
                                <div class="edit-file">{{ basename($edit['file']) }}</div>
                                <div class="edit-description">{{ $edit['description'] }}</div>
                                <div class="edit-time">{{ \Carbon\Carbon::parse($edit['edited_at'])->diffForHumans() }}</div>
                            </div>
                        @empty
                            <div class="empty-state">No recent edits</div>
                        @endforelse
                    </div>
                </div>

                <!-- Element Browser -->
                <div class="sidebar-section">
                    <h3 class="section-title">
                        Elements
                        <button class="btn-icon" id="refresh-elements" title="Refresh">
                            <i class="icon icon-refresh"></i>
                        </button>
                    </h3>
                    <div class="element-browser" id="element-browser">
                        <div class="loading-state">
                            <i class="icon icon-spinner"></i>
                            <span>Scanning elements...</span>
                        </div>
                    </div>
                </div>

                <!-- File Explorer -->
                <div class="sidebar-section">
                    <h3 class="section-title">Files</h3>
                    <div class="file-explorer" id="file-explorer">
                        <!-- File tree will be loaded here -->
                    </div>
                </div>
            </aside>

            <!-- Content Container -->
            <div class="cms-content" id="cms-content">
                <!-- Preview Frame -->
                <div class="preview-container">
                    <div class="preview-header">
                        <div class="preview-url">
                            <input type="url"
                                   id="preview-url"
                                   class="form-input"
                                   value="{{ $targetUrl }}"
                                   placeholder="Enter URL to preview...">
                            <button class="btn btn-primary" id="load-preview">
                                <i class="icon icon-refresh"></i>
                                Load
                            </button>
                        </div>

                        <div class="preview-controls">
                            <div class="view-modes">
                                <button class="mode-btn active" data-mode="desktop">
                                    <i class="icon icon-monitor"></i>
                                </button>
                                <button class="mode-btn" data-mode="tablet">
                                    <i class="icon icon-tablet"></i>
                                </button>
                                <button class="mode-btn" data-mode="mobile">
                                    <i class="icon icon-smartphone"></i>
                                </button>
                            </div>

                            <button class="btn btn-outline" id="toggle-edit-mode">
                                <i class="icon icon-edit"></i>
                                Edit Mode
                            </button>
                        </div>
                    </div>

                    <div class="preview-frame-container">
                        <iframe
                            id="preview-frame"
                            class="preview-frame"
                            src="{{ $targetUrl }}"
                            sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
                            loading="lazy">
                        </iframe>

                        <!-- Loading Overlay -->
                        <div class="preview-loading" id="preview-loading">
                            <div class="loading-spinner">
                                <i class="icon icon-spinner"></i>
                                <span>Loading preview...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Floating Editor Panels -->
        <div class="floating-panels">
            <!-- Element Editor Panel -->
            <div class="floating-panel editor-panel" id="editor-panel">
                <div class="panel-header">
                    <h4 class="panel-title">Element Editor</h4>
                    <div class="panel-controls">
                        <button class="btn-icon" id="minimize-editor">
                            <i class="icon icon-minimize"></i>
                        </button>
                        <button class="btn-icon" id="close-editor">
                            <i class="icon icon-x"></i>
                        </button>
                    </div>
                </div>

                <div class="panel-content">
                    <div class="element-info">
                        <div class="info-row">
                            <label>Type:</label>
                            <span id="element-type">-</span>
                        </div>
                        <div class="info-row">
                            <label>File:</label>
                            <span id="element-file">-</span>
                        </div>
                        <div class="info-row">
                            <label>Line:</label>
                            <span id="element-line">-</span>
                        </div>
                    </div>

                    <div class="editor-form">
                        <textarea
                            id="element-content"
                            class="form-textarea"
                            placeholder="Element content..."
                            rows="8">
                        </textarea>

                        <div class="form-actions">
                            <button class="btn btn-primary" id="save-element">
                                <i class="icon icon-save"></i>
                                Save
                            </button>
                            <button class="btn btn-outline" id="cancel-edit">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Translation Panel -->
            @if(in_array('translate', $permissions))
                <div class="floating-panel translation-panel" id="translation-panel">
                    <div class="panel-header">
                        <h4 class="panel-title">Translations</h4>
                        <button class="btn-icon" id="close-translation">
                            <i class="icon icon-x"></i>
                        </button>
                    </div>

                    <div class="panel-content">
                        <div class="translation-list" id="translation-list">
                            <!-- Translation entries will be loaded here -->
                        </div>
                    </div>
                </div>
            @endif

            <!-- Settings Panel -->
            <div class="floating-panel settings-panel" id="settings-panel">
                <div class="panel-header">
                    <h4 class="panel-title">Editor Settings</h4>
                    <button class="btn-icon" id="close-settings">
                        <i class="icon icon-x"></i>
                    </button>
                </div>

                <div class="panel-content">
                    <div class="setting-group">
                        <h5>Appearance</h5>
                        <div class="setting-item">
                            <label for="theme-select">Theme:</label>
                            <select id="theme-select" class="form-select">
                                <option value="light" {{ ($preferences['theme'] ?? 'light') === 'light' ? 'selected' : '' }}>Light</option>
                                <option value="dark" {{ ($preferences['theme'] ?? 'light') === 'dark' ? 'selected' : '' }}>Dark</option>
                                <option value="auto" {{ ($preferences['theme'] ?? 'light') === 'auto' ? 'selected' : '' }}>Auto</option>
                            </select>
                        </div>

                        <div class="setting-item">
                            <label for="font-size-slider">Font Size:</label>
                            <input type="range"
                                   id="font-size-slider"
                                   class="form-range"
                                   min="10"
                                   max="24"
                                   value="{{ $preferences['font_size'] ?? 14 }}">
                            <span id="font-size-value">{{ $preferences['font_size'] ?? 14 }}px</span>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h5>Editor</h5>
                        <div class="setting-item">
                            <label class="checkbox-label">
                                <input type="checkbox"
                                       id="auto-save"
                                       {{ ($preferences['auto_save'] ?? true) ? 'checked' : '' }}>
                                <span class="checkmark"></span>
                                Auto Save
                            </label>
                        </div>

                        <div class="setting-item">
                            <label class="checkbox-label">
                                <input type="checkbox"
                                       id="spell-check"
                                       {{ ($preferences['spell_check'] ?? true) ? 'checked' : '' }}>
                                <span class="checkmark"></span>
                                Spell Check
                            </label>
                        </div>

                        <div class="setting-item">
                            <label class="checkbox-label">
                                <input type="checkbox"
                                       id="show-minimap"
                                       {{ ($preferences['show_minimap'] ?? false) ? 'checked' : '' }}>
                                <span class="checkmark"></span>
                                Show Minimap
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Keyboard Shortcuts Help Modal -->
        <div class="modal" id="shortcuts-modal">
            <div class="modal-overlay" data-dismiss="modal"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Keyboard Shortcuts</h3>
                    <button class="btn-icon" data-dismiss="modal">
                        <i class="icon icon-x"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="shortcuts-grid">
                        @foreach($toolbar['shortcuts'] ?? [] as $category => $shortcuts)
                            <div class="shortcut-category">
                                <h4>{{ ucfirst(str_replace('_', ' ', $category)) }}</h4>
                                @foreach($shortcuts as $action => $shortcut)
                                    <div class="shortcut-item">
                                        <span class="shortcut-action">{{ ucfirst(str_replace('_', ' ', $action)) }}</span>
                                        <kbd class="shortcut-key">{{ $shortcut }}</kbd>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Bar -->
        <footer class="cms-footer">
            <div class="footer-left">
                <div class="status-item">
                    <span class="status-label">Target:</span>
                    <span class="status-value">{{ $targetUrl }}</span>
                </div>

                <div class="status-item">
                    <span class="status-label">Locale:</span>
                    <span class="status-value">{{ $locale }}</span>
                </div>
            </div>

            <div class="footer-right">
                <div class="status-item" id="element-count">
                    <span class="status-value">0 elements</span>
                </div>

                <div class="status-item" id="last-save">
                    <span class="status-value">{{ $editorState['last_save'] ? \Carbon\Carbon::parse($editorState['last_save'])->diffForHumans() : 'Never' }}</span>
                </div>
            </div>
        </footer>

        <!-- Toast Notifications -->
        <div class="toast-container" id="toast-container"></div>
    </div>

    <!-- JavaScript Configuration -->
    <script>
        window.cms = {
            config: @json($config),
            user: @json($user->only(['id', 'name', 'email'])),
            permissions: @json($permissions),
            locale: @json($locale),
            locales: @json($locales),
            targetUrl: @json($targetUrl),
            editorState: @json($editorState),
            preferences: @json($preferences),
            toolbar: @json($toolbar),
            csrfToken: @json($csrfToken),
            routes: {
                preview: '{{ route("cms.editor.preview", ["url" => "__URL__"]) }}',
                scan: '{{ route("cms.editor.scan") }}',
                config: '{{ route("cms.editor.config") }}',
                toolbar: '{{ route("cms.editor.toolbar") }}',
                update: '{{ route("cms.content.update") }}',
            }
        };

        // Initialize keyboard shortcuts
        window.cms.shortcuts = @json($toolbar['shortcuts'] ?? []);
    </script>

    <!-- Core JavaScript -->
    <script src="{{ asset('cms/js/vendor/lodash.min.js') }}"></script>
    <script src="{{ asset('cms/js/vendor/axios.min.js') }}"></script>
    <script src="{{ asset('cms/js/core/cms.js') }}"></script>
    <script src="{{ asset('cms/js/components/editor.js') }}"></script>
    <script src="{{ asset('cms/js/components/preview.js') }}"></script>
    <script src="{{ asset('cms/js/components/scanner.js') }}"></script>
    <script src="{{ asset('cms/js/components/shortcuts.js') }}"></script>
    <script src="{{ asset('cms/js/app.js') }}"></script>

    <!-- Initialize Application -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize CMS application
            if (window.CMS && window.CMS.init) {
                window.CMS.init(window.cms);
            }

            // Set up CSRF token for all AJAX requests
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = window.cms.csrfToken;
                window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
            }

            // Initialize keyboard shortcuts
            if (window.CMS && window.CMS.initShortcuts) {
                window.CMS.initShortcuts(window.cms.shortcuts);
            }

            // Auto-load content scan
            if (window.cms.targetUrl) {
                setTimeout(() => {
                    if (window.CMS && window.CMS.scanner) {
                        window.CMS.scanner.scan(window.cms.targetUrl);
                    }
                }, 1000);
            }
        });

        // Handle beforeunload to warn about unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (window.cms && window.cms.editorState && window.cms.editorState.unsaved_changes) {
                const confirmationMessage = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = confirmationMessage;
                return confirmationMessage;
            }
        });
    </script>

    <!-- Custom Styles -->
    <style>
        :root {
            --cms-primary: #3b82f6;
            --cms-secondary: #64748b;
            --cms-success: #10b981;
            --cms-warning: #f59e0b;
            --cms-error: #ef4444;
            --cms-bg: #ffffff;
            --cms-surface: #f8fafc;
            --cms-border: #e2e8f0;
            --cms-text: #1e293b;
            --cms-text-muted: #64748b;
            --cms-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --cms-radius: 0.5rem;
            --cms-font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        [data-theme="dark"] {
            --cms-bg: #0f172a;
            --cms-surface: #1e293b;
            --cms-border: #334155;
            --cms-text: #f1f5f9;
            --cms-text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: var(--cms-font-family);
            background: var(--cms-bg);
            color: var(--cms-text);
            overflow: hidden;
        }

        .cms-app {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .cms-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: var(--cms-surface);
            border-bottom: 1px solid var(--cms-border);
            box-shadow: var(--cms-shadow);
            z-index: 100;
        }

        .cms-header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .cms-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--cms-primary);
        }

        .cms-toolbar {
            display: flex;
            gap: 1rem;
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .group-label {
            font-size: 0.75rem;
            color: var(--cms-text-muted);
            margin-right: 0.25rem;
        }

        .group-buttons {
            display: flex;
            gap: 0.25rem;
        }

        .toolbar-btn {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.5rem;
            border: 1px solid var(--cms-border);
            border-radius: var(--cms-radius);
            background: var(--cms-bg);
            color: var(--cms-text);
            cursor: pointer;
            transition: all 0.2s;
        }

        .toolbar-btn:hover {
            background: var(--cms-surface);
            border-color: var(--cms-primary);
        }

        .toolbar-btn.active {
            background: var(--cms-primary);
            color: white;
            border-color: var(--cms-primary);
        }

        .cms-main {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .cms-sidebar {
            width: 300px;
            background: var(--cms-surface);
            border-right: 1px solid var(--cms-border);
            overflow-y: auto;
            padding: 1rem;
        }

        .sidebar-section {
            margin-bottom: 1.5rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0 0 0.75rem 0;
            color: var(--cms-text);
        }

        .cms-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .preview-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--cms-surface);
            border-bottom: 1px solid var(--cms-border);
        }

        .preview-url {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            max-width: 600px;
        }

        .preview-frame-container {
            position: relative;
            flex: 1;
        }

        .preview-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }

        .floating-panels {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 1000;
        }

        .floating-panel {
            position: absolute;
            min-width: 400px;
            max-width: 600px;
            background: var(--cms-bg);
            border: 1px solid var(--cms-border);
            border-radius: var(--cms-radius);
            box-shadow: 0 10px 25px -3px rgb(0 0 0 / 0.1);
            pointer-events: auto;
            display: none;
        }

        .floating-panel.active {
            display: block;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--cms-border);
            background: var(--cms-surface);
            border-radius: var(--cms-radius) var(--cms-radius) 0 0;
        }

        .panel-content {
            padding: 1rem;
        }

        .cms-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            background: var(--cms-surface);
            border-top: 1px solid var(--cms-border);
            font-size: 0.75rem;
        }

        .footer-left,
        .footer-right {
            display: flex;
            gap: 1rem;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--cms-text-muted);
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.5rem;
            border: 1px solid var(--cms-border);
            border-radius: var(--cms-radius);
            background: var(--cms-bg);
            color: var(--cms-text);
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--cms-primary);
            box-shadow: 0 0 0 3px rgb(59 130 246 / 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: var(--cms-radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--cms-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-outline {
            border-color: var(--cms-border);
            background: var(--cms-bg);
            color: var(--cms-text);
        }

        .btn-outline:hover {
            border-color: var(--cms-primary);
            background: var(--cms-surface);
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: var(--cms-radius);
            background: transparent;
            color: var(--cms-text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: var(--cms-surface);
            color: var(--cms-text);
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--cms-bg);
            border-radius: var(--cms-radius);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            max-width: 90vw;
            max-height: 90vh;
            overflow: hidden;
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 3000;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .cms-sidebar {
                position: fixed;
                left: -300px;
                top: 60px;
                height: calc(100vh - 60px);
                z-index: 500;
                transition: left 0.3s;
            }

            .cms-sidebar.open {
                left: 0;
            }

            .cms-toolbar {
                display: none;
            }

            .floating-panel {
                left: 1rem !important;
                right: 1rem !important;
                top: 1rem !important;
                min-width: auto;
                max-width: none;
            }
        }

        /* Loading states */
        .loading-state {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            color: var(--cms-text-muted);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .icon-spinner {
            animation: spin 1s linear infinite;
        }
    </style>
</body>
</html>