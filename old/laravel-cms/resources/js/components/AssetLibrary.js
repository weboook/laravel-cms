/**
 * Laravel CMS Asset Library Component
 * Comprehensive frontend interface similar to WordPress Media Library
 * Features: Grid/List view, Search, Filters, Upload, Edit, Folder management
 * AI-powered tagging, Virtual scrolling, Touch gestures, Accessibility
 */
class AssetLibrary {
    constructor(options = {}) {
        this.options = {
            mode: 'single', // single, multiple, gallery
            type: 'all', // all, image, video, document
            onSelect: null,
            onClose: null,
            allowUpload: true,
            allowDelete: false,
            allowEdit: true,
            maxFiles: null,
            maxSize: 10485760, // 10MB
            currentAsset: null,
            ...options
        };

        this.state = {
            assets: [],
            selectedAssets: [],
            folders: [],
            currentFolder: null,
            view: 'grid', // grid, list
            sortBy: 'created_at', // created_at, name, size, type
            sortOrder: 'desc',
            search: '',
            page: 1,
            hasMore: true,
            loading: false,
            uploading: false,
            filters: {
                type: 'all',
                dateRange: null,
                tags: []
            },
            currentTab: 'browse',
            detailsAsset: null
        };

        this.uploads = new Map(); // Track active uploads
        this.shortcuts = new Map(); // Keyboard shortcuts
        this.dropzoneIntegration = null; // Dropzone.js integration

        this.init();
    }

    // Core Methods
    init() {
        this.createModal();
        this.bindEvents();

        // Initialize touch handlers after modal is created
        if ('ontouchstart' in window && typeof AssetLibraryTouch !== 'undefined') {
            // Delay touch handler initialization to ensure modal is fully created
            setTimeout(() => {
                this.touchHandler = new AssetLibraryTouch(this);
            }, 100);
        }

        // Initialize Dropzone integration
        if (this.options.allowUpload && typeof DropzoneIntegration !== 'undefined') {
            setTimeout(() => {
                this.dropzoneIntegration = new DropzoneIntegration(this);
            }, 200);
        }

        this.loadAssets();
        this.loadFolders();
    }

    // Modal Creation
    createModal() {
        const modalHTML = `
            <div class="cms-asset-library-modal" id="cms-asset-library" role="dialog" aria-labelledby="cms-modal-title" aria-modal="true">
                <div class="cms-modal-overlay"></div>
                <div class="cms-modal-container">
                    <!-- Header -->
                    <div class="cms-modal-header">
                        <h2 id="cms-modal-title">Media Library</h2>
                        <div class="cms-modal-tabs" role="tablist">
                            <button class="cms-tab active" data-tab="browse" role="tab" aria-selected="true" aria-controls="tab-browse">
                                <i class="icon-browse"></i> Browse
                            </button>
                            ${this.options.allowUpload ? `
                                <button class="cms-tab" data-tab="upload" role="tab" aria-selected="false" aria-controls="tab-upload">
                                    <i class="icon-upload"></i> Upload
                                </button>
                            ` : ''}
                            ${this.options.mode === 'gallery' ? `
                                <button class="cms-tab" data-tab="gallery" role="tab" aria-selected="false" aria-controls="tab-gallery">
                                    <i class="icon-gallery"></i> Create Gallery
                                </button>
                            ` : ''}
                        </div>
                        <button class="cms-modal-close" aria-label="Close media library">
                            <i class="icon-close"></i>
                        </button>
                    </div>

                    <!-- Toolbar -->
                    <div class="cms-modal-toolbar">
                        <div class="cms-toolbar-left">
                            <div class="cms-search-container">
                                <input type="text" class="cms-search" placeholder="Search media..." aria-label="Search media files">
                                <button class="cms-search-clear" aria-label="Clear search">
                                    <i class="icon-clear"></i>
                                </button>
                            </div>
                            <select class="cms-filter-type" aria-label="Filter by type">
                                <option value="all">All Media</option>
                                <option value="image">Images</option>
                                <option value="video">Videos</option>
                                <option value="audio">Audio</option>
                                <option value="document">Documents</option>
                            </select>
                            <select class="cms-filter-date" aria-label="Filter by date">
                                <option value="">All Dates</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                        <div class="cms-toolbar-right">
                            <div class="cms-view-controls">
                                <button class="cms-view-toggle active" data-view="grid" aria-label="Grid view">
                                    <i class="icon-grid"></i>
                                </button>
                                <button class="cms-view-toggle" data-view="list" aria-label="List view">
                                    <i class="icon-list"></i>
                                </button>
                            </div>
                            <select class="cms-sort" aria-label="Sort by">
                                <option value="created_at-desc">Newest First</option>
                                <option value="created_at-asc">Oldest First</option>
                                <option value="filename-asc">Name (A-Z)</option>
                                <option value="filename-desc">Name (Z-A)</option>
                                <option value="size-desc">Largest First</option>
                                <option value="size-asc">Smallest First</option>
                            </select>
                            <div class="cms-grid-size-slider" style="display: none;">
                                <input type="range" min="100" max="300" value="150" class="cms-grid-size" aria-label="Grid size">
                            </div>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="cms-modal-content">
                        <!-- Sidebar -->
                        <div class="cms-modal-sidebar">
                            <div class="cms-folder-tree">
                                <h3>Folders</h3>
                                <ul class="cms-folders" role="tree">
                                    <li class="cms-folder-item active" data-folder-id="" role="treeitem" aria-expanded="true">
                                        <i class="icon-folder"></i> All Files
                                        <span class="cms-folder-count">(0)</span>
                                    </li>
                                    <!-- Dynamic folders -->
                                </ul>
                                ${this.options.allowUpload ? `
                                    <button class="cms-add-folder" aria-label="Create new folder">
                                        <i class="icon-plus"></i> New Folder
                                    </button>
                                ` : ''}
                            </div>

                            <div class="cms-recent-uploads">
                                <h3>Recent Uploads</h3>
                                <div class="cms-recent-list">
                                    <!-- Recent uploads -->
                                </div>
                            </div>

                            <div class="cms-favorites">
                                <h3>Favorites</h3>
                                <div class="cms-favorites-list">
                                    <!-- Favorite assets -->
                                </div>
                            </div>

                            ${this.options.allowUpload ? `
                                <div class="cms-upload-area">
                                    <div class="cms-dropzone">
                                        <i class="icon-upload"></i>
                                        <p>Drop files here or</p>
                                        <button class="cms-browse-button">Browse Files</button>
                                        <input type="file" class="cms-file-input" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt,.zip">
                                        <p class="cms-upload-hint">Max size: ${this.formatFileSize(this.options.maxSize)}</p>
                                    </div>
                                </div>
                            ` : ''}

                            <div class="cms-upload-progress" style="display: none;">
                                <h3>Uploading</h3>
                                <div class="cms-upload-queue">
                                    <!-- Upload progress items -->
                                </div>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="cms-modal-main">
                            <!-- Browse Tab -->
                            <div class="cms-tab-content active" id="tab-browse" data-tab="browse" role="tabpanel">
                                <div class="cms-grid-container">
                                    <div class="cms-asset-grid" role="grid" aria-label="Media files">
                                        <!-- Dynamic asset items -->
                                    </div>
                                    <div class="cms-virtual-scroll-spacer" style="height: 0;"></div>
                                </div>
                                <div class="cms-load-more">
                                    <button class="cms-load-more-btn">Load More</button>
                                </div>
                                <div class="cms-no-results" style="display: none;">
                                    <i class="icon-empty"></i>
                                    <h3>No media files found</h3>
                                    <p>Try adjusting your search or filters</p>
                                </div>
                                <div class="cms-loading" style="display: none;">
                                    <div class="cms-loading-spinner" role="status" aria-label="Loading"></div>
                                </div>
                            </div>

                            <!-- Upload Tab -->
                            ${this.options.allowUpload ? `
                                <div class="cms-tab-content" id="tab-upload" data-tab="upload" role="tabpanel">
                                    <div class="cms-upload-container">
                                        <div class="cms-large-dropzone">
                                            <i class="icon-cloud-upload"></i>
                                            <h3>Drop files anywhere to upload</h3>
                                            <p>or</p>
                                            <button class="cms-select-files-btn">Select Files</button>
                                            <input type="file" class="cms-bulk-file-input" multiple style="display: none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt,.zip">
                                            <div class="cms-upload-options">
                                                <label>
                                                    <input type="checkbox" class="cms-auto-optimize"> Optimize images on upload
                                                </label>
                                                <label>
                                                    <input type="checkbox" class="cms-generate-webp"> Generate WebP versions
                                                </label>
                                                <select class="cms-upload-folder">
                                                    <option value="">Select folder...</option>
                                                    <!-- Dynamic folder options -->
                                                </select>
                                            </div>
                                            <p class="cms-upload-hint">
                                                Maximum file size: ${this.formatFileSize(this.options.maxSize)}<br>
                                                Supported formats: Images, Videos, Audio, Documents
                                            </p>
                                        </div>

                                        <div class="cms-external-upload">
                                            <h3>Import from URL</h3>
                                            <div class="cms-url-upload">
                                                <input type="url" class="cms-url-input" placeholder="Enter image URL...">
                                                <button class="cms-url-upload-btn">Import</button>
                                            </div>
                                        </div>

                                        <div class="cms-ai-suggestions">
                                            <h3>AI-Powered Stock Photos</h3>
                                            <div class="cms-stock-search">
                                                <input type="text" class="cms-stock-query" placeholder="Search Unsplash...">
                                                <button class="cms-stock-search-btn">Search</button>
                                            </div>
                                            <div class="cms-stock-results">
                                                <!-- Stock photo results -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}

                            <!-- Gallery Tab -->
                            ${this.options.mode === 'gallery' ? `
                                <div class="cms-tab-content" id="tab-gallery" data-tab="gallery" role="tabpanel">
                                    <div class="cms-gallery-builder">
                                        <h3>Create Gallery</h3>
                                        <div class="cms-gallery-options">
                                            <select class="cms-gallery-type">
                                                <option value="grid">Grid Gallery</option>
                                                <option value="masonry">Masonry Gallery</option>
                                                <option value="slider">Image Slider</option>
                                                <option value="lightbox">Lightbox Gallery</option>
                                            </select>
                                            <input type="number" class="cms-gallery-columns" value="3" min="1" max="6" placeholder="Columns">
                                        </div>
                                        <div class="cms-gallery-preview">
                                            <div class="cms-gallery-items sortable">
                                                <!-- Selected gallery items -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>

                        <!-- Details Sidebar -->
                        <div class="cms-modal-details">
                            <div class="cms-details-header">
                                <h3>Details</h3>
                                <button class="cms-details-close" aria-label="Close details">
                                    <i class="icon-close"></i>
                                </button>
                            </div>

                            <div class="cms-asset-preview">
                                <!-- Preview image/video -->
                            </div>

                            <div class="cms-asset-info">
                                <div class="cms-field-group">
                                    <label for="cms-asset-title">Title</label>
                                    <input type="text" id="cms-asset-title" class="cms-asset-title">
                                </div>

                                <div class="cms-field-group">
                                    <label for="cms-asset-alt">Alt Text</label>
                                    <input type="text" id="cms-asset-alt" class="cms-asset-alt" aria-describedby="alt-help">
                                    <small id="alt-help">Describe the image for screen readers</small>
                                </div>

                                <div class="cms-field-group">
                                    <label for="cms-asset-caption">Caption</label>
                                    <textarea id="cms-asset-caption" class="cms-asset-caption" rows="3"></textarea>
                                </div>

                                <div class="cms-field-group">
                                    <label for="cms-asset-description">Description</label>
                                    <textarea id="cms-asset-description" class="cms-asset-description" rows="4"></textarea>
                                </div>

                                <div class="cms-asset-meta">
                                    <h4>File Information</h4>
                                    <dl>
                                        <dt>Filename:</dt>
                                        <dd class="filename">—</dd>
                                        <dt>File size:</dt>
                                        <dd class="filesize">—</dd>
                                        <dt>Dimensions:</dt>
                                        <dd class="dimensions">—</dd>
                                        <dt>Uploaded:</dt>
                                        <dd class="uploaded">—</dd>
                                        <dt>Modified:</dt>
                                        <dd class="modified">—</dd>
                                    </dl>
                                </div>

                                <div class="cms-asset-urls">
                                    <h4>File URLs</h4>
                                    <div class="cms-url-field">
                                        <label>Original:</label>
                                        <input type="text" class="file-url" readonly>
                                        <button class="cms-copy-url" aria-label="Copy URL">
                                            <i class="icon-copy"></i>
                                        </button>
                                    </div>
                                    <div class="cms-url-field">
                                        <label>Medium:</label>
                                        <input type="text" class="file-url-medium" readonly>
                                        <button class="cms-copy-url" aria-label="Copy medium URL">
                                            <i class="icon-copy"></i>
                                        </button>
                                    </div>
                                    <div class="cms-url-field">
                                        <label>Thumbnail:</label>
                                        <input type="text" class="file-url-thumb" readonly>
                                        <button class="cms-copy-url" aria-label="Copy thumbnail URL">
                                            <i class="icon-copy"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="cms-asset-tags">
                                    <h4>Tags</h4>
                                    <div class="cms-tag-input">
                                        <input type="text" placeholder="Add tags..." class="cms-new-tag">
                                        <button class="cms-add-tag">Add</button>
                                    </div>
                                    <div class="cms-tag-list">
                                        <!-- Dynamic tags -->
                                    </div>
                                </div>

                                <div class="cms-asset-actions">
                                    <button class="cms-edit-image" style="display: none;">
                                        <i class="icon-edit"></i> Edit Image
                                    </button>
                                    <button class="cms-replace-file">
                                        <i class="icon-replace"></i> Replace File
                                    </button>
                                    <button class="cms-download-file">
                                        <i class="icon-download"></i> Download
                                    </button>
                                    <button class="cms-duplicate-asset">
                                        <i class="icon-duplicate"></i> Duplicate
                                    </button>
                                    <button class="cms-favorite-toggle">
                                        <i class="icon-star"></i> Add to Favorites
                                    </button>
                                    ${this.options.allowDelete ? `
                                        <button class="cms-delete-asset danger">
                                            <i class="icon-trash"></i> Delete
                                        </button>
                                    ` : ''}
                                </div>

                                <div class="cms-usage-info">
                                    <h4>Usage</h4>
                                    <div class="cms-usage-list">
                                        <!-- Where this asset is used -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="cms-modal-footer">
                        <div class="cms-selection-info">
                            <span class="cms-selected-count">0 selected</span>
                            <div class="cms-bulk-actions" style="display: none;">
                                <button class="cms-bulk-move">Move to Folder</button>
                                <button class="cms-bulk-tag">Add Tags</button>
                                ${this.options.allowDelete ? '<button class="cms-bulk-delete danger">Delete Selected</button>' : ''}
                            </div>
                        </div>
                        <div class="cms-modal-actions">
                            <button class="cms-cancel-btn">Cancel</button>
                            <button class="cms-select-btn" disabled>
                                ${this.options.mode === 'multiple' ? 'Select Files' : 'Select File'}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Context Menu -->
                <div class="cms-context-menu" style="display: none;">
                    <ul>
                        <li><a href="#" data-action="select">Select</a></li>
                        <li><a href="#" data-action="edit">Edit</a></li>
                        <li><a href="#" data-action="duplicate">Duplicate</a></li>
                        <li><a href="#" data-action="download">Download</a></li>
                        <li class="separator"></li>
                        <li><a href="#" data-action="move">Move to Folder</a></li>
                        <li><a href="#" data-action="favorite">Add to Favorites</a></li>
                        <li class="separator"></li>
                        ${this.options.allowDelete ? '<li><a href="#" data-action="delete" class="danger">Delete</a></li>' : ''}
                    </ul>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('cms-asset-library');

        if (!this.modal) {
            console.error('AssetLibrary: Failed to create modal');
            throw new Error('Failed to create asset library modal');
        }

        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }

    // Asset Grid Item Creation
    createAssetItem(asset, index) {
        const isSelected = this.state.selectedAssets.includes(asset.id);
        const gridSize = this.getCurrentGridSize();

        return `
            <div class="cms-asset-item ${isSelected ? 'selected' : ''}"
                 data-id="${asset.id}"
                 data-type="${asset.type}"
                 data-index="${index}"
                 role="gridcell"
                 tabindex="0"
                 aria-selected="${isSelected}"
                 style="--grid-size: ${gridSize}px;">

                <div class="cms-asset-preview">
                    ${this.getAssetPreview(asset)}
                    <div class="cms-asset-overlay">
                        <div class="cms-asset-item-checkbox">
                            <input type="checkbox" ${isSelected ? 'checked' : ''}
                                   aria-label="Select ${asset.title || asset.filename}">
                        </div>
                        <div class="cms-asset-quick-actions">
                            <button class="cms-quick-view" title="Quick View" aria-label="Quick view ${asset.title || asset.filename}">
                                <i class="icon-eye"></i>
                            </button>
                            ${this.options.allowEdit ? `
                                <button class="cms-quick-edit" title="Edit" aria-label="Edit ${asset.title || asset.filename}">
                                    <i class="icon-edit"></i>
                                </button>
                            ` : ''}
                            <button class="cms-quick-select" title="Select" aria-label="Select ${asset.title || asset.filename}">
                                <i class="icon-check"></i>
                            </button>
                        </div>
                    </div>

                    ${asset.type === 'image' && asset.responsive_images ? `
                        <div class="cms-responsive-indicator" title="Responsive images available">
                            <i class="icon-responsive"></i>
                        </div>
                    ` : ''}

                    ${asset.is_favorite ? `
                        <div class="cms-favorite-indicator" title="Favorite">
                            <i class="icon-star-filled"></i>
                        </div>
                    ` : ''}

                    <div class="cms-asset-type-badge">${asset.type}</div>
                </div>

                <div class="cms-asset-info">
                    <p class="cms-asset-name" title="${asset.title || asset.filename}">
                        ${this.truncate(asset.title || asset.filename, 20)}
                    </p>
                    <p class="cms-asset-meta">
                        ${asset.metadata?.width && asset.metadata?.height ?
                            `${asset.metadata.width}×${asset.metadata.height} • ` : ''}
                        ${this.formatFileSize(asset.size)}
                    </p>
                    <p class="cms-asset-date">${this.formatDate(asset.created_at)}</p>
                </div>
            </div>
        `;
    }

    // Asset Preview Generation
    getAssetPreview(asset) {
        switch(asset.type) {
            case 'image':
                const thumbnail = asset.thumbnails?.medium || asset.thumbnails?.thumbnail || asset.url;
                return `
                    <img src="${thumbnail}"
                         alt="${asset.alt_text || ''}"
                         loading="lazy"
                         onerror="this.onerror=null; this.src='${this.getPlaceholderImage(asset.type)}';">
                `;

            case 'video':
                return `
                    <div class="cms-video-preview">
                        ${asset.thumbnails?.medium ?
                            `<img src="${asset.thumbnails.medium}" alt="Video thumbnail" loading="lazy">` :
                            `<div class="cms-video-placeholder"><i class="icon-video"></i></div>`
                        }
                        <div class="cms-play-overlay">
                            <i class="icon-play-circle"></i>
                        </div>
                        <div class="cms-duration">${this.formatDuration(asset.metadata?.duration)}</div>
                    </div>
                `;

            case 'audio':
                return `
                    <div class="cms-audio-preview">
                        <i class="icon-audio"></i>
                        <div class="cms-audio-info">
                            <div class="cms-waveform"></div>
                            <div class="cms-duration">${this.formatDuration(asset.metadata?.duration)}</div>
                        </div>
                    </div>
                `;

            case 'document':
                return `
                    <div class="cms-file-icon cms-file-${asset.extension}">
                        <i class="icon-file-${this.getFileIcon(asset.extension)}"></i>
                        <span class="cms-file-ext">${asset.extension?.toUpperCase() || 'FILE'}</span>
                        ${asset.metadata?.pages ? `<span class="cms-page-count">${asset.metadata.pages} pages</span>` : ''}
                    </div>
                `;

            default:
                return `
                    <div class="cms-file-icon">
                        <i class="icon-file"></i>
                        <span class="cms-file-ext">${asset.extension?.toUpperCase() || 'FILE'}</span>
                    </div>
                `;
        }
    }

    // API Communication Methods
    async loadAssets(page = 1, append = false) {
        if (this.state.loading) return;

        this.state.loading = true;
        this.showLoading();

        const params = new URLSearchParams({
            page: page,
            per_page: 40,
            search: this.state.search,
            sort_by: this.state.sortBy,
            sort_dir: this.state.sortOrder,
            ...this.state.filters
        });

        if (this.state.currentFolder) {
            params.append('folder_id', this.state.currentFolder);
        }

        try {
            const response = await fetch(`${this.options.apiUrl}?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('Failed to load assets');

            const data = await response.json();

            if (append) {
                this.state.assets.push(...data.data);
            } else {
                this.state.assets = data.data;
            }

            this.state.page = page;
            this.state.hasMore = data.meta.current_page < data.meta.last_page;

            this.renderAssets();
            this.updateAssetCount();

        } catch (error) {
            console.error('Error loading assets:', error);
            // Only show error in debug mode or when not a 404
            if (this.options.debug || !error.message.includes('404')) {
                this.showError('Failed to load assets: ' + error.message);
            }
        } finally {
            this.state.loading = false;
            this.hideLoading();
        }
    }

    async loadFolders() {
        try {
            const response = await fetch(`${this.options.apiUrl}/folders`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('Failed to load folders');

            const data = await response.json();
            this.state.folders = data.data;
            this.renderFolders();

        } catch (error) {
            console.error('Error loading folders:', error);
            // Only show error in debug mode or when not a 404
            if (this.options.debug || !error.message.includes('404')) {
                this.showError('Failed to load folders: ' + error.message);
            }
        }
    }

    // Upload Methods
    async uploadFiles(files) {
        const uploadPromises = Array.from(files).map(file => this.uploadFile(file));

        try {
            const results = await Promise.all(uploadPromises);
            const successful = results.filter(r => r.success);

            if (successful.length > 0) {
                this.showSuccess(`Successfully uploaded ${successful.length} file(s)`);
                this.loadAssets(); // Reload assets
            }

            const failed = results.filter(r => !r.success);
            if (failed.length > 0) {
                this.showError(`Failed to upload ${failed.length} file(s)`);
            }

        } catch (error) {
            this.showError('Upload failed: ' + error.message);
        }
    }

    async uploadFile(file) {
        const uploadId = this.generateUploadId();

        // Add to upload queue
        this.uploads.set(uploadId, {
            file: file,
            progress: 0,
            status: 'uploading'
        });

        this.showUploadProgress(uploadId, file);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder_id', this.state.currentFolder || '');

        // Add upload options
        const autoOptimize = this.modal.querySelector('.asset-library-auto-optimize')?.checked;
        const generateWebP = this.modal.querySelector('.asset-library-generate-webp')?.checked;

        if (autoOptimize) formData.append('auto_optimize', '1');
        if (generateWebP) formData.append('generate_webp', '1');

        try {
            const response = await fetch(this.options.uploadUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.uploads.get(uploadId).status = 'completed';
                this.updateUploadProgress(uploadId, 100);

                setTimeout(() => {
                    this.removeUploadProgress(uploadId);
                    this.uploads.delete(uploadId);
                }, 2000);

                return { success: true, asset: data.data };
            } else {
                throw new Error(data.message || 'Upload failed');
            }

        } catch (error) {
            this.uploads.get(uploadId).status = 'failed';
            this.updateUploadProgress(uploadId, 0, error.message);

            setTimeout(() => {
                this.removeUploadProgress(uploadId);
                this.uploads.delete(uploadId);
            }, 5000);

            return { success: false, error: error.message };
        }
    }

    // Event Binding
    bindEvents() {
        // Check if modal exists before binding events
        if (!this.modal) {
            console.error('AssetLibrary: Modal not found, cannot bind events');
            return;
        }

        // Modal close events
        const closeBtn = this.modal.querySelector('.cms-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', () => this.close());

        // Close when clicking outside modal overlay
        const overlay = this.modal.querySelector('.cms-modal-overlay');
        if (overlay) overlay.addEventListener('click', () => this.close());

        // Cancel button
        const cancelBtn = this.modal.querySelector('.cms-cancel-btn');
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.close());

        // Tab switching
        this.modal.querySelectorAll('.cms-tab').forEach(tab => {
            tab.addEventListener('click', (e) => this.switchTab(e.target.dataset.tab));
        });

        // Search and filters
        const searchInput = this.modal.querySelector('.cms-search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.state.search = e.target.value;
                    this.loadAssets();
                }, 300);
            });
        }

        const searchClear = this.modal.querySelector('.cms-search-clear');
        if (searchClear) {
            searchClear.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                    this.state.search = '';
                    this.loadAssets();
                }
            });
        }

        const filterType = this.modal.querySelector('.cms-type-filter');
        if (filterType) {
            filterType.addEventListener('change', (e) => {
                this.state.filters.type = e.target.value;
                this.loadAssets();
            });
        }

        const sortSelect = this.modal.querySelector('.cms-sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                const [sortBy, sortOrder] = e.target.value.split('-');
                this.state.sortBy = sortBy;
                this.state.sortOrder = sortOrder;
                this.loadAssets();
            });
        }

        // View toggles
        this.modal.querySelectorAll('.cms-view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.switchView(e.target.dataset.view));
        });

        // Grid size slider
        const gridSizeSlider = this.modal.querySelector('.cms-grid-size');
        if (gridSizeSlider) {
            gridSizeSlider.addEventListener('input', (e) => {
                this.updateGridSize(e.target.value);
            });
        }

        // File upload - with null checks to prevent errors
        const fileInput = this.modal ? this.modal.querySelector('.cms-file-input') : null;
        const browseButton = this.modal ? this.modal.querySelector('.cms-browse-btn') : null;
        const bulkFileInput = this.modal ? this.modal.querySelector('.cms-bulk-file-input') : null;
        const selectFilesBtn = this.modal ? this.modal.querySelector('.cms-select-files-btn') : null;

        if (browseButton && fileInput) {
            browseButton.addEventListener('click', () => {
                if (fileInput) fileInput.click();
            });
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.uploadFiles(e.target.files);
                }
            });
        }

        if (selectFilesBtn && bulkFileInput) {
            selectFilesBtn.addEventListener('click', () => {
                if (bulkFileInput) bulkFileInput.click();
            });
            bulkFileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.uploadFiles(e.target.files);
                }
            });
        }

        // Load more
        const loadMoreBtn = this.modal.querySelector('.cms-load-more-btn');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', () => {
                this.loadAssets(this.state.page + 1, true);
            });
        }

        // Select button
        const selectBtn = this.modal.querySelector('.cms-select-btn');
        if (selectBtn) {
            selectBtn.addEventListener('click', () => {
                this.selectAssets();
            });
        }

        // Details close
        const detailsCloseBtn = this.modal.querySelector('.cms-details-close');
        if (detailsCloseBtn) {
            detailsCloseBtn.addEventListener('click', () => {
                this.hideDetails();
            });
        }

        // Asset actions
        this.bindAssetActions();
    }

    bindAssetActions() {
        if (!this.modal) return;

        // Delegate events for dynamically created asset items
        this.modal.addEventListener('click', (e) => {
            const assetItem = e.target.closest('.cms-asset-item');
            if (!assetItem) return;

            const assetId = parseInt(assetItem.dataset.id);
            const asset = this.state.assets.find(a => a.id === assetId);

            if (e.target.matches('.cms-asset-item-checkbox input')) {
                this.toggleAssetSelection(assetId);
            } else if (e.target.closest('.asset-library-quick-view')) {
                this.quickView(asset);
            } else if (e.target.closest('.asset-library-quick-edit')) {
                this.editAsset(asset);
            } else if (e.target.closest('.asset-library-quick-select')) {
                this.selectSingleAsset(asset);
            } else if (e.target.closest('.cms-asset-item-preview')) {
                this.showDetails(asset);
            }
        });

        // Context menu
        this.modal.addEventListener('contextmenu', (e) => {
            const assetItem = e.target.closest('.cms-asset-item');
            if (assetItem) {
                e.preventDefault();
                this.showContextMenu(e, assetItem);
            }
        });

        // Double click to select
        this.modal.addEventListener('dblclick', (e) => {
            const assetItem = e.target.closest('.cms-asset-item');
            if (assetItem) {
                const assetId = parseInt(assetItem.dataset.id);
                const asset = this.state.assets.find(a => a.id === assetId);
                this.selectSingleAsset(asset);
            }
        });
    }

    // Rendering Methods
    renderAssets() {
        const container = this.modal.querySelector('.cms-asset-grid');
        const noResults = this.modal.querySelector('.cms-no-results');

        if (this.state.assets.length === 0) {
            container.innerHTML = '';
            noResults.style.display = 'flex';
            return;
        }

        noResults.style.display = 'none';

        // Apply current view
        container.className = `cms-assets-${this.state.view}`;

        const assetsHTML = this.state.assets.map((asset, index) =>
            this.createAssetItem(asset, index)
        ).join('');

        container.innerHTML = assetsHTML;

        // Update load more button
        this.modal.querySelector('.asset-library-load-more').style.display =
            this.state.hasMore ? 'block' : 'none';
    }

    renderFolders() {
        const container = this.modal.querySelector('.cms-folders');

        const foldersHTML = this.state.folders.map(folder =>
            this.createFolderItem(folder)
        ).join('');

        // Keep the "All Files" item and add folders
        const allFilesItem = container.querySelector('li[data-folder-id=""]');
        container.innerHTML = '';
        container.appendChild(allFilesItem);
        container.insertAdjacentHTML('beforeend', foldersHTML);

        // Bind folder events
        container.addEventListener('click', (e) => {
            const folderItem = e.target.closest('.cms-folder-item');
            if (folderItem) {
                this.selectFolder(folderItem.dataset.folderId);
            }
        });
    }

    // Public API Methods
    open() {
        this.modal.style.display = 'flex';
        this.modal.setAttribute('aria-hidden', 'false');
        this.modal.classList.add('active');

        // Don't prevent body scrolling - keep it accessible
        // document.body.style.overflow = 'hidden'; // Commented out to allow scrolling

        // Focus management
        this.previousFocus = document.activeElement;
        const searchInput = this.modal.querySelector('.asset-library-search');
        if (searchInput) searchInput.focus();

        // Trap focus
        this.trapFocus();

        // Load initial data if not already loaded
        if (this.state.assets.length === 0) {
            this.loadAssets();
        }
    }

    close() {
        this.modal.style.display = 'none';
        this.modal.setAttribute('aria-hidden', 'true');
        this.modal.classList.remove('active');

        // Ensure body scrolling is restored
        document.body.style.overflow = '';

        // Restore focus
        if (this.previousFocus) {
            this.previousFocus.focus();
        }

        // Clean up
        this.modal.remove();

        if (this.options.onClose) {
            this.options.onClose();
        }
    }

    selectAssets() {
        const selectedAssets = this.state.assets.filter(asset =>
            this.state.selectedAssets.includes(asset.id)
        );

        if (selectedAssets.length === 0) return;

        if (this.options.onSelect) {
            if (this.options.mode === 'single') {
                this.options.onSelect(selectedAssets[0]);
            } else {
                this.options.onSelect(selectedAssets);
            }
        }

        this.close();
    }

    // Utility Methods
    formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 1) return 'Today';
        if (diffDays === 2) return 'Yesterday';
        if (diffDays <= 7) return `${diffDays} days ago`;

        return date.toLocaleDateString();
    }

    truncate(str, length) {
        if (!str) return '';
        return str.length > length ? str.substring(0, length) + '...' : str;
    }

    // Focus management for accessibility
    trapFocus() {
        if (!this.modal) return;

        const focusableElements = this.modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        // Focus first element
        firstElement.focus();

        // Handle Tab key navigation
        this.modal.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });
    }

    // Update asset count display
    updateAssetCount() {
        if (!this.modal) return;

        const countElement = this.modal.querySelector('.asset-library-asset-count');
        if (countElement) {
            const total = this.state.assets.length;
            const selected = this.state.selectedAssets.length;

            if (selected > 0) {
                countElement.textContent = `${selected} of ${total} selected`;
            } else {
                countElement.textContent = `${total} asset${total !== 1 ? 's' : ''}`;
            }
        }
    }

    generateUploadId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    showError(message) {
        // Implementation for error notifications
        console.error(message);
    }

    // Grid size methods
    getCurrentGridSize() {
        const slider = this.modal.querySelector('.cms-grid-size');
        return slider ? parseInt(slider.value) : 150;
    }

    updateGridSize(size) {
        const gridContainer = this.modal.querySelector('.cms-grid-container');
        if (gridContainer) {
            gridContainer.style.setProperty('--grid-size', size + 'px');
        }
        // Re-render assets with new grid size
        this.renderAssets();
    }

    // Folder item creation
    createFolderItem(folder) {
        return `
            <div class="cms-folder-item" data-folder-id="${folder.id}">
                <i class="cms-folder-icon">📁</i>
                <span class="cms-folder-name">${folder.name}</span>
                <span class="cms-folder-count">${folder.asset_count || 0}</span>
            </div>
        `;
    }

    // Placeholder image for different asset types
    getPlaceholderImage(type) {
        const placeholders = {
            image: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgNzBDMTA4LjI4NCA3MCA5NSA4My4yODQgOTUgOTVTMTA4LjI4NCAxMjAgMTAwIDEyMFM3NS4wMDAyIDEwNi43MTYgNzUuMDAwMiA5NVM4Ny43MTYyIDcwIDEwMCA3MFoiIGZpbGw9IiNEMUQ1REIiLz4KPHBhdGggZD0iTTE3NSA2MFY4NUwxNDUgNjVMMTI1IDg1TDcwIDQwVjE2MEgxNzVWMTQwVjYwWiIgZmlsbD0iI0QxRDVEQiIvPgo8L3N2Zz4K',
            video: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik04NSA3NUwxMjUgMTAwTDg1IDEyNVY3NVoiIGZpbGw9IiNEMUQ1REIiLz4KPC9zdmc+Cg==',
            document: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxyZWN0IHg9IjYwIiB5PSI0MCIgd2lkdGg9IjgwIiBoZWlnaHQ9IjEyMCIgZmlsbD0iI0QxRDVEQiIvPgo8L3N2Zz4K'
        };
        return placeholders[type] || placeholders.document;
    }

    showSuccess(message) {
        // Implementation for success notifications
        console.log(message);
    }

    showLoading() {
        this.modal.querySelector('.asset-library-loading').style.display = 'flex';
    }

    hideLoading() {
        this.modal.querySelector('.asset-library-loading').style.display = 'none';
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AssetLibrary;
} else {
    window.AssetLibrary = AssetLibrary;
}