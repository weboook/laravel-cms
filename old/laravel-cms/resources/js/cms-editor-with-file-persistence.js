/**
 * Laravel CMS Editor with File Persistence
 *
 * This is the corrected version that properly sends file paths and original content
 * to enable actual file updates on the server.
 */

(function() {
    'use strict';

    // Store original content and metadata for each editable element
    const elementMetadata = new Map();

    // Store current edit mode state
    let isEditMode = false;

    /**
     * Scan and initialize all CMS editable elements
     * IMPORTANT: This now stores the file path and original content for each element
     */
    async function scanContent() {
        console.log('Scanning for CMS editable content...');

        try {
            // First, try to get metadata from the server if available
            const currentUrl = window.location.href;
            const response = await fetch(`/cms/api/content/scan?url=${encodeURIComponent(currentUrl)}`, {
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const scanData = await response.json();

                // Store server-provided metadata
                if (scanData.elements) {
                    scanData.elements.forEach(element => {
                        if (element.metadata && element.metadata.file) {
                            elementMetadata.set(element.id || element.key, {
                                file: element.metadata.file,
                                line: element.metadata.line,
                                originalContent: element.content,
                                key: element.key || element.id
                            });
                        }
                    });
                }
            }
        } catch (error) {
            console.warn('Could not fetch scan data from server:', error);
        }

        // Initialize all editable elements on the page
        const editables = document.querySelectorAll('.cms-editable, [data-cms-field], [data-field]');

        editables.forEach(element => {
            const field = element.dataset.field || element.dataset.cmsField || element.id;

            if (field) {
                // Store the original content if not already stored
                if (!elementMetadata.has(field)) {
                    elementMetadata.set(field, {
                        originalContent: element.innerHTML,
                        file: element.dataset.cmsFile || null,
                        line: element.dataset.cmsLine || null,
                        key: field
                    });
                } else {
                    // Update with current content as original if needed
                    const metadata = elementMetadata.get(field);
                    if (!metadata.originalContent) {
                        metadata.originalContent = element.innerHTML;
                    }
                }

                // Add CMS classes for styling
                element.classList.add('cms-editable');

                // Add data attribute for easy identification
                element.dataset.cmsKey = field;
            }
        });

        console.log('Scanned elements:', elementMetadata.size);
        console.log('Element metadata:', Array.from(elementMetadata.entries()));
    }

    /**
     * Save all changes with proper file path and original content
     * This is the CORRECTED version that includes all required fields
     */
    async function saveChanges() {
        const editables = document.querySelectorAll('.cms-editable');
        const updates = [];

        editables.forEach(element => {
            const field = element.dataset.field || element.dataset.cmsField || element.dataset.cmsKey;
            if (field) {
                const metadata = elementMetadata.get(field) || {};

                // Build the update object with ALL required fields
                const update = {
                    key: field,
                    type: element.dataset.type || 'text',
                    content: element.innerHTML,

                    // CRITICAL: Include file path if available
                    file: metadata.file || element.dataset.cmsFile || null,

                    // CRITICAL: Include original content for comparison
                    oldContent: metadata.originalContent || null
                };

                // Only include updates that have both file and oldContent
                // or log a warning if they're missing
                if (!update.file || !update.oldContent) {
                    console.warn(`Missing metadata for ${field}:`, {
                        hasFile: !!update.file,
                        hasOldContent: !!update.oldContent,
                        metadata: metadata
                    });
                }

                updates.push(update);
            }
        });

        console.log('Saving changes with full metadata:', updates);

        if (updates.length === 0) {
            console.log('No changes to save');
            return;
        }

        const btn = document.getElementById('saveBtn');
        const originalBtnContent = btn ? btn.innerHTML : '';

        // Show saving state
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;
        }

        try {
            // Determine the correct API endpoint
            const apiUrl = window.CMS_API_URL || '/cms/api/content/bulk';

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    updates: updates
                })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Update stored original content after successful save
                updates.forEach(update => {
                    const metadata = elementMetadata.get(update.key);
                    if (metadata) {
                        metadata.originalContent = update.content;
                    }
                });

                // Show success
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Saved!';
                    btn.classList.add('success');

                    setTimeout(() => {
                        btn.innerHTML = originalBtnContent;
                        btn.classList.remove('success');
                        btn.disabled = false;
                    }, 2000);
                }

                console.log('Save successful:', result);

                // Check the logs for debugging
                if (result.stats) {
                    console.log('Save statistics:', result.stats);
                }

            } else {
                throw new Error(result.message || 'Save failed');
            }

        } catch (error) {
            console.error('Save error:', error);

            // Show error
            if (btn) {
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error!';
                btn.classList.add('error');

                setTimeout(() => {
                    btn.innerHTML = originalBtnContent;
                    btn.classList.remove('error');
                    btn.disabled = false;
                }, 3000);
            }

            alert('Failed to save changes: ' + error.message);
        }
    }

    /**
     * Enable or disable edit mode
     */
    function setEditMode(enabled) {
        isEditMode = enabled;
        document.body.classList.toggle('cms-edit-mode', enabled);

        const editables = document.querySelectorAll('.cms-editable');
        editables.forEach(element => {
            element.contentEditable = enabled;
            element.classList.toggle('cms-editing', enabled);

            if (enabled) {
                // Store current content as we enter edit mode
                const field = element.dataset.field || element.dataset.cmsField || element.dataset.cmsKey;
                if (field && !elementMetadata.has(field)) {
                    elementMetadata.set(field, {
                        originalContent: element.innerHTML,
                        file: element.dataset.cmsFile || null,
                        key: field
                    });
                }
            }
        });

        // Update button states
        const editBtn = document.getElementById('editBtn');
        const saveBtn = document.getElementById('saveBtn');
        const cancelBtn = document.getElementById('cancelBtn');

        if (editBtn) editBtn.style.display = enabled ? 'none' : 'block';
        if (saveBtn) saveBtn.style.display = enabled ? 'block' : 'none';
        if (cancelBtn) cancelBtn.style.display = enabled ? 'block' : 'none';
    }

    /**
     * Cancel editing and restore original content
     */
    function cancelEdit() {
        const editables = document.querySelectorAll('.cms-editable');

        editables.forEach(element => {
            const field = element.dataset.field || element.dataset.cmsField || element.dataset.cmsKey;
            if (field && elementMetadata.has(field)) {
                const metadata = elementMetadata.get(field);
                if (metadata.originalContent) {
                    element.innerHTML = metadata.originalContent;
                }
            }
        });

        setEditMode(false);
    }

    /**
     * Get CSRF token from meta tag or cookie
     */
    function getCsrfToken() {
        // Try meta tag first
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.content;
        }

        // Try cookie
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'XSRF-TOKEN') {
                return decodeURIComponent(value);
            }
        }

        return '';
    }

    /**
     * Initialize the CMS editor when DOM is ready
     */
    function initialize() {
        console.log('Initializing CMS Editor with File Persistence...');

        // Scan content on load
        scanContent();

        // Set up global functions for buttons
        window.cmsEditor = {
            setEditMode: setEditMode,
            saveChanges: saveChanges,
            cancelEdit: cancelEdit,
            scanContent: scanContent,
            getMetadata: () => Array.from(elementMetadata.entries())
        };

        // Auto-initialize edit buttons if they exist
        const editBtn = document.getElementById('editBtn');
        const saveBtn = document.getElementById('saveBtn');
        const cancelBtn = document.getElementById('cancelBtn');

        if (editBtn) editBtn.addEventListener('click', () => setEditMode(true));
        if (saveBtn) saveBtn.addEventListener('click', saveChanges);
        if (cancelBtn) cancelBtn.addEventListener('click', cancelEdit);

        console.log('CMS Editor initialized. Use cmsEditor.setEditMode(true) to start editing.');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

})();