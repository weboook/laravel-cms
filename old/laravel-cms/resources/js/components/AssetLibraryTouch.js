/**
 * Mobile Touch Handlers for Asset Library
 * Provides comprehensive touch and gesture support for mobile devices
 */
class AssetLibraryTouch {
    constructor(library) {
        this.library = library;
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.touchStartTime = 0;
        this.lastTap = 0;
        this.selectedItems = new Set();
        this.multiSelectMode = false;
        this.isScrolling = false;
        this.longPressTimer = null;
        this.swipeThreshold = 50;
        this.longPressDelay = 500;
        this.doubleTapDelay = 300;

        // Gesture state
        this.gestureState = {
            isGesturing: false,
            initialDistance: 0,
            initialScale: 1,
            currentScale: 1,
            centerX: 0,
            centerY: 0
        };

        this.initTouchHandlers();
        this.initGestureHandlers();
        this.initAccessibilityFeatures();
    }

    initTouchHandlers() {
        const container = this.library.modal;

        // Check if modal exists before proceeding
        if (!container) {
            console.warn('Asset library modal not found, skipping touch handlers initialization');
            return;
        }

        // Prevent default touch behaviors
        container.addEventListener('touchstart', (e) => {
            this.handleTouchStart(e);
        }, { passive: false });

        container.addEventListener('touchmove', (e) => {
            this.handleTouchMove(e);
        }, { passive: false });

        container.addEventListener('touchend', (e) => {
            this.handleTouchEnd(e);
        }, { passive: false });

        // Handle touch cancel (when touch is interrupted)
        container.addEventListener('touchcancel', (e) => {
            this.handleTouchCancel(e);
        });

        // Asset-specific touch handlers
        this.initAssetTouchHandlers();

        // Swipe navigation
        this.initSwipeNavigation();

        // Pull to refresh
        this.initPullToRefresh();
    }

    handleTouchStart(e) {
        const touch = e.touches[0];
        const item = e.target.closest('.asset-library-item');

        this.touchStartX = touch.clientX;
        this.touchStartY = touch.clientY;
        this.touchStartTime = Date.now();
        this.isScrolling = false;

        // Clear any existing long press timer
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
        }

        if (item) {
            // Start long press detection for multi-select
            this.longPressTimer = setTimeout(() => {
                this.handleLongPress(item, e);
            }, this.longPressDelay);

            // Add visual feedback
            item.classList.add('touch-active');

            // Detect double tap for quick actions
            const currentTime = Date.now();
            if (currentTime - this.lastTap < this.doubleTapDelay) {
                this.handleDoubleTap(item, e);
                e.preventDefault();
            }
            this.lastTap = currentTime;
        }

        // Handle modal dragging for repositioning
        this.handleModalDragStart(e);
    }

    handleTouchMove(e) {
        const touch = e.touches[0];
        const deltaX = touch.clientX - this.touchStartX;
        const deltaY = touch.clientY - this.touchStartY;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

        // Cancel long press if user moves finger
        if (distance > 10 && this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }

        // Detect if user is scrolling
        if (Math.abs(deltaY) > Math.abs(deltaX) && Math.abs(deltaY) > 10) {
            this.isScrolling = true;
        }

        // Remove touch active state on move
        const activeItems = this.library.modal.querySelectorAll('.touch-active');
        activeItems.forEach(item => item.classList.remove('touch-active'));

        // Handle swipe gestures for navigation
        this.handleSwipeGesture(deltaX, deltaY, e);

        // Handle modal dragging
        this.handleModalDrag(e);
    }

    handleTouchEnd(e) {
        const touch = e.changedTouches[0];
        const deltaX = touch.clientX - this.touchStartX;
        const deltaY = touch.clientY - this.touchStartY;
        const touchDuration = Date.now() - this.touchStartTime;
        const item = e.target.closest('.asset-library-item');

        // Clear long press timer
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }

        // Remove touch active state
        const activeItems = this.library.modal.querySelectorAll('.touch-active');
        activeItems.forEach(item => item.classList.remove('touch-active'));

        // Handle quick tap (if not scrolling and quick enough)
        if (!this.isScrolling && touchDuration < 200 && item) {
            this.handleQuickTap(item, e);
        }

        // Handle swipe completion
        this.handleSwipeComplete(deltaX, deltaY);

        // Handle modal drag end
        this.handleModalDragEnd(e);

        // Reset state
        this.isScrolling = false;
    }

    handleTouchCancel(e) {
        // Clean up on touch interruption
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }

        const activeItems = this.library.modal.querySelectorAll('.touch-active');
        activeItems.forEach(item => item.classList.remove('touch-active'));

        this.isScrolling = false;
        this.gestureState.isGesturing = false;
    }

    handleLongPress(item, e) {
        e.preventDefault();

        // Enable multi-select mode
        this.enableMultiSelect();

        // Toggle item selection
        this.toggleItemSelection(item);

        // Provide haptic feedback
        this.hapticFeedback('medium');

        // Show multi-select UI
        this.showMultiSelectUI();

        // Add visual indication
        item.classList.add('long-pressed');
        setTimeout(() => {
            item.classList.remove('long-pressed');
        }, 200);
    }

    handleQuickTap(item, e) {
        const assetId = parseInt(item.dataset.id);

        if (this.multiSelectMode) {
            // In multi-select mode, tap toggles selection
            this.toggleItemSelection(item);
        } else {
            // Single tap - select or show details
            const asset = this.library.state.assets.find(a => a.id === assetId);
            if (asset) {
                this.library.selectAsset(asset);
            }
        }

        this.hapticFeedback('light');
    }

    handleDoubleTap(item, e) {
        e.preventDefault();

        const assetId = parseInt(item.dataset.id);
        const asset = this.library.state.assets.find(a => a.id === assetId);

        if (asset) {
            // Double tap always selects and closes modal
            this.library.selectAsset(asset);
            this.library.close();
            this.hapticFeedback('heavy');
        }
    }

    initAssetTouchHandlers() {
        // Handle checkbox touches
        this.library.modal.addEventListener('touchend', (e) => {
            const checkbox = e.target.closest('.asset-library-item-checkbox');
            if (checkbox && !this.isScrolling) {
                e.preventDefault();
                const item = e.target.closest('.asset-library-item');
                if (item) {
                    this.toggleItemSelection(item);
                    this.hapticFeedback('light');
                }
            }
        });

        // Handle quick action buttons
        this.library.modal.addEventListener('touchend', (e) => {
            const quickAction = e.target.closest('.quick-view, .quick-edit, .quick-select');
            if (quickAction && !this.isScrolling) {
                e.preventDefault();
                const item = e.target.closest('.asset-library-item');
                const assetId = parseInt(item.dataset.id);
                const asset = this.library.state.assets.find(a => a.id === assetId);

                if (quickAction.classList.contains('quick-view')) {
                    this.library.showPreview(asset);
                } else if (quickAction.classList.contains('quick-edit')) {
                    this.library.editAsset(asset);
                } else if (quickAction.classList.contains('quick-select')) {
                    this.library.selectAsset(asset);
                }

                this.hapticFeedback('medium');
            }
        });
    }

    initSwipeNavigation() {
        // Horizontal swipes for pagination
        this.swipeHandlers = {
            left: () => this.library.nextPage(),
            right: () => this.library.previousPage(),
            up: () => this.loadMoreAssets(),
            down: () => this.refreshAssets()
        };
    }

    handleSwipeGesture(deltaX, deltaY, e) {
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        // Only handle swipes if not in an input or scrollable area
        if (e.target.closest('input, textarea, .asset-library-sidebar')) {
            return;
        }

        // Horizontal swipe detection
        if (absX > this.swipeThreshold && absX > absY) {
            e.preventDefault();

            if (deltaX > 0) {
                this.showSwipeIndicator('right');
            } else {
                this.showSwipeIndicator('left');
            }
        }

        // Vertical swipe detection
        if (absY > this.swipeThreshold && absY > absX) {
            if (deltaY < 0) {
                this.showSwipeIndicator('up');
            } else {
                this.showSwipeIndicator('down');
            }
        }
    }

    handleSwipeComplete(deltaX, deltaY) {
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        this.hideSwipeIndicators();

        // Execute swipe actions
        if (absX > this.swipeThreshold && absX > absY) {
            if (deltaX > 0 && this.swipeHandlers.right) {
                this.swipeHandlers.right();
                this.hapticFeedback('medium');
            } else if (deltaX < 0 && this.swipeHandlers.left) {
                this.swipeHandlers.left();
                this.hapticFeedback('medium');
            }
        } else if (absY > this.swipeThreshold && absY > absX) {
            if (deltaY < 0 && this.swipeHandlers.up) {
                this.swipeHandlers.up();
                this.hapticFeedback('light');
            } else if (deltaY > 0 && this.swipeHandlers.down) {
                this.swipeHandlers.down();
                this.hapticFeedback('light');
            }
        }
    }

    initGestureHandlers() {
        const container = this.library.modal;

        // Pinch to zoom for grid size adjustment
        container.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                this.gestureState.isGesturing = true;
                this.gestureState.initialDistance = this.getDistance(e.touches[0], e.touches[1]);
                this.gestureState.initialScale = this.getCurrentGridScale();

                const center = this.getCenter(e.touches[0], e.touches[1]);
                this.gestureState.centerX = center.x;
                this.gestureState.centerY = center.y;
            }
        }, { passive: false });

        container.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2 && this.gestureState.isGesturing) {
                e.preventDefault();

                const distance = this.getDistance(e.touches[0], e.touches[1]);
                const scale = distance / this.gestureState.initialDistance;

                this.gestureState.currentScale = this.gestureState.initialScale * scale;

                // Apply grid size changes with debouncing
                this.updateGridSizeWithGesture(this.gestureState.currentScale);
            }
        }, { passive: false });

        container.addEventListener('touchend', (e) => {
            if (this.gestureState.isGesturing) {
                this.gestureState.isGesturing = false;
                this.finalizeGridSizeChange();
            }
        });

        // Three-finger gestures for advanced actions
        container.addEventListener('touchstart', (e) => {
            if (e.touches.length === 3) {
                e.preventDefault();
                this.handleThreeFingerGesture(e);
            }
        }, { passive: false });
    }

    initPullToRefresh() {
        const assetsContainer = this.library.modal.querySelector('.asset-library-grid-container');

        // Check if container exists before adding listeners
        if (!assetsContainer) {
            console.warn('Asset library container not found, skipping pull-to-refresh initialization');
            return;
        }

        let pullDistance = 0;
        let isPulling = false;
        let startY = 0;

        assetsContainer.addEventListener('touchstart', (e) => {
            if (assetsContainer.scrollTop === 0) {
                startY = e.touches[0].clientY;
                isPulling = true;
            }
        }, { passive: true });

        assetsContainer.addEventListener('touchmove', (e) => {
            if (isPulling && assetsContainer.scrollTop === 0) {
                pullDistance = e.touches[0].clientY - startY;

                if (pullDistance > 0) {
                    e.preventDefault();
                    this.updatePullToRefreshIndicator(pullDistance);
                }
            }
        }, { passive: false });

        assetsContainer.addEventListener('touchend', (e) => {
            if (isPulling) {
                isPulling = false;

                if (pullDistance > 80) {
                    this.triggerRefresh();
                }

                this.hidePullToRefreshIndicator();
                pullDistance = 0;
            }
        });
    }

    initAccessibilityFeatures() {
        // Voice over support for gestures
        if ('speechSynthesis' in window) {
            this.speechSynthesis = window.speechSynthesis;
        }

        // Announce gesture actions
        this.announceGesture = (message) => {
            if (this.speechSynthesis && this.library.options.announceGestures) {
                const utterance = new SpeechSynthesisUtterance(message);
                utterance.volume = 0.3;
                utterance.rate = 1.2;
                this.speechSynthesis.speak(utterance);
            }
        };

        // High contrast mode detection
        this.checkHighContrastMode();

        // Reduced motion detection
        this.checkReducedMotionMode();
    }

    // Multi-select methods
    enableMultiSelect() {
        if (!this.multiSelectMode) {
            this.multiSelectMode = true;
            this.library.modal.classList.add('multi-select-mode');
            this.showMultiSelectToolbar();
            this.announceGesture('Multi-select mode enabled');
        }
    }

    disableMultiSelect() {
        this.multiSelectMode = false;
        this.selectedItems.clear();
        this.library.modal.classList.remove('multi-select-mode');
        this.hideMultiSelectToolbar();
        this.updateAllSelectionStates();
        this.announceGesture('Multi-select mode disabled');
    }

    toggleItemSelection(item) {
        const assetId = parseInt(item.dataset.id);

        if (this.selectedItems.has(assetId)) {
            this.selectedItems.delete(assetId);
            item.classList.remove('selected');
            const checkbox = item.querySelector('.asset-library-item-checkbox');
            if (checkbox) checkbox.style.opacity = '0';
        } else {
            this.selectedItems.add(assetId);
            item.classList.add('selected');
            const checkbox = item.querySelector('.asset-library-item-checkbox');
            if (checkbox) checkbox.style.opacity = '1';
        }

        this.updateSelectionCount();
    }

    updateSelectionCount() {
        const count = this.selectedItems.size;
        const countElement = this.library.modal.querySelector('.selection-count');

        if (countElement) {
            countElement.textContent = `${count} selected`;
        }

        // Show/hide bulk actions
        const bulkActions = this.library.modal.querySelector('.bulk-actions');
        if (bulkActions) {
            bulkActions.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    updateAllSelectionStates() {
        const items = this.library.modal.querySelectorAll('.asset-library-item');
        items.forEach(item => {
            const assetId = parseInt(item.dataset.id);
            const checkbox = item.querySelector('.asset-library-item-checkbox');

            if (this.selectedItems.has(assetId)) {
                item.classList.add('selected');
                if (checkbox) checkbox.style.opacity = '1';
            } else {
                item.classList.remove('selected');
                if (checkbox) checkbox.style.opacity = '0';
            }
        });
    }

    // UI Methods
    showMultiSelectUI() {
        const toolbar = this.library.modal.querySelector('.asset-library-toolbar');

        if (!toolbar.querySelector('.multi-select-toolbar')) {
            const multiSelectToolbar = document.createElement('div');
            multiSelectToolbar.className = 'multi-select-toolbar';
            multiSelectToolbar.innerHTML = `
                <button class="asset-library-button select-all">Select All</button>
                <button class="asset-library-button deselect-all">Deselect All</button>
                <button class="asset-library-button exit-multi-select">Exit</button>
                <span class="selection-count">0 selected</span>
            `;

            toolbar.appendChild(multiSelectToolbar);

            // Bind events
            multiSelectToolbar.querySelector('.select-all').addEventListener('click', () => {
                this.selectAllVisible();
            });

            multiSelectToolbar.querySelector('.deselect-all').addEventListener('click', () => {
                this.deselectAll();
            });

            multiSelectToolbar.querySelector('.exit-multi-select').addEventListener('click', () => {
                this.disableMultiSelect();
            });
        }
    }

    showMultiSelectToolbar() {
        const toolbar = this.library.modal.querySelector('.multi-select-toolbar');
        if (toolbar) {
            toolbar.style.display = 'flex';
        }
    }

    hideMultiSelectToolbar() {
        const toolbar = this.library.modal.querySelector('.multi-select-toolbar');
        if (toolbar) {
            toolbar.style.display = 'none';
        }
    }

    showSwipeIndicator(direction) {
        this.hideSwipeIndicators();

        const indicator = document.createElement('div');
        indicator.className = `swipe-indicator swipe-${direction}`;
        indicator.innerHTML = this.getSwipeIcon(direction);

        this.library.modal.appendChild(indicator);

        // Animate in
        setTimeout(() => {
            indicator.classList.add('active');
        }, 10);
    }

    hideSwipeIndicators() {
        const indicators = this.library.modal.querySelectorAll('.swipe-indicator');
        indicators.forEach(indicator => {
            indicator.classList.remove('active');
            setTimeout(() => {
                indicator.remove();
            }, 200);
        });
    }

    updatePullToRefreshIndicator(distance) {
        let indicator = this.library.modal.querySelector('.pull-refresh-indicator');

        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'pull-refresh-indicator';
            indicator.innerHTML = '<div class="refresh-icon">⟳</div><span>Pull to refresh</span>';
            this.library.modal.querySelector('.asset-library-grid-container').prepend(indicator);
        }

        const progress = Math.min(distance / 80, 1);
        indicator.style.transform = `translateY(${distance}px) scale(${progress})`;
        indicator.style.opacity = progress;

        if (progress >= 1) {
            indicator.querySelector('span').textContent = 'Release to refresh';
            indicator.classList.add('ready');
        } else {
            indicator.querySelector('span').textContent = 'Pull to refresh';
            indicator.classList.remove('ready');
        }
    }

    hidePullToRefreshIndicator() {
        const indicator = this.library.modal.querySelector('.pull-refresh-indicator');
        if (indicator) {
            indicator.style.transform = 'translateY(-100px)';
            indicator.style.opacity = '0';
            setTimeout(() => {
                indicator.remove();
            }, 300);
        }
    }

    // Gesture utility methods
    getDistance(touch1, touch2) {
        const dx = touch1.clientX - touch2.clientX;
        const dy = touch1.clientY - touch2.clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    getCenter(touch1, touch2) {
        return {
            x: (touch1.clientX + touch2.clientX) / 2,
            y: (touch1.clientY + touch2.clientY) / 2
        };
    }

    getCurrentGridScale() {
        const gridContainer = this.library.modal.querySelector('.asset-library-grid');
        if (!gridContainer) return 1;

        const style = getComputedStyle(gridContainer);
        const templateColumns = style.getPropertyValue('grid-template-columns');
        const columnWidth = templateColumns.split(' ')[0];

        if (columnWidth.includes('minmax')) {
            const minSize = columnWidth.match(/minmax\((\d+)px/);
            return minSize ? parseInt(minSize[1]) / 180 : 1; // Base size is 180px
        }

        return 1;
    }

    updateGridSizeWithGesture(scale) {
        // Debounce the updates
        if (this.gridUpdateTimeout) {
            clearTimeout(this.gridUpdateTimeout);
        }

        this.gridUpdateTimeout = setTimeout(() => {
            const newSize = Math.max(120, Math.min(300, 180 * scale));
            this.updateGridSize(newSize);
        }, 16); // ~60fps
    }

    updateGridSize(size) {
        const gridContainer = this.library.modal.querySelector('.asset-library-grid');
        if (gridContainer) {
            gridContainer.style.gridTemplateColumns = `repeat(auto-fill, minmax(${size}px, 1fr))`;
        }
    }

    finalizeGridSizeChange() {
        if (this.gridUpdateTimeout) {
            clearTimeout(this.gridUpdateTimeout);
        }

        // Snap to nearest preset size
        const currentSize = this.getCurrentGridScale() * 180;
        const presets = [120, 150, 180, 220, 260, 300];
        const nearest = presets.reduce((prev, curr) =>
            Math.abs(curr - currentSize) < Math.abs(prev - currentSize) ? curr : prev
        );

        this.updateGridSize(nearest);
        this.hapticFeedback('light');
    }

    // Action methods
    selectAllVisible() {
        const visibleItems = this.library.modal.querySelectorAll('.asset-library-item');
        visibleItems.forEach(item => {
            const assetId = parseInt(item.dataset.id);
            this.selectedItems.add(assetId);
            item.classList.add('selected');
            const checkbox = item.querySelector('.asset-library-item-checkbox');
            if (checkbox) checkbox.style.opacity = '1';
        });

        this.updateSelectionCount();
        this.hapticFeedback('medium');
    }

    deselectAll() {
        this.selectedItems.clear();
        this.updateAllSelectionStates();
        this.updateSelectionCount();
        this.hapticFeedback('light');
    }

    loadMoreAssets() {
        if (this.library.state.hasMore && !this.library.state.loading) {
            this.library.loadAssets(this.library.state.page + 1, true);
        }
    }

    refreshAssets() {
        this.library.loadAssets(1, false);
    }

    triggerRefresh() {
        this.refreshAssets();
        this.announceGesture('Refreshing assets');
    }

    // Modal dragging for repositioning
    handleModalDragStart(e) {
        const header = e.target.closest('.asset-library-header');
        if (header && e.touches.length === 1) {
            this.isDraggingModal = true;
            this.modalStartX = e.touches[0].clientX;
            this.modalStartY = e.touches[0].clientY;

            const modal = this.library.modal.querySelector('.asset-library-content');
            const rect = modal.getBoundingClientRect();
            this.modalOffsetX = this.modalStartX - rect.left;
            this.modalOffsetY = this.modalStartY - rect.top;
        }
    }

    handleModalDrag(e) {
        if (this.isDraggingModal && e.touches.length === 1) {
            e.preventDefault();

            const modal = this.library.modal.querySelector('.asset-library-content');
            const newX = e.touches[0].clientX - this.modalOffsetX;
            const newY = e.touches[0].clientY - this.modalOffsetY;

            // Constrain to viewport
            const maxX = window.innerWidth - modal.offsetWidth;
            const maxY = window.innerHeight - modal.offsetHeight;

            const constrainedX = Math.max(0, Math.min(maxX, newX));
            const constrainedY = Math.max(0, Math.min(maxY, newY));

            modal.style.position = 'fixed';
            modal.style.left = constrainedX + 'px';
            modal.style.top = constrainedY + 'px';
            modal.style.transform = 'none';
        }
    }

    handleModalDragEnd(e) {
        this.isDraggingModal = false;
    }

    // Three-finger gestures
    handleThreeFingerGesture(e) {
        const touches = Array.from(e.touches);
        const centerY = touches.reduce((sum, touch) => sum + touch.clientY, 0) / touches.length;

        if (centerY < window.innerHeight / 3) {
            // Three fingers at top - close modal
            this.library.close();
        } else if (centerY > window.innerHeight * 2 / 3) {
            // Three fingers at bottom - toggle view
            this.library.toggleView();
        } else {
            // Three fingers in middle - toggle multi-select
            if (this.multiSelectMode) {
                this.disableMultiSelect();
            } else {
                this.enableMultiSelect();
            }
        }

        this.hapticFeedback('heavy');
    }

    // Utility methods
    hapticFeedback(intensity = 'light') {
        if ('vibrate' in navigator) {
            const patterns = {
                light: [10],
                medium: [10, 50, 10],
                heavy: [10, 100, 30, 100, 10]
            };

            navigator.vibrate(patterns[intensity] || patterns.light);
        }
    }

    getSwipeIcon(direction) {
        const icons = {
            left: '←',
            right: '→',
            up: '↑',
            down: '↓'
        };

        return icons[direction] || '';
    }

    checkHighContrastMode() {
        const isHighContrast = window.matchMedia('(prefers-contrast: high)').matches;
        if (isHighContrast) {
            this.library.modal.classList.add('high-contrast');
        }
    }

    checkReducedMotionMode() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            this.library.modal.classList.add('reduced-motion');
        }
    }

    // Cleanup
    destroy() {
        // Clear any active timers
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
        }

        if (this.gridUpdateTimeout) {
            clearTimeout(this.gridUpdateTimeout);
        }

        // Clear state
        this.selectedItems.clear();
        this.multiSelectMode = false;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AssetLibraryTouch;
} else {
    window.AssetLibraryTouch = AssetLibraryTouch;
}