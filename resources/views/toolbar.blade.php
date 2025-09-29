<div id="cms-toolbar" class="cms-toolbar">
    <div class="cms-toolbar-container">
        {{-- Left Section: Edit/Preview Mode Toggle --}}
        <div class="cms-toolbar-section cms-toolbar-left">
            <div class="cms-mode-toggle">
                <button class="cms-btn cms-btn-mode cms-btn-edit active" data-mode="edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span>Edit</span>
                </button>
                <button class="cms-btn cms-btn-mode cms-btn-preview" data-mode="preview">
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
            <button class="cms-btn cms-btn-pages">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <span>Pages</span>
            </button>

            <div class="cms-separator"></div>

            <button class="cms-btn cms-btn-languages">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <span>Languages</span>
            </button>
        </div>

        {{-- Right Section: Asset Library and Save --}}
        <div class="cms-toolbar-section cms-toolbar-right">
            <button class="cms-btn cms-btn-assets">
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

    .cms-separator {
        width: 1px;
        height: 24px;
        background: #333;
        margin: 0 5px;
    }

    @media (max-width: 768px) {
        .cms-toolbar-container {
            padding: 0 10px;
        }

        .cms-toolbar-middle {
            position: static;
            transform: none;
        }

        .cms-toolbar-section {
            gap: 5px;
        }

        .cms-btn {
            padding: 6px 10px;
            font-size: 12px;
        }

        .cms-btn span {
            display: none;
        }

        .cms-btn svg {
            width: 18px;
            height: 18px;
        }

        .cms-btn-mode span,
        .cms-btn-primary span {
            display: inline;
        }
    }

    @media (max-width: 480px) {
        .cms-toolbar-container {
            flex-wrap: wrap;
            height: auto;
            padding: 8px;
        }

        .cms-toolbar-section {
            flex: 1 1 auto;
        }

        .cms-toolbar-middle {
            order: 3;
            width: 100%;
            justify-content: center;
            margin-top: 8px;
        }
    }
</style>

<script>
    (function() {
        'use strict';

        document.addEventListener('DOMContentLoaded', function() {
            const toolbar = document.getElementById('cms-toolbar');
            if (!toolbar) return;

            // Mode toggle functionality
            const modeButtons = toolbar.querySelectorAll('.cms-btn-mode');
            modeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    modeButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const mode = this.dataset.mode;
                    console.log('CMS Mode:', mode);
                    // Mode switching logic will be implemented later
                });
            });

            // Button click handlers (placeholders)
            toolbar.querySelector('.cms-btn-pages')?.addEventListener('click', function() {
                console.log('Pages clicked');
                // Pages functionality will be implemented later
            });

            toolbar.querySelector('.cms-btn-languages')?.addEventListener('click', function() {
                console.log('Languages clicked');
                // Languages functionality will be implemented later
            });

            toolbar.querySelector('.cms-btn-assets')?.addEventListener('click', function() {
                console.log('Asset Library clicked');
                // Asset Library functionality will be implemented later
            });

            toolbar.querySelector('.cms-btn-save')?.addEventListener('click', function() {
                console.log('Save clicked');
                // Save functionality will be implemented later
            });
        });
    })();
</script>