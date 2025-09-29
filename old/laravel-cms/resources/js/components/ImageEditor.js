/**
 * Laravel CMS Image Editor Component
 * Advanced image editing with cropping, filters, adjustments, and effects
 * Built with Cropper.js and Canvas API for professional image editing
 */
class ImageEditor {
    constructor(asset, options = {}) {
        this.asset = asset;
        this.options = {
            onSave: null,
            onCancel: null,
            allowSaveAsCopy: true,
            tools: ['crop', 'rotate', 'flip', 'adjust', 'filter', 'resize'],
            ...options
        };

        this.state = {
            cropper: null,
            currentTool: null,
            history: [],
            historyIndex: -1,
            originalImageData: null,
            currentImageData: null,
            adjustments: {
                brightness: 0,
                contrast: 0,
                saturation: 0,
                hue: 0,
                blur: 0,
                sharpen: 0
            },
            filters: [],
            hasChanges: false
        };

        this.cropperOptions = {
            aspectRatio: NaN,
            viewMode: 1,
            guides: true,
            center: true,
            highlight: true,
            background: true,
            autoCrop: false,
            movable: true,
            rotatable: true,
            scalable: true,
            zoomable: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: true,
            ready: () => this.onCropperReady(),
            crop: (e) => this.onCrop(e),
            zoom: (e) => this.onZoom(e)
        };

        this.init();
    }

    init() {
        this.createEditorModal();
        this.loadImage();
        this.initTools();
        this.bindEvents();
        this.initKeyboardShortcuts();
        this.saveState(); // Save initial state
    }

    createEditorModal() {
        const modalHTML = `
            <div class="cms-image-editor-modal" id="cms-image-editor" role="dialog" aria-labelledby="editor-title" aria-modal="true">
                <div class="cms-editor-overlay"></div>
                <div class="cms-editor-container">
                    <div class="cms-editor-header">
                        <h3 id="editor-title">Edit Image: ${this.asset.title || this.asset.filename}</h3>
                        <div class="cms-editor-tools">
                            ${this.options.tools.map(tool => `
                                <button class="cms-tool" data-tool="${tool}" title="${this.getToolTitle(tool)}" aria-label="${this.getToolTitle(tool)}">
                                    <i class="icon-${tool}"></i>
                                    <span>${this.getToolTitle(tool)}</span>
                                </button>
                            `).join('')}
                        </div>
                        <div class="cms-editor-meta">
                            <span class="cms-image-dimensions">${this.asset.metadata?.width || 0} × ${this.asset.metadata?.height || 0}</span>
                            <span class="cms-image-size">${this.formatFileSize(this.asset.size)}</span>
                        </div>
                        <button class="cms-editor-close" aria-label="Close editor">
                            <i class="icon-close"></i>
                        </button>
                    </div>

                    <div class="cms-editor-workspace">
                        <div class="cms-editor-sidebar">
                            <!-- Crop Tool Panel -->
                            <div class="cms-tool-panel" data-tool="crop" style="display: none;">
                                <h4><i class="icon-crop"></i> Crop Image</h4>
                                <div class="cms-aspect-ratios">
                                    <button class="cms-aspect-btn active" data-aspect="">
                                        <i class="icon-free"></i> Free
                                    </button>
                                    <button class="cms-aspect-btn" data-aspect="1">
                                        <i class="icon-square"></i> 1:1
                                    </button>
                                    <button class="cms-aspect-btn" data-aspect="1.333">
                                        <i class="icon-landscape"></i> 4:3
                                    </button>
                                    <button class="cms-aspect-btn" data-aspect="1.777">
                                        <i class="icon-widescreen"></i> 16:9
                                    </button>
                                    <button class="cms-aspect-btn" data-aspect="0.75">
                                        <i class="icon-portrait"></i> 3:4
                                    </button>
                                </div>
                                <div class="cms-custom-aspect">
                                    <label>Custom Ratio:</label>
                                    <input type="number" class="cms-aspect-width" placeholder="W" min="1" step="1">
                                    <span>:</span>
                                    <input type="number" class="cms-aspect-height" placeholder="H" min="1" step="1">
                                    <button class="cms-apply-custom-aspect">Apply</button>
                                </div>
                                <div class="cms-crop-presets">
                                    <h5>Common Sizes</h5>
                                    <button class="cms-preset-btn" data-width="1920" data-height="1080">1920×1080</button>
                                    <button class="cms-preset-btn" data-width="1200" data-height="630">Social (FB)</button>
                                    <button class="cms-preset-btn" data-width="1024" data-height="512">Blog Header</button>
                                    <button class="cms-preset-btn" data-width="400" data-height="400">Profile</button>
                                </div>
                                <div class="cms-crop-actions">
                                    <button class="cms-apply-crop primary">Apply Crop</button>
                                    <button class="cms-reset-crop">Reset</button>
                                </div>
                            </div>

                            <!-- Rotate Tool Panel -->
                            <div class="cms-tool-panel" data-tool="rotate" style="display: none;">
                                <h4><i class="icon-rotate"></i> Rotate & Flip</h4>
                                <div class="cms-rotate-controls">
                                    <button class="cms-rotate-btn" data-angle="-90">
                                        <i class="icon-rotate-left"></i> 90° Left
                                    </button>
                                    <button class="cms-rotate-btn" data-angle="90">
                                        <i class="icon-rotate-right"></i> 90° Right
                                    </button>
                                    <button class="cms-rotate-btn" data-angle="180">
                                        <i class="icon-rotate-180"></i> 180°
                                    </button>
                                </div>
                                <div class="cms-custom-rotate">
                                    <label>Custom Angle:</label>
                                    <input type="range" class="cms-rotate-slider" min="-180" max="180" value="0" step="1">
                                    <input type="number" class="cms-rotate-input" min="-180" max="180" value="0" step="1">
                                    <span>°</span>
                                </div>
                                <div class="cms-flip-controls">
                                    <button class="cms-flip-btn" data-direction="horizontal">
                                        <i class="icon-flip-h"></i> Flip Horizontal
                                    </button>
                                    <button class="cms-flip-btn" data-direction="vertical">
                                        <i class="icon-flip-v"></i> Flip Vertical
                                    </button>
                                </div>
                            </div>

                            <!-- Adjust Tool Panel -->
                            <div class="cms-tool-panel" data-tool="adjust" style="display: none;">
                                <h4><i class="icon-adjust"></i> Adjustments</h4>
                                <div class="cms-adjustments">
                                    <div class="cms-slider-group">
                                        <label>Brightness</label>
                                        <input type="range" class="cms-brightness" min="-100" max="100" value="0" step="1">
                                        <span class="cms-value">0</span>
                                        <button class="cms-reset-slider" data-target="brightness">↻</button>
                                    </div>
                                    <div class="cms-slider-group">
                                        <label>Contrast</label>
                                        <input type="range" class="cms-contrast" min="-100" max="100" value="0" step="1">
                                        <span class="cms-value">0</span>
                                        <button class="cms-reset-slider" data-target="contrast">↻</button>
                                    </div>
                                    <div class="cms-slider-group">
                                        <label>Saturation</label>
                                        <input type="range" class="cms-saturation" min="-100" max="100" value="0" step="1">
                                        <span class="cms-value">0</span>
                                        <button class="cms-reset-slider" data-target="saturation">↻</button>
                                    </div>
                                    <div class="cms-slider-group">
                                        <label>Hue</label>
                                        <input type="range" class="cms-hue" min="-180" max="180" value="0" step="1">
                                        <span class="cms-value">0°</span>
                                        <button class="cms-reset-slider" data-target="hue">↻</button>
                                    </div>
                                    <div class="cms-slider-group">
                                        <label>Blur</label>
                                        <input type="range" class="cms-blur" min="0" max="20" value="0" step="0.1">
                                        <span class="cms-value">0</span>
                                        <button class="cms-reset-slider" data-target="blur">↻</button>
                                    </div>
                                    <div class="cms-slider-group">
                                        <label>Sharpen</label>
                                        <input type="range" class="cms-sharpen" min="0" max="100" value="0" step="1">
                                        <span class="cms-value">0</span>
                                        <button class="cms-reset-slider" data-target="sharpen">↻</button>
                                    </div>
                                </div>
                                <div class="cms-adjustment-presets">
                                    <h5>Presets</h5>
                                    <button class="cms-preset-adjust" data-preset="vibrant">Vibrant</button>
                                    <button class="cms-preset-adjust" data-preset="soft">Soft</button>
                                    <button class="cms-preset-adjust" data-preset="dramatic">Dramatic</button>
                                    <button class="cms-preset-adjust" data-preset="reset">Reset All</button>
                                </div>
                            </div>

                            <!-- Filter Tool Panel -->
                            <div class="cms-tool-panel" data-tool="filter" style="display: none;">
                                <h4><i class="icon-filter"></i> Filters</h4>
                                <div class="cms-filter-grid">
                                    <button class="cms-filter-btn" data-filter="none">
                                        <div class="filter-preview original"></div>
                                        <span>Original</span>
                                    </button>
                                    <button class="cms-filter-btn" data-filter="grayscale">
                                        <div class="filter-preview grayscale"></div>
                                        <span>Grayscale</span>
                                    </button>
                                    <button class="cms-filter-btn" data-filter="sepia">
                                        <div class="filter-preview sepia"></div>
                                        <span>Sepia</span>
                                    </button>
                                    <button class="cms-filter-btn" data-filter="vintage">
                                        <div class="filter-preview vintage"></div>
                                        <span>Vintage</span>
                                    </button>
                                    <button class="cms-filter-btn" data-filter="cool">
                                        <div class="filter-preview cool"></div>
                                        <span>Cool</span>
                                    </button>
                                    <button class="cms-filter-btn" data-filter="warm">
                                        <div class="filter-preview warm"></div>
                                        <span>Warm</span>
                                    </button>
                                    <button class="cms-filter-btn" data-filter="high-contrast">
                                        <div class="filter-preview high-contrast"></div>
                                        <span>High Contrast</span>
                                    </button>
                                    <button class="cms-filter-btn" data-filter="soft-focus">
                                        <div class="filter-preview soft-focus"></div>
                                        <span>Soft Focus</span>
                                    </button>
                                </div>
                                <div class="cms-filter-intensity">
                                    <label>Filter Intensity:</label>
                                    <input type="range" class="cms-filter-strength" min="0" max="100" value="100" step="1">
                                    <span class="cms-value">100%</span>
                                </div>
                            </div>

                            <!-- Resize Tool Panel -->
                            <div class="cms-tool-panel" data-tool="resize" style="display: none;">
                                <h4><i class="icon-resize"></i> Resize Image</h4>
                                <div class="cms-current-size">
                                    <p>Current: <span class="current-width">${this.asset.metadata?.width || 0}</span> × <span class="current-height">${this.asset.metadata?.height || 0}</span></p>
                                </div>
                                <div class="cms-resize-inputs">
                                    <div class="cms-input-group">
                                        <label>Width</label>
                                        <input type="number" class="cms-resize-width" value="${this.asset.metadata?.width || 0}" min="1" step="1">
                                        <span>px</span>
                                    </div>
                                    <div class="cms-input-group">
                                        <label>Height</label>
                                        <input type="number" class="cms-resize-height" value="${this.asset.metadata?.height || 0}" min="1" step="1">
                                        <span>px</span>
                                    </div>
                                    <label class="cms-checkbox">
                                        <input type="checkbox" class="cms-maintain-ratio" checked>
                                        <span>Maintain aspect ratio</span>
                                    </label>
                                </div>
                                <div class="cms-resize-presets">
                                    <h5>Common Sizes</h5>
                                    <button class="cms-size-preset" data-width="1920" data-height="1080">Full HD</button>
                                    <button class="cms-size-preset" data-width="1280" data-height="720">HD</button>
                                    <button class="cms-size-preset" data-width="800" data-height="600">Web</button>
                                    <button class="cms-size-preset" data-width="400" data-height="400">Thumbnail</button>
                                </div>
                                <div class="cms-resize-method">
                                    <label>Resize Method:</label>
                                    <select class="cms-resize-type">
                                        <option value="contain">Fit (contain)</option>
                                        <option value="cover">Fill (cover)</option>
                                        <option value="stretch">Stretch</option>
                                    </select>
                                </div>
                                <button class="cms-apply-resize primary">Apply Resize</button>
                            </div>
                        </div>

                        <div class="cms-editor-canvas-container">
                            <div class="cms-editor-toolbar">
                                <div class="cms-zoom-controls">
                                    <button class="cms-zoom-out" title="Zoom Out">
                                        <i class="icon-zoom-out"></i>
                                    </button>
                                    <span class="cms-zoom-level">100%</span>
                                    <button class="cms-zoom-in" title="Zoom In">
                                        <i class="icon-zoom-in"></i>
                                    </button>
                                    <button class="cms-zoom-fit" title="Fit to Screen">
                                        <i class="icon-zoom-fit"></i>
                                    </button>
                                    <button class="cms-zoom-100" title="Actual Size">
                                        <i class="icon-zoom-100"></i>
                                    </button>
                                </div>
                                <div class="cms-canvas-info">
                                    <span class="cms-selection-info"></span>
                                </div>
                            </div>
                            <div class="cms-editor-canvas">
                                <img id="cms-edit-image" src="${this.asset.url}" alt="Image to edit">
                                <div class="cms-canvas-overlay"></div>
                            </div>
                        </div>
                    </div>

                    <div class="cms-editor-footer">
                        <div class="cms-editor-history">
                            <button class="cms-undo" disabled title="Undo (Ctrl+Z)">
                                <i class="icon-undo"></i>
                            </button>
                            <button class="cms-redo" disabled title="Redo (Ctrl+Y)">
                                <i class="icon-redo"></i>
                            </button>
                            <button class="cms-reset" title="Reset All Changes">
                                <i class="icon-reset"></i> Reset All
                            </button>
                        </div>
                        <div class="cms-editor-info">
                            <span class="cms-changes-indicator" style="display: none;">
                                <i class="icon-warning"></i> Unsaved changes
                            </span>
                        </div>
                        <div class="cms-editor-actions">
                            <button class="cms-cancel">Cancel</button>
                            ${this.options.allowSaveAsCopy ? '<button class="cms-save-copy">Save as Copy</button>' : ''}
                            <button class="cms-save primary">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('cms-image-editor');
        this.canvas = this.modal.querySelector('#cms-edit-image');

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    async loadImage() {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';

            img.onload = () => {
                this.originalImageData = this.getImageData(img);
                this.currentImageData = { ...this.originalImageData };
                resolve();
            };

            img.onerror = reject;
            img.src = this.asset.url;
        });
    }

    initTools() {
        // Initialize Cropper.js
        this.initCropper();

        // Set default tool
        if (this.options.tools.length > 0) {
            this.switchTool(this.options.tools[0]);
        }
    }

    initCropper() {
        // Destroy existing cropper if any
        if (this.state.cropper) {
            this.state.cropper.destroy();
        }

        // Initialize new cropper
        this.state.cropper = new Cropper(this.canvas, this.cropperOptions);
    }

    switchTool(toolName) {
        // Hide all tool panels
        this.modal.querySelectorAll('.cms-tool-panel').forEach(panel => {
            panel.style.display = 'none';
        });

        // Remove active class from all tools
        this.modal.querySelectorAll('.cms-tool').forEach(tool => {
            tool.classList.remove('active');
        });

        // Show selected tool panel
        const toolPanel = this.modal.querySelector(`[data-tool="${toolName}"]`);
        if (toolPanel) {
            toolPanel.style.display = 'block';
        }

        // Add active class to selected tool
        const toolButton = this.modal.querySelector(`.cms-tool[data-tool="${toolName}"]`);
        if (toolButton) {
            toolButton.classList.add('active');
        }

        this.state.currentTool = toolName;

        // Configure cropper based on tool
        if (this.state.cropper) {
            switch (toolName) {
                case 'crop':
                    this.state.cropper.setDragMode('crop');
                    break;
                case 'rotate':
                case 'flip':
                    this.state.cropper.setDragMode('move');
                    break;
                default:
                    this.state.cropper.setDragMode('move');
            }
        }
    }

    bindEvents() {
        // Tool switching
        this.modal.querySelectorAll('.cms-tool').forEach(tool => {
            tool.addEventListener('click', (e) => {
                this.switchTool(e.currentTarget.dataset.tool);
            });
        });

        // Crop events
        this.bindCropEvents();

        // Rotate events
        this.bindRotateEvents();

        // Adjustment events
        this.bindAdjustmentEvents();

        // Filter events
        this.bindFilterEvents();

        // Resize events
        this.bindResizeEvents();

        // History events
        this.modal.querySelector('.cms-undo').addEventListener('click', () => this.undo());
        this.modal.querySelector('.cms-redo').addEventListener('click', () => this.redo());
        this.modal.querySelector('.cms-reset').addEventListener('click', () => this.resetAll());

        // Save events
        this.modal.querySelector('.cms-save').addEventListener('click', () => this.save());
        this.modal.querySelector('.cms-cancel').addEventListener('click', () => this.cancel());

        const saveCopyBtn = this.modal.querySelector('.cms-save-copy');
        if (saveCopyBtn) {
            saveCopyBtn.addEventListener('click', () => this.save(true));
        }

        // Close events
        this.modal.querySelector('.cms-editor-close').addEventListener('click', () => this.cancel());
        this.modal.querySelector('.cms-editor-overlay').addEventListener('click', () => this.cancel());

        // Zoom controls
        this.bindZoomEvents();
    }

    bindCropEvents() {
        // Aspect ratio buttons
        this.modal.querySelectorAll('.cms-aspect-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Remove active class from all buttons
                this.modal.querySelectorAll('.cms-aspect-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');

                const aspectRatio = e.target.dataset.aspect;
                this.state.cropper.setAspectRatio(aspectRatio ? parseFloat(aspectRatio) : NaN);
            });
        });

        // Custom aspect ratio
        this.modal.querySelector('.cms-apply-custom-aspect').addEventListener('click', () => {
            const width = parseFloat(this.modal.querySelector('.cms-aspect-width').value);
            const height = parseFloat(this.modal.querySelector('.cms-aspect-height').value);

            if (width > 0 && height > 0) {
                this.state.cropper.setAspectRatio(width / height);
            }
        });

        // Crop presets
        this.modal.querySelectorAll('.cms-preset-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const width = parseInt(e.target.dataset.width);
                const height = parseInt(e.target.dataset.height);
                this.applyCropPreset(width, height);
            });
        });

        // Apply and reset crop
        this.modal.querySelector('.cms-apply-crop').addEventListener('click', () => this.applyCrop());
        this.modal.querySelector('.cms-reset-crop').addEventListener('click', () => this.resetCrop());
    }

    bindRotateEvents() {
        // Rotation buttons
        this.modal.querySelectorAll('.cms-rotate-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const angle = parseFloat(e.target.dataset.angle);
                this.rotate(angle);
            });
        });

        // Custom rotation slider
        const rotateSlider = this.modal.querySelector('.cms-rotate-slider');
        const rotateInput = this.modal.querySelector('.cms-rotate-input');

        rotateSlider.addEventListener('input', (e) => {
            const angle = parseFloat(e.target.value);
            rotateInput.value = angle;
            this.setRotation(angle);
        });

        rotateInput.addEventListener('input', (e) => {
            const angle = parseFloat(e.target.value);
            if (!isNaN(angle)) {
                rotateSlider.value = angle;
                this.setRotation(angle);
            }
        });

        // Flip buttons
        this.modal.querySelectorAll('.cms-flip-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const direction = e.target.dataset.direction;
                this.flip(direction);
            });
        });
    }

    bindAdjustmentEvents() {
        // Adjustment sliders
        ['brightness', 'contrast', 'saturation', 'hue', 'blur', 'sharpen'].forEach(adjustment => {
            const slider = this.modal.querySelector(`.cms-${adjustment}`);
            const valueDisplay = slider.nextElementSibling;
            const resetBtn = slider.parentElement.querySelector('.cms-reset-slider');

            slider.addEventListener('input', (e) => {
                const value = parseFloat(e.target.value);
                this.state.adjustments[adjustment] = value;

                // Update value display
                const suffix = adjustment === 'hue' ? '°' : '';
                valueDisplay.textContent = value + suffix;

                this.applyAdjustments();
                this.markAsChanged();
            });

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    slider.value = 0;
                    this.state.adjustments[adjustment] = 0;
                    valueDisplay.textContent = '0' + (adjustment === 'hue' ? '°' : '');
                    this.applyAdjustments();
                });
            }
        });

        // Adjustment presets
        this.modal.querySelectorAll('.cms-preset-adjust').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.applyAdjustmentPreset(e.target.dataset.preset);
            });
        });
    }

    bindFilterEvents() {
        // Filter buttons
        this.modal.querySelectorAll('.cms-filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Remove active class from all filter buttons
                this.modal.querySelectorAll('.cms-filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');

                const filter = e.target.dataset.filter;
                this.applyFilter(filter);
            });
        });

        // Filter intensity
        const filterStrength = this.modal.querySelector('.cms-filter-strength');
        const filterValue = filterStrength.nextElementSibling;

        filterStrength.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            filterValue.textContent = value + '%';
            this.updateFilterIntensity(value);
        });
    }

    bindResizeEvents() {
        const widthInput = this.modal.querySelector('.cms-resize-width');
        const heightInput = this.modal.querySelector('.cms-resize-height');
        const maintainRatio = this.modal.querySelector('.cms-maintain-ratio');

        // Maintain aspect ratio
        let aspectRatio = this.asset.metadata?.width / this.asset.metadata?.height || 1;

        widthInput.addEventListener('input', (e) => {
            if (maintainRatio.checked) {
                const width = parseInt(e.target.value);
                const height = Math.round(width / aspectRatio);
                heightInput.value = height;
            }
        });

        heightInput.addEventListener('input', (e) => {
            if (maintainRatio.checked) {
                const height = parseInt(e.target.value);
                const width = Math.round(height * aspectRatio);
                widthInput.value = width;
            }
        });

        // Size presets
        this.modal.querySelectorAll('.cms-size-preset').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const width = parseInt(e.target.dataset.width);
                const height = parseInt(e.target.dataset.height);

                widthInput.value = width;
                heightInput.value = height;

                // Update aspect ratio if maintain ratio is checked
                if (maintainRatio.checked) {
                    aspectRatio = width / height;
                }
            });
        });

        // Apply resize
        this.modal.querySelector('.cms-apply-resize').addEventListener('click', () => {
            this.applyResize();
        });
    }

    bindZoomEvents() {
        this.modal.querySelector('.cms-zoom-in').addEventListener('click', () => {
            this.state.cropper.zoom(0.1);
        });

        this.modal.querySelector('.cms-zoom-out').addEventListener('click', () => {
            this.state.cropper.zoom(-0.1);
        });

        this.modal.querySelector('.cms-zoom-fit').addEventListener('click', () => {
            this.state.cropper.reset();
        });

        this.modal.querySelector('.cms-zoom-100').addEventListener('click', () => {
            this.state.cropper.zoomTo(1);
        });
    }

    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (!this.modal.style.display || this.modal.style.display === 'none') return;

            // Prevent default browser shortcuts when editor is open
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'z':
                        e.preventDefault();
                        if (e.shiftKey) {
                            this.redo();
                        } else {
                            this.undo();
                        }
                        break;
                    case 'y':
                        e.preventDefault();
                        this.redo();
                        break;
                    case 's':
                        e.preventDefault();
                        this.save();
                        break;
                }
            }

            // ESC to close
            if (e.key === 'Escape') {
                this.cancel();
            }
        });
    }

    // Tool Methods
    applyCrop() {
        const cropData = this.state.cropper.getCropBoxData();
        if (cropData.width > 0 && cropData.height > 0) {
            this.saveState();
            this.state.cropper.crop();
            this.markAsChanged();
        }
    }

    resetCrop() {
        this.state.cropper.clear();
    }

    rotate(angle) {
        this.saveState();
        this.state.cropper.rotate(angle);
        this.markAsChanged();
    }

    setRotation(angle) {
        this.saveState();
        this.state.cropper.rotateTo(angle);
        this.markAsChanged();
    }

    flip(direction) {
        this.saveState();

        if (direction === 'horizontal') {
            this.state.cropper.scaleX(-this.state.cropper.getImageData().scaleX || -1);
        } else if (direction === 'vertical') {
            this.state.cropper.scaleY(-this.state.cropper.getImageData().scaleY || -1);
        }

        this.markAsChanged();
    }

    applyAdjustments() {
        const canvas = this.state.cropper.getCroppedCanvas();
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;

        // Apply adjustments to image data
        for (let i = 0; i < data.length; i += 4) {
            // Brightness
            if (this.state.adjustments.brightness !== 0) {
                data[i] += this.state.adjustments.brightness * 2.55;     // Red
                data[i + 1] += this.state.adjustments.brightness * 2.55; // Green
                data[i + 2] += this.state.adjustments.brightness * 2.55; // Blue
            }

            // Contrast
            if (this.state.adjustments.contrast !== 0) {
                const contrast = (this.state.adjustments.contrast + 100) / 100;
                data[i] = ((data[i] - 128) * contrast + 128);     // Red
                data[i + 1] = ((data[i + 1] - 128) * contrast + 128); // Green
                data[i + 2] = ((data[i + 2] - 128) * contrast + 128); // Blue
            }

            // Clamp values
            data[i] = Math.max(0, Math.min(255, data[i]));
            data[i + 1] = Math.max(0, Math.min(255, data[i + 1]));
            data[i + 2] = Math.max(0, Math.min(255, data[i + 2]));
        }

        ctx.putImageData(imageData, 0, 0);
        this.updateCanvasPreview(canvas);
    }

    applyFilter(filterName) {
        this.saveState();

        const canvas = this.state.cropper.getCroppedCanvas();
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Apply CSS filters
        let filterString = '';

        switch (filterName) {
            case 'grayscale':
                filterString = 'grayscale(100%)';
                break;
            case 'sepia':
                filterString = 'sepia(100%)';
                break;
            case 'vintage':
                filterString = 'sepia(50%) contrast(120%) brightness(110%)';
                break;
            case 'cool':
                filterString = 'hue-rotate(-20deg) saturate(120%)';
                break;
            case 'warm':
                filterString = 'hue-rotate(20deg) saturate(120%)';
                break;
            case 'high-contrast':
                filterString = 'contrast(150%) brightness(110%)';
                break;
            case 'soft-focus':
                filterString = 'blur(1px) brightness(110%)';
                break;
            default:
                filterString = 'none';
        }

        ctx.filter = filterString;
        this.updateCanvasPreview(canvas);
        this.markAsChanged();
    }

    applyResize() {
        const width = parseInt(this.modal.querySelector('.cms-resize-width').value);
        const height = parseInt(this.modal.querySelector('.cms-resize-height').value);
        const method = this.modal.querySelector('.cms-resize-type').value;

        if (width > 0 && height > 0) {
            this.saveState();
            // Resize logic would be implemented here
            this.markAsChanged();
        }
    }

    // History Management
    saveState() {
        const state = {
            cropperData: this.state.cropper ? this.state.cropper.getData() : null,
            adjustments: { ...this.state.adjustments },
            filters: [...this.state.filters],
            timestamp: Date.now()
        };

        // Remove future states if we're not at the end
        this.state.history = this.state.history.slice(0, this.state.historyIndex + 1);

        // Add new state
        this.state.history.push(state);
        this.state.historyIndex = this.state.history.length - 1;

        // Limit history size
        if (this.state.history.length > 20) {
            this.state.history.shift();
            this.state.historyIndex--;
        }

        this.updateHistoryButtons();
    }

    undo() {
        if (this.state.historyIndex > 0) {
            this.state.historyIndex--;
            this.restoreState(this.state.history[this.state.historyIndex]);
        }
    }

    redo() {
        if (this.state.historyIndex < this.state.history.length - 1) {
            this.state.historyIndex++;
            this.restoreState(this.state.history[this.state.historyIndex]);
        }
    }

    restoreState(state) {
        if (state.cropperData && this.state.cropper) {
            this.state.cropper.setData(state.cropperData);
        }

        this.state.adjustments = { ...state.adjustments };
        this.state.filters = [...state.filters];

        // Update UI
        this.updateAdjustmentSliders();
        this.updateHistoryButtons();
        this.applyAdjustments();
    }

    resetAll() {
        if (confirm('Are you sure you want to reset all changes?')) {
            this.state.cropper.reset();
            this.state.adjustments = {
                brightness: 0,
                contrast: 0,
                saturation: 0,
                hue: 0,
                blur: 0,
                sharpen: 0
            };
            this.state.filters = [];
            this.state.hasChanges = false;

            this.updateAdjustmentSliders();
            this.updateChangesIndicator();
            this.saveState();
        }
    }

    // Save and Cancel
    async save(saveAsCopy = false) {
        if (!this.state.hasChanges && !saveAsCopy) {
            this.close();
            return;
        }

        const canvas = this.state.cropper.getCroppedCanvas({
            width: this.modal.querySelector('.cms-resize-width').value || undefined,
            height: this.modal.querySelector('.cms-resize-height').value || undefined,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });

        if (!canvas) {
            this.showError('Unable to process image');
            return;
        }

        // Show loading
        this.showSaveProgress();

        try {
            // Convert canvas to blob
            const blob = await new Promise(resolve => {
                canvas.toBlob(resolve, 'image/jpeg', 0.9);
            });

            // Prepare form data
            const formData = new FormData();
            formData.append('image', blob, this.asset.filename);
            formData.append('asset_id', this.asset.id);
            formData.append('save_as_copy', saveAsCopy ? '1' : '0');
            formData.append('adjustments', JSON.stringify(this.state.adjustments));
            formData.append('filters', JSON.stringify(this.state.filters));

            // Send to server
            const response = await fetch('/cms/api/assets/edit', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(saveAsCopy ? 'Copy saved successfully' : 'Changes saved successfully');

                if (this.options.onSave) {
                    this.options.onSave(data.asset, saveAsCopy);
                }

                this.close();
            } else {
                throw new Error(data.message || 'Save failed');
            }

        } catch (error) {
            this.showError('Failed to save: ' + error.message);
        } finally {
            this.hideSaveProgress();
        }
    }

    cancel() {
        if (this.state.hasChanges) {
            if (!confirm('You have unsaved changes. Are you sure you want to close?')) {
                return;
            }
        }

        this.close();
    }

    close() {
        // Cleanup
        if (this.state.cropper) {
            this.state.cropper.destroy();
        }

        // Remove modal
        this.modal.remove();
        document.body.style.overflow = '';

        if (this.options.onCancel) {
            this.options.onCancel();
        }
    }

    // Utility Methods
    markAsChanged() {
        this.state.hasChanges = true;
        this.updateChangesIndicator();
    }

    updateChangesIndicator() {
        const indicator = this.modal.querySelector('.cms-changes-indicator');
        indicator.style.display = this.state.hasChanges ? 'inline-flex' : 'none';
    }

    updateHistoryButtons() {
        const undoBtn = this.modal.querySelector('.cms-undo');
        const redoBtn = this.modal.querySelector('.cms-redo');

        undoBtn.disabled = this.state.historyIndex <= 0;
        redoBtn.disabled = this.state.historyIndex >= this.state.history.length - 1;
    }

    updateAdjustmentSliders() {
        Object.keys(this.state.adjustments).forEach(key => {
            const slider = this.modal.querySelector(`.cms-${key}`);
            const valueDisplay = slider?.nextElementSibling;

            if (slider) {
                slider.value = this.state.adjustments[key];
                if (valueDisplay) {
                    const suffix = key === 'hue' ? '°' : '';
                    valueDisplay.textContent = this.state.adjustments[key] + suffix;
                }
            }
        });
    }

    updateCanvasPreview(canvas) {
        // Update the main canvas with the edited version
        if (this.state.cropper) {
            this.state.cropper.replace(canvas.toDataURL());
        }
    }

    showSaveProgress() {
        // Implementation for save progress indicator
    }

    hideSaveProgress() {
        // Implementation to hide save progress indicator
    }

    showError(message) {
        console.error(message);
        // Implementation for error notification
    }

    showSuccess(message) {
        console.log(message);
        // Implementation for success notification
    }

    getToolTitle(tool) {
        const titles = {
            crop: 'Crop',
            rotate: 'Rotate',
            flip: 'Flip',
            adjust: 'Adjust',
            filter: 'Filter',
            resize: 'Resize'
        };
        return titles[tool] || tool;
    }

    formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
    }

    getImageData(img) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0);
        return {
            width: img.width,
            height: img.height,
            dataUrl: canvas.toDataURL()
        };
    }

    // Cropper event handlers
    onCropperReady() {
        this.updateZoomLevel();
    }

    onCrop(e) {
        this.updateSelectionInfo(e.detail);
    }

    onZoom(e) {
        this.updateZoomLevel(e.detail.ratio);
    }

    updateZoomLevel(ratio) {
        const zoomLevel = this.modal.querySelector('.cms-zoom-level');
        if (ratio) {
            zoomLevel.textContent = Math.round(ratio * 100) + '%';
        }
    }

    updateSelectionInfo(cropData) {
        const info = this.modal.querySelector('.cms-selection-info');
        if (cropData && cropData.width > 0 && cropData.height > 0) {
            info.textContent = `${Math.round(cropData.width)} × ${Math.round(cropData.height)}`;
        } else {
            info.textContent = '';
        }
    }

    // Preset methods
    applyCropPreset(width, height) {
        this.saveState();
        const aspectRatio = width / height;
        this.state.cropper.setAspectRatio(aspectRatio);

        // Set crop box to preset size if possible
        const containerData = this.state.cropper.getContainerData();
        const scale = Math.min(containerData.width / width, containerData.height / height);

        this.state.cropper.setCropBoxData({
            left: (containerData.width - width * scale) / 2,
            top: (containerData.height - height * scale) / 2,
            width: width * scale,
            height: height * scale
        });
    }

    applyAdjustmentPreset(preset) {
        const presets = {
            vibrant: { brightness: 10, contrast: 20, saturation: 30 },
            soft: { brightness: 15, contrast: -10, saturation: -5 },
            dramatic: { brightness: -5, contrast: 40, saturation: 20 },
            reset: { brightness: 0, contrast: 0, saturation: 0, hue: 0, blur: 0, sharpen: 0 }
        };

        if (presets[preset]) {
            Object.assign(this.state.adjustments, presets[preset]);
            this.updateAdjustmentSliders();
            this.applyAdjustments();
            this.markAsChanged();
        }
    }

    updateFilterIntensity(intensity) {
        // Update the intensity of the current filter
        const activeFilter = this.modal.querySelector('.cms-filter-btn.active');
        if (activeFilter && activeFilter.dataset.filter !== 'none') {
            this.applyFilter(activeFilter.dataset.filter, intensity / 100);
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImageEditor;
} else {
    window.ImageEditor = ImageEditor;
}