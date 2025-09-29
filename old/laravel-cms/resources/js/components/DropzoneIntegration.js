/**
 * Dropzone.js Integration for Laravel CMS Asset Library
 * Handles file uploads with drag & drop, progress tracking, and chunked uploads
 */

import Dropzone from 'dropzone';
import 'dropzone/dist/dropzone.css';

class DropzoneIntegration {
    constructor(assetLibrary) {
        this.assetLibrary = assetLibrary;
        this.dropzones = new Map();
        this.uploadQueue = [];
        this.activeUploads = 0;
        this.maxConcurrentUploads = 3;

        // Default Dropzone options
        this.defaultOptions = {
            url: '/cms/api/assets/upload',
            paramName: 'file',
            maxFilesize: 10, // MB
            maxFiles: null,
            parallelUploads: 3,
            uploadMultiple: false,
            chunking: true,
            forceChunking: true,
            chunkSize: 1024 * 1024, // 1MB chunks
            parallelChunkUploads: true,
            retryChunks: true,
            retryChunksLimit: 3,
            acceptedFiles: 'image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar',
            autoProcessQueue: true,
            addRemoveLinks: false,
            dictDefaultMessage: 'Drop files here to upload',
            dictFallbackMessage: 'Your browser does not support drag and drop file uploads.',
            dictInvalidFileType: 'You can\'t upload files of this type.',
            dictFileTooBig: 'File is too big ({{filesize}}MB). Max filesize: {{maxFilesize}}MB.',
            dictResponseError: 'Server responded with {{statusCode}} code.',
            dictCancelUpload: 'Cancel upload',
            dictUploadCanceled: 'Upload canceled.',
            dictRemoveFile: 'Remove file',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        this.init();
    }

    init() {
        // Prevent Dropzone from auto-discovering
        Dropzone.autoDiscover = false;

        // Initialize dropzones after modal is ready
        setTimeout(() => {
            this.initMainDropzone();
            this.initBulkUploadDropzone();
            this.initPageDropzone();
        }, 100);
    }

    initMainDropzone() {
        const dropzoneElement = this.assetLibrary.modal.querySelector('.cms-dropzone');
        if (!dropzoneElement || dropzoneElement.dropzone) return;

        const options = {
            ...this.defaultOptions,
            previewsContainer: false,
            clickable: '.cms-browse-btn',

            // Custom preview template
            previewTemplate: this.getPreviewTemplate(),

            // Event handlers
            init: function() {
                this.on('addedfile', (file) => {
                    this.handleFileAdded(file);
                });

                this.on('uploadprogress', (file, progress) => {
                    this.updateProgress(file, progress);
                });

                this.on('success', (file, response) => {
                    this.handleUploadSuccess(file, response);
                });

                this.on('error', (file, errorMessage) => {
                    this.handleUploadError(file, errorMessage);
                });

                this.on('complete', (file) => {
                    this.handleUploadComplete(file);
                });

                this.on('sending', (file, xhr, formData) => {
                    // Add additional data
                    const folderId = this.assetLibrary.state.currentFolder;
                    if (folderId) {
                        formData.append('folder_id', folderId);
                    }

                    // Add optimization options
                    const optimizeCheckbox = this.assetLibrary.modal.querySelector('.cms-auto-optimize');
                    if (optimizeCheckbox?.checked) {
                        formData.append('optimize', 'true');
                    }

                    const webpCheckbox = this.assetLibrary.modal.querySelector('.cms-generate-webp');
                    if (webpCheckbox?.checked) {
                        formData.append('generate_webp', 'true');
                    }
                }.bind(this));

                // Handle drag events
                this.on('dragenter', () => {
                    dropzoneElement.classList.add('dragging');
                });

                this.on('dragleave', () => {
                    dropzoneElement.classList.remove('dragging');
                });

                this.on('drop', () => {
                    dropzoneElement.classList.remove('dragging');
                });
            }.bind(this)
        };

        const dropzone = new Dropzone(dropzoneElement, options);
        this.dropzones.set('main', dropzone);

        // Bind file input
        const fileInput = this.assetLibrary.modal.querySelector('.cms-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    Array.from(e.target.files).forEach(file => {
                        dropzone.addFile(file);
                    });
                    e.target.value = '';
                }
            });
        }
    }

    initBulkUploadDropzone() {
        const largeDropzoneElement = this.assetLibrary.modal.querySelector('.cms-large-dropzone');
        if (!largeDropzoneElement || largeDropzoneElement.dropzone) return;

        const options = {
            ...this.defaultOptions,
            previewsContainer: '.cms-upload-preview',
            clickable: '.cms-select-files-btn',

            init: function() {
                this.on('addedfile', (file) => {
                    this.createUploadPreview(file);
                });

                this.on('uploadprogress', (file, progress) => {
                    this.updateUploadPreview(file, progress);
                });

                this.on('success', (file, response) => {
                    this.handleBulkUploadSuccess(file, response);
                });

                this.on('error', (file, errorMessage) => {
                    this.handleBulkUploadError(file, errorMessage);
                });

                this.on('queuecomplete', () => {
                    this.handleQueueComplete();
                });
            }.bind(this)
        };

        const dropzone = new Dropzone(largeDropzoneElement, options);
        this.dropzones.set('bulk', dropzone);

        // Bind bulk file input
        const bulkFileInput = this.assetLibrary.modal.querySelector('.cms-bulk-file-input');
        if (bulkFileInput) {
            bulkFileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    Array.from(e.target.files).forEach(file => {
                        dropzone.addFile(file);
                    });
                    e.target.value = '';
                }
            });
        }
    }

    initPageDropzone() {
        // Enable drag and drop on the entire page when modal is open
        document.addEventListener('dragover', (e) => {
            if (this.assetLibrary.modal.style.display === 'flex') {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                this.showDropIndicator();
            }
        });

        document.addEventListener('dragleave', (e) => {
            if (!e.relatedTarget || e.relatedTarget.nodeName === 'HTML') {
                this.hideDropIndicator();
            }
        });

        document.addEventListener('drop', (e) => {
            if (this.assetLibrary.modal.style.display === 'flex') {
                e.preventDefault();
                this.hideDropIndicator();

                const files = Array.from(e.dataTransfer.files);
                if (files.length > 0) {
                    // Switch to upload tab
                    this.assetLibrary.switchTab('upload');

                    // Add files to bulk dropzone
                    const bulkDropzone = this.dropzones.get('bulk');
                    if (bulkDropzone) {
                        files.forEach(file => bulkDropzone.addFile(file));
                    }
                }
            }
        });
    }

    handleFileAdded(file) {
        // Create custom preview
        const preview = this.createPreviewElement(file);

        // Add to upload queue
        this.uploadQueue.push({
            file: file,
            preview: preview,
            progress: 0,
            status: 'pending'
        });

        // Update UI
        this.updateUploadStatus();
    }

    updateProgress(file, progress) {
        const uploadItem = this.uploadQueue.find(item => item.file === file);
        if (uploadItem) {
            uploadItem.progress = progress;
            this.updateProgressBar(uploadItem.preview, progress);
        }
    }

    handleUploadSuccess(file, response) {
        if (response.success && response.data) {
            const asset = response.data;

            // Add to assets list
            this.assetLibrary.state.assets.unshift(asset);
            this.assetLibrary.renderAssets();

            // Show success notification
            this.showNotification(`${file.name} uploaded successfully`, 'success');

            // Update upload item
            const uploadItem = this.uploadQueue.find(item => item.file === file);
            if (uploadItem) {
                uploadItem.status = 'success';
                uploadItem.assetId = asset.id;
                this.updatePreviewSuccess(uploadItem.preview, asset);
            }

            // Auto-select if in single mode
            if (this.assetLibrary.options.mode === 'single') {
                this.assetLibrary.selectSingleAsset(asset);
            }
        }
    }

    handleUploadError(file, errorMessage) {
        // Show error notification
        this.showNotification(`Failed to upload ${file.name}: ${errorMessage}`, 'error');

        // Update upload item
        const uploadItem = this.uploadQueue.find(item => item.file === file);
        if (uploadItem) {
            uploadItem.status = 'error';
            uploadItem.error = errorMessage;
            this.updatePreviewError(uploadItem.preview, errorMessage);
        }
    }

    handleUploadComplete(file) {
        this.activeUploads--;
        this.updateUploadStatus();

        // Process next in queue if any
        this.processQueue();
    }

    handleBulkUploadSuccess(file, response) {
        this.handleUploadSuccess(file, response);

        // Update bulk upload counter
        this.updateBulkUploadCounter();
    }

    handleBulkUploadError(file, errorMessage) {
        this.handleUploadError(file, errorMessage);

        // Update bulk upload counter
        this.updateBulkUploadCounter();
    }

    handleQueueComplete() {
        // Show completion message
        const successCount = this.uploadQueue.filter(item => item.status === 'success').length;
        const errorCount = this.uploadQueue.filter(item => item.status === 'error').length;

        let message = `Upload complete: ${successCount} file(s) uploaded successfully`;
        if (errorCount > 0) {
            message += `, ${errorCount} file(s) failed`;
        }

        this.showNotification(message, errorCount > 0 ? 'warning' : 'success');

        // Switch to browse tab after a delay
        setTimeout(() => {
            this.assetLibrary.switchTab('browse');
            this.assetLibrary.loadAssets(true);
        }, 2000);
    }

    createPreviewElement(file) {
        const preview = document.createElement('div');
        preview.className = 'cms-upload-item';
        preview.innerHTML = `
            <div class="cms-upload-thumbnail">
                ${file.type.startsWith('image/') ?
                    `<img src="${URL.createObjectURL(file)}" alt="${file.name}">` :
                    `<i class="icon-file"></i>`
                }
            </div>
            <div class="cms-upload-info">
                <div class="filename">${file.name}</div>
                <div class="filesize">${this.formatFileSize(file.size)}</div>
            </div>
            <div class="cms-upload-progress">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <span class="progress-text">0%</span>
            </div>
            <button class="cms-upload-cancel" title="Cancel upload">
                <i class="icon-close"></i>
            </button>
        `;

        // Add to preview container
        const previewContainer = this.getPreviewContainer();
        if (previewContainer) {
            previewContainer.appendChild(preview);
        }

        // Bind cancel button
        const cancelBtn = preview.querySelector('.cms-upload-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                this.cancelUpload(file);
            });
        }

        return preview;
    }

    createUploadPreview(file) {
        const preview = this.createPreviewElement(file);

        // Store reference
        file.previewElement = preview;

        return preview;
    }

    updateUploadPreview(file, progress) {
        if (file.previewElement) {
            this.updateProgressBar(file.previewElement, progress);
        }
    }

    updateProgressBar(preview, progress) {
        const progressFill = preview.querySelector('.progress-fill');
        const progressText = preview.querySelector('.progress-text');

        if (progressFill) {
            progressFill.style.width = `${progress}%`;
        }

        if (progressText) {
            progressText.textContent = `${Math.round(progress)}%`;
        }
    }

    updatePreviewSuccess(preview, asset) {
        preview.classList.add('upload-success');

        const progressContainer = preview.querySelector('.cms-upload-progress');
        if (progressContainer) {
            progressContainer.innerHTML = '<i class="icon-check" style="color: var(--cms-success);"></i>';
        }

        // Update thumbnail if it's an image
        if (asset.type === 'image' && asset.thumbnails?.small) {
            const img = preview.querySelector('img');
            if (img) {
                img.src = asset.thumbnails.small;
            }
        }
    }

    updatePreviewError(preview, errorMessage) {
        preview.classList.add('upload-error');

        const progressContainer = preview.querySelector('.cms-upload-progress');
        if (progressContainer) {
            progressContainer.innerHTML = `
                <span class="error-message" title="${errorMessage}">
                    <i class="icon-error" style="color: var(--cms-error);"></i>
                    Error
                </span>
            `;
        }
    }

    cancelUpload(file) {
        // Find the appropriate dropzone
        this.dropzones.forEach(dropzone => {
            const queuedFile = dropzone.files.find(f => f === file);
            if (queuedFile) {
                dropzone.removeFile(file);
            }
        });

        // Remove from upload queue
        const index = this.uploadQueue.findIndex(item => item.file === file);
        if (index > -1) {
            const uploadItem = this.uploadQueue[index];
            if (uploadItem.preview && uploadItem.preview.parentNode) {
                uploadItem.preview.remove();
            }
            this.uploadQueue.splice(index, 1);
        }

        this.updateUploadStatus();
    }

    processQueue() {
        // Process next file in queue if under concurrent limit
        if (this.activeUploads < this.maxConcurrentUploads) {
            const pending = this.uploadQueue.find(item => item.status === 'pending');
            if (pending) {
                this.activeUploads++;
                // Dropzone handles the actual upload
            }
        }
    }

    getPreviewContainer() {
        // Check if we're in upload tab
        const uploadTab = this.assetLibrary.modal.querySelector('#tab-upload');
        if (uploadTab && uploadTab.classList.contains('active')) {
            let container = uploadTab.querySelector('.cms-upload-preview');
            if (!container) {
                container = document.createElement('div');
                container.className = 'cms-upload-preview';
                uploadTab.appendChild(container);
            }
            return container;
        }

        return null;
    }

    showDropIndicator() {
        if (!this.dropIndicator) {
            this.dropIndicator = document.createElement('div');
            this.dropIndicator.className = 'cms-page-drop-indicator';
            this.dropIndicator.innerHTML = `
                <div class="drop-message">
                    <i class="icon-cloud-upload"></i>
                    <h3>Drop files to upload</h3>
                </div>
            `;
            document.body.appendChild(this.dropIndicator);
        }

        this.dropIndicator.classList.add('active');
    }

    hideDropIndicator() {
        if (this.dropIndicator) {
            this.dropIndicator.classList.remove('active');
        }
    }

    updateUploadStatus() {
        const totalFiles = this.uploadQueue.length;
        const completedFiles = this.uploadQueue.filter(
            item => item.status === 'success' || item.status === 'error'
        ).length;

        // Update status indicator
        const statusElement = this.assetLibrary.modal.querySelector('.cms-upload-status');
        if (statusElement) {
            if (totalFiles > 0) {
                statusElement.textContent = `Uploading ${completedFiles} of ${totalFiles} files...`;
                statusElement.style.display = 'block';
            } else {
                statusElement.style.display = 'none';
            }
        }
    }

    updateBulkUploadCounter() {
        const successCount = this.uploadQueue.filter(item => item.status === 'success').length;
        const totalCount = this.uploadQueue.length;

        const counter = this.assetLibrary.modal.querySelector('.cms-bulk-upload-counter');
        if (counter) {
            counter.textContent = `${successCount} / ${totalCount} uploaded`;
        }
    }

    showNotification(message, type = 'info') {
        // Use asset library's notification system if available
        if (this.assetLibrary.showNotification) {
            this.assetLibrary.showNotification(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
    }

    getPreviewTemplate() {
        return `
            <div class="dz-preview dz-file-preview">
                <div class="dz-image">
                    <img data-dz-thumbnail />
                </div>
                <div class="dz-details">
                    <div class="dz-size"><span data-dz-size></span></div>
                    <div class="dz-filename"><span data-dz-name></span></div>
                </div>
                <div class="dz-progress">
                    <span class="dz-upload" data-dz-uploadprogress></span>
                </div>
                <div class="dz-error-message"><span data-dz-errormessage></span></div>
                <div class="dz-success-mark">✓</div>
                <div class="dz-error-mark">✗</div>
            </div>
        `;
    }

    destroy() {
        // Clean up all dropzone instances
        this.dropzones.forEach(dropzone => {
            dropzone.destroy();
        });
        this.dropzones.clear();

        // Remove drop indicator
        if (this.dropIndicator && this.dropIndicator.parentNode) {
            this.dropIndicator.remove();
        }

        // Clear upload queue
        this.uploadQueue = [];
    }
}

// Export for use in AssetLibrary
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DropzoneIntegration;
} else {
    window.DropzoneIntegration = DropzoneIntegration;
}