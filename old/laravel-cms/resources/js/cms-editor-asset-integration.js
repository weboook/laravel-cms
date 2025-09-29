/**
 * Laravel CMS Editor Asset Integration
 * Seamlessly integrates the Asset Library with the CMS Editor
 * Handles image insertion, replacement, and inline editing
 */

// Extend the existing CMS Editor with asset library functionality
(function() {
    'use strict';

    // Wait for CMS Editor to be available
    if (typeof LaravelCMSEditor === 'undefined') {
        document.addEventListener('DOMContentLoaded', initAssetIntegration);
    } else {
        initAssetIntegration();
    }

    function initAssetIntegration() {
        if (typeof LaravelCMSEditor === 'undefined') {
            console.log('LaravelCMSEditor not found. Using standalone asset library mode.');
            // Create a minimal compatibility layer for standalone mode
            window.LaravelCMSEditor = {
                prototype: {
                    openAssetLibrary: function() {
                        if (window.assetLibrary) {
                            window.assetLibrary.open();
                        }
                    },
                    insertAsset: function(asset) {
                        console.log('Asset selected:', asset);
                    },
                    replaceAsset: function(element, asset) {
                        if (element.tagName === 'IMG') {
                            element.src = asset.url;
                            element.alt = asset.name || '';
                        }
                    }
                }
            };
            return;
        }

        // Extend the editor prototype with asset management methods
        extendEditorPrototype();

        // Initialize asset library integration for existing editor instances
        if (window.cmsEditor) {
            initializeEditorAssetLibrary(window.cmsEditor);
        }

        // Hook into editor initialization for future instances
        const originalInit = LaravelCMSEditor.prototype.init;
        LaravelCMSEditor.prototype.init = function() {
            const result = originalInit.call(this);
            initializeEditorAssetLibrary(this);
            return result;
        };
    }

    function extendEditorPrototype() {
        // Initialize asset library integration
        LaravelCMSEditor.prototype.initAssetLibrary = function() {
            this.assetLibrary = null;
            this.assetIntegration = new AssetIntegration(this);

            // Add asset library toolbar button
            this.addAssetLibraryButton();

            // Override existing image/media handlers
            this.overrideMediaHandlers();

            // Add drag and drop support for assets
            this.initAssetDragDrop();

            // Add keyboard shortcuts for asset library
            this.addAssetKeyboardShortcuts();

            // Initialize asset placeholders and lazy loading
            this.initAssetPlaceholders();
        };

        // Add asset library button to toolbar
        LaravelCMSEditor.prototype.addAssetLibraryButton = function() {
            const toolbar = this.getToolbar();

            if (toolbar) {
                const assetButton = this.createToolbarButton({
                    id: 'asset-library',
                    icon: 'icon-images',
                    title: 'Media Library',
                    tooltip: 'Open media library (Ctrl+M)',
                    group: 'media',
                    onClick: () => this.openAssetLibrary()
                });

                // Add button to media group or create new group
                const mediaGroup = toolbar.querySelector('.cms-toolbar-group-media') ||
                                  this.createToolbarGroup('media', 'Media');

                mediaGroup.appendChild(assetButton);

                // Also add to context menu for images
                this.addContextMenuItem('image', {
                    label: 'Replace Image',
                    icon: 'icon-replace',
                    action: (element) => this.replaceImage(element)
                });

                this.addContextMenuItem('image', {
                    label: 'Edit Image',
                    icon: 'icon-edit',
                    action: (element) => this.editImage(element)
                });
            }
        };

        // Open asset library with specific options
        LaravelCMSEditor.prototype.openAssetLibrary = function(options = {}) {
            const defaultOptions = {
                mode: 'single',
                type: 'all',
                allowUpload: true,
                allowEdit: true,
                allowDelete: false,
                onSelect: (asset) => this.handleAssetSelection(asset, options),
                onClose: () => this.focusEditor()
            };

            const mergedOptions = { ...defaultOptions, ...options };

            // Create or reuse asset library instance
            if (!this.assetLibrary || this.assetLibrary.isDestroyed) {
                this.assetLibrary = new AssetLibrary(mergedOptions);
            } else {
                // Update options for existing instance
                Object.assign(this.assetLibrary.options, mergedOptions);
            }

            this.assetLibrary.open();

            // Track that asset library is open
            this.isAssetLibraryOpen = true;

            // Pause editor auto-save while library is open
            this.pauseAutoSave();

            return this.assetLibrary;
        };

        // Handle asset selection from library
        LaravelCMSEditor.prototype.handleAssetSelection = function(asset, options = {}) {
            this.isAssetLibraryOpen = false;
            this.resumeAutoSave();

            if (options.targetElement) {
                // Replacing existing element
                this.replaceMediaElement(options.targetElement, asset);
            } else if (options.insertAt) {
                // Insert at specific position
                this.insertAssetAtPosition(asset, options.insertAt);
            } else {
                // Insert at current cursor position
                this.insertAsset(asset, options);
            }

            // Track the change for undo/redo
            this.trackChange({
                type: 'asset_insert',
                asset: asset,
                timestamp: Date.now()
            });

            // Focus back to editor
            this.focusEditor();
        };

        // Insert asset at current cursor position
        LaravelCMSEditor.prototype.insertAsset = function(asset, options = {}) {
            const selection = this.getSelection();
            const range = this.getRange();

            if (!range) {
                console.warn('No cursor position available for asset insertion');
                return;
            }

            let element;

            switch (asset.type) {
                case 'image':
                    element = this.createImageElement(asset, options);
                    break;
                case 'video':
                    element = this.createVideoElement(asset, options);
                    break;
                case 'audio':
                    element = this.createAudioElement(asset, options);
                    break;
                case 'document':
                    element = this.createDocumentLink(asset, options);
                    break;
                default:
                    element = this.createGenericAssetLink(asset, options);
            }

            if (element) {
                // Insert element at cursor position
                range.deleteContents();
                range.insertNode(element);

                // Move cursor after inserted element
                range.setStartAfter(element);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);

                // Mark element as CMS-managed
                element.dataset.cmsAsset = 'true';
                element.dataset.cmsAssetId = asset.id;
                element.dataset.cmsAssetType = asset.type;

                // Initialize editing capabilities
                this.initializeAssetElement(element, asset);

                // Trigger asset inserted event
                this.trigger('asset:inserted', { asset, element, options });

                // Save change
                this.saveAssetInsertion(element, asset);
            }
        };

        // Create image element from asset
        LaravelCMSEditor.prototype.createImageElement = function(asset, options = {}) {
            const img = document.createElement('img');

            // Set source with responsive image support
            if (asset.responsive_images && asset.responsive_images.length > 0) {
                img.src = asset.responsive_images.medium?.url || asset.url;
                img.srcset = this.buildSrcSet(asset.responsive_images);
                img.sizes = options.sizes || '(max-width: 768px) 100vw, 50vw';
            } else {
                img.src = asset.url;
            }

            // Set attributes
            img.alt = asset.alt_text || asset.title || '';
            img.title = asset.title || asset.filename;

            // Add responsive classes if configured
            if (options.responsive !== false) {
                img.className = 'cms-responsive-image';
            }

            // Add lazy loading
            if (options.lazy !== false) {
                img.loading = 'lazy';
            }

            // Set dimensions if available
            if (asset.metadata?.width && asset.metadata?.height) {
                img.width = asset.metadata.width;
                img.height = asset.metadata.height;
            }

            // Add caption wrapper if caption exists
            if (asset.caption) {
                const figure = document.createElement('figure');
                const figcaption = document.createElement('figcaption');

                figure.appendChild(img);
                figcaption.textContent = asset.caption;
                figure.appendChild(figcaption);

                figure.className = 'cms-image-figure';
                return figure;
            }

            return img;
        };

        // Create video element from asset
        LaravelCMSEditor.prototype.createVideoElement = function(asset, options = {}) {
            const video = document.createElement('video');

            video.src = asset.url;
            video.controls = options.controls !== false;
            video.preload = options.preload || 'metadata';

            if (asset.thumbnails?.medium) {
                video.poster = asset.thumbnails.medium;
            }

            if (asset.metadata?.width && asset.metadata?.height) {
                video.width = asset.metadata.width;
                video.height = asset.metadata.height;
            }

            video.className = 'cms-video';

            // Add accessibility attributes
            if (asset.alt_text) {
                video.setAttribute('aria-label', asset.alt_text);
            }

            return video;
        };

        // Create audio element from asset
        LaravelCMSEditor.prototype.createAudioElement = function(asset, options = {}) {
            const audio = document.createElement('audio');

            audio.src = asset.url;
            audio.controls = options.controls !== false;
            audio.preload = options.preload || 'metadata';
            audio.className = 'cms-audio';

            return audio;
        };

        // Create document link from asset
        LaravelCMSEditor.prototype.createDocumentLink = function(asset, options = {}) {
            const link = document.createElement('a');

            link.href = asset.url;
            link.textContent = asset.title || asset.filename;
            link.title = `Download ${asset.filename} (${this.formatFileSize(asset.size)})`;
            link.className = 'cms-document-link';

            // Open in new tab by default for documents
            if (options.newTab !== false) {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }

            // Add download attribute
            link.download = asset.filename;

            // Add file type indicator
            if (asset.extension) {
                const typeSpan = document.createElement('span');
                typeSpan.className = 'cms-file-type';
                typeSpan.textContent = asset.extension.toUpperCase();
                link.appendChild(typeSpan);
            }

            return link;
        };

        // Replace existing media element with new asset
        LaravelCMSEditor.prototype.replaceMediaElement = function(element, asset) {
            const newElement = this.createElementFromAsset(asset);

            if (newElement) {
                // Copy over any existing classes (except CMS-specific ones)
                const existingClasses = Array.from(element.classList)
                    .filter(cls => !cls.startsWith('cms-'));

                newElement.classList.add(...existingClasses);

                // Preserve data attributes
                for (const attr of element.attributes) {
                    if (attr.name.startsWith('data-') && !attr.name.startsWith('data-cms-')) {
                        newElement.setAttribute(attr.name, attr.value);
                    }
                }

                // Replace element
                element.parentNode.replaceChild(newElement, element);

                // Update tracking
                this.initializeAssetElement(newElement, asset);

                // Trigger replacement event
                this.trigger('asset:replaced', {
                    oldElement: element,
                    newElement: newElement,
                    asset: asset
                });

                // Save change
                this.saveAssetReplacement(element, newElement, asset);

                return newElement;
            }

            return null;
        };

        // Replace image specifically
        LaravelCMSEditor.prototype.replaceImage = function(imageElement) {
            const currentAssetId = imageElement.dataset.cmsAssetId;

            this.openAssetLibrary({
                mode: 'single',
                type: 'image',
                currentAsset: currentAssetId,
                targetElement: imageElement,
                onSelect: (asset) => {
                    this.replaceMediaElement(imageElement, asset);
                }
            });
        };

        // Edit image using image editor
        LaravelCMSEditor.prototype.editImage = function(imageElement) {
            const assetId = imageElement.dataset.cmsAssetId;

            if (!assetId) {
                this.showNotification('This image cannot be edited', 'warning');
                return;
            }

            // Fetch asset data
            this.fetchAsset(assetId)
                .then(asset => {
                    if (asset.type !== 'image') {
                        this.showNotification('Only images can be edited', 'warning');
                        return;
                    }

                    // Open image editor
                    const imageEditor = new ImageEditor(asset, {
                        onSave: (editedAsset, savedAsCopy) => {
                            if (!savedAsCopy) {
                                // Update the existing image
                                this.updateImageElement(imageElement, editedAsset);
                            } else {
                                // Replace with the new copy
                                this.replaceMediaElement(imageElement, editedAsset);
                            }

                            this.showNotification('Image updated successfully', 'success');
                        },
                        onCancel: () => {
                            this.focusEditor();
                        }
                    });

                    this.pauseAutoSave();
                })
                .catch(error => {
                    this.showNotification('Failed to load image for editing', 'error');
                    console.error('Image edit error:', error);
                });
        };

        // Override default image/media click handlers
        LaravelCMSEditor.prototype.overrideMediaHandlers = function() {
            // Store original handlers
            this._originalHandlers = this._originalHandlers || {};

            // Override image click handler
            this.on('element:click', (element, event) => {
                if (element.tagName === 'IMG' && element.dataset.cmsAsset) {
                    event.preventDefault();
                    this.showImageContextMenu(element, event);
                }
            });

            // Override image double-click handler
            this.on('element:dblclick', (element, event) => {
                if (element.tagName === 'IMG' && element.dataset.cmsAsset) {
                    event.preventDefault();
                    this.replaceImage(element);
                }
            });

            // Add hover effects for asset elements
            this.on('element:mouseenter', (element) => {
                if (element.dataset.cmsAsset) {
                    element.classList.add('cms-asset-hover');
                    this.showAssetTooltip(element);
                }
            });

            this.on('element:mouseleave', (element) => {
                if (element.dataset.cmsAsset) {
                    element.classList.remove('cms-asset-hover');
                    this.hideAssetTooltip();
                }
            });
        };

        // Initialize drag and drop for assets
        LaravelCMSEditor.prototype.initAssetDragDrop = function() {
            const editorContainer = this.getEditorContainer();

            // Handle file drops
            editorContainer.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                editorContainer.classList.add('cms-drag-over');
            });

            editorContainer.addEventListener('dragleave', (e) => {
                if (!editorContainer.contains(e.relatedTarget)) {
                    editorContainer.classList.remove('cms-drag-over');
                }
            });

            editorContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                editorContainer.classList.remove('cms-drag-over');

                const files = Array.from(e.dataTransfer.files);
                if (files.length > 0) {
                    this.handleFilesDrop(files, e);
                }
            });

            // Handle asset element dragging within editor
            editorContainer.addEventListener('dragstart', (e) => {
                if (e.target.dataset.cmsAsset) {
                    e.dataTransfer.setData('text/cms-asset-id', e.target.dataset.cmsAssetId);
                    e.dataTransfer.effectAllowed = 'move';
                }
            });
        };

        // Handle dropped files
        LaravelCMSEditor.prototype.handleFilesDrop = function(files, event) {
            // Get drop position
            const range = this.getRangeFromPoint(event.clientX, event.clientY);

            if (range) {
                // Set cursor to drop position
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            }

            // Upload files
            this.uploadFiles(files)
                .then(assets => {
                    assets.forEach(asset => {
                        this.insertAsset(asset);
                    });
                })
                .catch(error => {
                    this.showNotification('Failed to upload files', 'error');
                    console.error('File upload error:', error);
                });
        };

        // Add keyboard shortcuts for asset library
        LaravelCMSEditor.prototype.addAssetKeyboardShortcuts = function() {
            this.addKeyboardShortcut('Ctrl+M', () => {
                this.openAssetLibrary();
            }, 'Open Media Library');

            this.addKeyboardShortcut('Ctrl+Shift+M', () => {
                this.openAssetLibrary({ mode: 'multiple' });
            }, 'Open Media Library (Multiple Selection)');

            this.addKeyboardShortcut('Ctrl+Alt+I', () => {
                this.openAssetLibrary({ type: 'image' });
            }, 'Insert Image');

            // Context-sensitive shortcuts
            this.addKeyboardShortcut('F2', () => {
                const selected = this.getSelectedElement();
                if (selected && selected.dataset.cmsAsset) {
                    if (selected.tagName === 'IMG') {
                        this.editImage(selected);
                    } else {
                        this.replaceMediaElement(selected);
                    }
                }
            }, 'Edit Selected Asset');
        };

        // Initialize asset placeholders and lazy loading
        LaravelCMSEditor.prototype.initAssetPlaceholders = function() {
            // Find all existing asset elements and initialize them
            const assetElements = this.container.querySelectorAll('[data-cms-asset]');
            assetElements.forEach(element => {
                this.initializeAssetElement(element);
            });

            // Set up intersection observer for lazy loading
            this.initLazyLoading();
        };

        // Show context menu for images
        LaravelCMSEditor.prototype.showImageContextMenu = function(imageElement, event) {
            const contextMenu = this.createContextMenu([
                {
                    label: 'Replace Image',
                    icon: 'icon-replace',
                    action: () => this.replaceImage(imageElement)
                },
                {
                    label: 'Edit Image',
                    icon: 'icon-edit',
                    action: () => this.editImage(imageElement)
                },
                {
                    label: 'Copy Image URL',
                    icon: 'icon-copy',
                    action: () => this.copyImageUrl(imageElement)
                },
                {
                    label: 'View Full Size',
                    icon: 'icon-expand',
                    action: () => this.viewFullSizeImage(imageElement)
                },
                { separator: true },
                {
                    label: 'Remove Image',
                    icon: 'icon-trash',
                    action: () => this.removeElement(imageElement),
                    className: 'danger'
                }
            ]);

            this.showContextMenuAt(contextMenu, event.clientX, event.clientY);
        };

        // Utility methods
        LaravelCMSEditor.prototype.buildSrcSet = function(responsiveImages) {
            return Object.entries(responsiveImages)
                .map(([size, data]) => `${data.url} ${data.width}w`)
                .join(', ');
        };

        LaravelCMSEditor.prototype.formatFileSize = function(bytes) {
            if (!bytes) return '0 B';
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
        };

        LaravelCMSEditor.prototype.fetchAsset = function(assetId) {
            return fetch(`/cms/api/assets/${assetId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => data.data);
        };

        LaravelCMSEditor.prototype.updateImageElement = function(imgElement, asset) {
            imgElement.src = asset.url;
            imgElement.alt = asset.alt_text || '';
            imgElement.title = asset.title || asset.filename;

            // Update responsive images if available
            if (asset.responsive_images) {
                imgElement.srcset = this.buildSrcSet(asset.responsive_images);
            }

            // Update data attributes
            imgElement.dataset.cmsAssetId = asset.id;
        };

        LaravelCMSEditor.prototype.saveAssetInsertion = function(element, asset) {
            // Implementation depends on your CMS save mechanism
            this.saveChange({
                type: 'asset_insert',
                element: this.getElementSelector(element),
                asset_id: asset.id,
                asset_type: asset.type,
                timestamp: Date.now()
            });
        };

        LaravelCMSEditor.prototype.saveAssetReplacement = function(oldElement, newElement, asset) {
            this.saveChange({
                type: 'asset_replace',
                element: this.getElementSelector(newElement),
                old_asset_id: oldElement.dataset.cmsAssetId,
                new_asset_id: asset.id,
                timestamp: Date.now()
            });
        };

        // Upload files and return promise of assets
        LaravelCMSEditor.prototype.uploadFiles = function(files) {
            const uploads = files.map(file => this.uploadSingleFile(file));
            return Promise.all(uploads);
        };

        LaravelCMSEditor.prototype.uploadSingleFile = function(file) {
            const formData = new FormData();
            formData.append('file', file);

            return fetch('/cms/api/assets/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    return data.data;
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            });
        };
    }

    // Asset Integration Helper Class
    class AssetIntegration {
        constructor(editor) {
            this.editor = editor;
            this.bindAssetEvents();
        }

        bindAssetEvents() {
            // Listen for asset library events
            document.addEventListener('cms:asset:selected', (e) => {
                if (this.editor.isAssetLibraryOpen) {
                    this.editor.handleAssetSelection(e.detail.asset, e.detail.options);
                }
            });

            document.addEventListener('cms:asset:uploaded', (e) => {
                this.editor.trigger('asset:uploaded', e.detail);
            });

            document.addEventListener('cms:asset:deleted', (e) => {
                this.handleAssetDeleted(e.detail.asset);
            });
        }

        handleAssetDeleted(asset) {
            // Find and remove/replace elements using this asset
            const elements = this.editor.container.querySelectorAll(`[data-cms-asset-id="${asset.id}"]`);

            elements.forEach(element => {
                // Show confirmation before removing
                if (confirm(`The asset "${asset.filename}" has been deleted. Remove this element from the page?`)) {
                    element.remove();
                    this.editor.saveChange({
                        type: 'asset_remove',
                        asset_id: asset.id,
                        timestamp: Date.now()
                    });
                }
            });
        }
    }

    function initializeEditorAssetLibrary(editor) {
        if (editor && typeof editor.initAssetLibrary === 'function') {
            editor.initAssetLibrary();
        }
    }

})();