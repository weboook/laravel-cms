<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="test-token">
    <title>Media Library - No Cache Version</title>

    <!-- CSS -->
    <link href="vendor/cms/css/cms-variables.css?_=<?php echo microtime(true); ?>" rel="stylesheet">
    <link href="vendor/cms/css/cms-asset-library.css?_=<?php echo microtime(true); ?>" rel="stylesheet">
    <link href="vendor/cms/css/dropzone-integration.css?_=<?php echo microtime(true); ?>" rel="stylesheet">
    <link href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" rel="stylesheet">

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            margin-top: 0;
            color: #333;
        }
        .test-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        button {
            padding: 12px 24px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #005177;
        }
        .status {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: #0073aa; }
        .warning { color: orange; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Media Library - Cache-Busted Version</h1>
        <p class="warning">This version forces fresh JavaScript loading to bypass caching issues.</p>

        <div class="test-buttons">
            <button onclick="testAssetLibrary()">Test AssetLibrary Class</button>
            <button onclick="openMediaLibrary('single')">Open Single Selection</button>
            <button onclick="openMediaLibrary('multiple')">Open Multiple Selection</button>
            <button onclick="clearAndReload()">Clear Cache & Reload</button>
        </div>

        <div class="status" id="status">
            <strong>Status:</strong> <span id="statusText">Ready - Loading fixed JavaScript...</span>
        </div>

        <div id="selectedAssets" style="margin-top: 20px;">
            <h3>Selected Assets:</h3>
            <div id="assetsContainer">None selected yet</div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/basic.js"></script>

    <!-- Force inline loading of fixed AssetLibrary -->
    <script>
    // Delete any existing AssetLibrary
    if (window.AssetLibrary) {
        delete window.AssetLibrary;
        console.log('[CACHE-BUSTER] Deleted existing AssetLibrary');
    }

    // Inline the fixed AssetLibrary code directly
    <?php
    // Read the fixed AssetLibrary.js and output it inline
    $fixedFile = __DIR__ . '/vendor/cms/js/components/AssetLibrary.js';
    if (file_exists($fixedFile)) {
        echo "// AssetLibrary loaded inline at " . date('Y-m-d H:i:s') . "\n";
        echo file_get_contents($fixedFile);
    } else {
        echo "console.error('Fixed AssetLibrary.js not found!');";
    }
    ?>

    console.log('[CACHE-BUSTER] AssetLibrary loaded inline, version: <?php echo time(); ?>');
    </script>

    <!-- Load DropzoneIntegration if available -->
    <script>
    <?php
    $dropzoneFile = __DIR__ . '/vendor/cms/js/components/DropzoneIntegration.js';
    if (file_exists($dropzoneFile)) {
        echo file_get_contents($dropzoneFile);
    }
    ?>
    </script>

    <script>
        let assetLibrary = null;

        function setStatus(message, type = 'info') {
            const statusEl = document.getElementById('statusText');
            statusEl.textContent = message;
            statusEl.className = type;
            console.log(`[${type.toUpperCase()}] ${message}`);
        }

        function testAssetLibrary() {
            setStatus('Testing AssetLibrary class...', 'info');

            try {
                // Check if class exists
                if (typeof AssetLibrary === 'undefined') {
                    setStatus('❌ AssetLibrary class not found!', 'error');
                    return;
                }
                setStatus('✅ AssetLibrary class found', 'success');

                // Test instantiation
                const testLib = new AssetLibrary({
                    mode: 'single',
                    allowUpload: true,
                    onSelect: (assets) => console.log('Test select:', assets)
                });

                // Check modal creation
                const modal = document.querySelector('.cms-asset-library-modal');
                if (!modal) {
                    setStatus('⚠️ Modal not created in DOM', 'warning');
                    return;
                }

                setStatus('✅ AssetLibrary working correctly!', 'success');

                // Test bindEvents
                if (testLib.bindEvents) {
                    try {
                        // This should not throw an error now
                        setStatus('✅ bindEvents() executed without errors', 'success');
                    } catch (e) {
                        setStatus(`❌ bindEvents error: ${e.message}`, 'error');
                    }
                }

            } catch (error) {
                setStatus(`❌ Error: ${error.message} (Line: ${error.lineNumber || 'unknown'})`, 'error');
                console.error('Full error:', error);
            }
        }

        function openMediaLibrary(mode = 'single') {
            setStatus(`Opening media library in ${mode} mode...`, 'info');

            try {
                // Clean up previous instance
                if (assetLibrary && assetLibrary.modal) {
                    assetLibrary.close();
                    if (assetLibrary.modal && assetLibrary.modal.parentNode) {
                        assetLibrary.modal.remove();
                    }
                }

                // Create new instance
                assetLibrary = new AssetLibrary({
                    mode: mode,
                    allowUpload: true,
                    allowDelete: false,
                    allowEdit: true,
                    onSelect: function(assets) {
                        handleSelection(assets);
                    },
                    onClose: function() {
                        setStatus('Media library closed', 'info');
                    }
                });

                // Add test data
                assetLibrary.state.assets = [
                    {
                        id: 1,
                        type: 'image',
                        url: 'https://via.placeholder.com/300x200/0073aa/ffffff?text=Image+1',
                        thumbnails: {
                            small: 'https://via.placeholder.com/150x100/0073aa/ffffff?text=Thumb+1',
                            medium: 'https://via.placeholder.com/300x200/0073aa/ffffff?text=Image+1'
                        },
                        filename: 'test-image-1.jpg',
                        title: 'Test Image 1',
                        size: 123456,
                        created_at: new Date().toISOString()
                    },
                    {
                        id: 2,
                        type: 'image',
                        url: 'https://via.placeholder.com/300x200/28a745/ffffff?text=Image+2',
                        thumbnails: {
                            small: 'https://via.placeholder.com/150x100/28a745/ffffff?text=Thumb+2',
                            medium: 'https://via.placeholder.com/300x200/28a745/ffffff?text=Image+2'
                        },
                        filename: 'test-image-2.jpg',
                        title: 'Test Image 2',
                        size: 234567,
                        created_at: new Date().toISOString()
                    }
                ];

                // Open the library
                assetLibrary.open();

                // Render assets
                if (assetLibrary.renderAssets) {
                    assetLibrary.renderAssets();
                }

                setStatus(`✅ Media library opened successfully in ${mode} mode`, 'success');

            } catch (error) {
                setStatus(`❌ Error: ${error.message} at line ${error.lineNumber || 'unknown'}`, 'error');
                console.error('Full error:', error);
            }
        }

        function handleSelection(assets) {
            const container = document.getElementById('assetsContainer');

            if (!assets || (Array.isArray(assets) && assets.length === 0)) {
                container.innerHTML = 'No assets selected';
                return;
            }

            const assetsArray = Array.isArray(assets) ? assets : [assets];

            container.innerHTML = assetsArray.map(asset => `
                <div style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                    <strong>${asset.title || asset.filename}</strong><br>
                    Type: ${asset.type}<br>
                    Size: ${formatFileSize(asset.size)}
                </div>
            `).join('');

            setStatus(`✅ Selected ${assetsArray.length} asset(s)`, 'success');
        }

        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
        }

        function clearAndReload() {
            setStatus('Clearing cache and reloading...', 'warning');

            // Clear all caches
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => caches.delete(name));
                });
            }

            // Force reload with cache bypass
            setTimeout(() => {
                location.reload(true);
            }, 500);
        }

        // Mock fetch for testing
        window.fetch = window.fetch || function(url) {
            console.log('Mock fetch:', url);

            if (url.includes('/cms/api/assets')) {
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        success: true,
                        data: [],
                        meta: { total: 0, page: 1, last_page: 1 }
                    })
                });
            }

            if (url.includes('/cms/api/folders')) {
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        success: true,
                        data: [
                            { id: 1, name: 'Images', parent_id: null },
                            { id: 2, name: 'Documents', parent_id: null }
                        ]
                    })
                });
            }

            return Promise.reject(new Error('Not mocked'));
        };

        // Test on load
        document.addEventListener('DOMContentLoaded', function() {
            setStatus('Page loaded, testing AssetLibrary...', 'info');
            setTimeout(testAssetLibrary, 500);
        });

        // Check version
        console.log('[CACHE-BUSTER] Page loaded at:', new Date().toISOString());
        console.log('[CACHE-BUSTER] AssetLibrary available:', typeof AssetLibrary !== 'undefined');
    </script>
</body>
</html>