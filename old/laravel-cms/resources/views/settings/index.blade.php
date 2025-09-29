<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - Laravel CMS</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .settings-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .settings-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .btn-save {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 0.75rem 2rem;
        }

        .btn-reset {
            background: linear-gradient(135deg, #dc3545, #e94560);
            border: none;
        }

        .route-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.5rem;
        }

        .route-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            margin: 0.25rem 0;
            background: #f8f9fa;
            border-radius: 0.25rem;
        }

        .ip-input {
            margin-bottom: 0.5rem;
        }

        .loading {
            display: none;
        }

        .alert-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
    </style>
</head>
<body>
    <div class="settings-header">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h1><i class="fas fa-cog"></i> CMS Settings</h1>
                    <p class="mb-0">Configure your Laravel CMS system settings, access controls, and preferences.</p>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-light" onclick="window.close()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form id="settingsForm" method="POST" action="{{ route('cms.settings.update') }}">
            @csrf
            @method('PUT')

            <!-- General Settings -->
            <div class="card settings-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sliders-h"></i> General Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="cmsEnabled"
                                           name="cms[enabled]" value="1"
                                           {{ $settings['cms']['enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="cmsEnabled">
                                        <strong>Enable CMS</strong>
                                    </label>
                                </div>
                                <div class="form-text">Enable or disable the entire CMS system</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoInject"
                                           name="cms[auto_inject]" value="1"
                                           {{ $settings['cms']['auto_inject'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="autoInject">
                                        <strong>Auto-inject CMS</strong>
                                    </label>
                                </div>
                                <div class="form-text">Automatically add CMS editor to all pages</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="debugMode"
                                           name="cms[debug_mode]" value="1"
                                           {{ $settings['cms']['debug_mode'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="debugMode">
                                        <strong>Debug Mode</strong>
                                    </label>
                                </div>
                                <div class="form-text">Enable debug logging and error display</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Route Access Control -->
            <div class="card settings-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-route"></i> Route Access Control</h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Excluded Routes</label>
                        <div class="form-text mb-3">Routes where the CMS editor will not be available. Use wildcards (*) for pattern matching.</div>

                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="newRoute" placeholder="e.g., admin/*, api/*, login">
                            <button type="button" class="btn btn-outline-primary" onclick="addRoute()">
                                <i class="fas fa-plus"></i> Add Route
                            </button>
                        </div>

                        <div class="route-list" id="routeList">
                            @foreach($settings['cms']['excluded_routes'] as $index => $route)
                                <div class="route-item">
                                    <span>{{ $route }}</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRoute({{ $index }})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <input type="hidden" name="cms[excluded_routes][]" value="{{ $route }}">
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <div class="input-group">
                                <input type="text" class="form-control" id="testRoute" placeholder="Test a route path">
                                <button type="button" class="btn btn-outline-info" onclick="testRoute()">
                                    <i class="fas fa-search"></i> Test Route
                                </button>
                            </div>
                            <div id="testResult" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Access Control -->
            <div class="card settings-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users"></i> User Access Control</h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Allowed User Groups</label>
                        <div class="form-text mb-3">User roles/groups that can access the CMS editor. Leave empty to allow all authenticated users.</div>

                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="newUserGroup" placeholder="e.g., admin, editor, content-manager">
                            <button type="button" class="btn btn-outline-primary" onclick="addUserGroup()">
                                <i class="fas fa-plus"></i> Add Group
                            </button>
                        </div>

                        <div class="row" id="userGroupList">
                            @foreach($settings['cms']['allowed_user_groups'] as $index => $group)
                                <div class="col-md-4 mb-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="cms[allowed_user_groups][]" value="{{ $group }}" readonly>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeUserGroup({{ $index }})">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- IP Access Control -->
            <div class="card settings-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-globe"></i> IP Access Control</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Allowed IPs</label>
                                <div class="form-text mb-3">Only these IPs can access the CMS. Leave empty to allow all IPs.</div>

                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="newAllowedIP" placeholder="e.g., 192.168.1.100">
                                    <button type="button" class="btn btn-outline-success" onclick="addAllowedIP()">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>

                                <div id="allowedIPList">
                                    @foreach($settings['cms']['allowed_ips'] as $index => $ip)
                                        <div class="ip-input">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="cms[allowed_ips][]" value="{{ $ip }}" readonly>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeAllowedIP({{ $index }})">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Blocked IPs</label>
                                <div class="form-text mb-3">These IPs are explicitly blocked from accessing the CMS.</div>

                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="newBlockedIP" placeholder="e.g., 192.168.1.50">
                                    <button type="button" class="btn btn-outline-danger" onclick="addBlockedIP()">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>

                                <div id="blockedIPList">
                                    @foreach($settings['cms']['blocked_ips'] as $index => $ip)
                                        <div class="ip-input">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="cms[blocked_ips][]" value="{{ $ip }}" readonly>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeBlockedIP({{ $index }})">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Management -->
            <div class="card settings-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-images"></i> Asset Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Storage Path</label>
                                <input type="text" class="form-control" name="assets[storage_path]"
                                       value="{{ $settings['assets']['storage_path'] }}">
                                <div class="form-text">Path where uploaded assets will be stored</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="thumbnailsEnabled"
                                           name="assets[thumbnails_enabled]" value="1"
                                           {{ $settings['assets']['thumbnails_enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="thumbnailsEnabled">
                                        <strong>Generate Thumbnails</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="optimizationEnabled"
                                           name="assets[optimization_enabled]" value="1"
                                           {{ $settings['assets']['optimization_enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="optimizationEnabled">
                                        <strong>Optimize Images</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="webpGeneration"
                                           name="assets[webp_generation]" value="1"
                                           {{ $settings['assets']['webp_generation'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="webpGeneration">
                                        <strong>Generate WebP</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Management -->
            <div class="card settings-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Content Management</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="backupOnSave"
                                           name="content[backup_on_save]" value="1"
                                           {{ $settings['content']['backup_on_save'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="backupOnSave">
                                        <strong>Backup on Save</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="versionHistory"
                                           name="content[version_history]" value="1"
                                           {{ $settings['content']['version_history'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="versionHistory">
                                        <strong>Version History</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Max Versions</label>
                                <input type="number" class="form-control" name="content[max_versions]"
                                       value="{{ $settings['content']['max_versions'] }}" min="1" max="1000">
                                <div class="form-text">Maximum number of versions to keep per content item</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Auto-save Interval (ms)</label>
                                <input type="number" class="form-control" name="content[auto_save_interval]"
                                       value="{{ $settings['content']['auto_save_interval'] }}" min="5000" max="300000" step="1000">
                                <div class="form-text">Auto-save interval in milliseconds (5000-300000)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mb-5">
                <div class="col-md-12 text-center">
                    <button type="submit" class="btn btn-success btn-save me-3">
                        <span class="btn-text"><i class="fas fa-save"></i> Save Settings</span>
                        <span class="loading"><i class="fas fa-spinner fa-spin"></i> Saving...</span>
                    </button>

                    <button type="button" class="btn btn-warning me-3" onclick="resetSettings()">
                        <i class="fas fa-undo"></i> Reset to Defaults
                    </button>

                    <button type="button" class="btn btn-info" onclick="exportSettings()">
                        <i class="fas fa-download"></i> Export Settings
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Route management
        function addRoute() {
            const input = document.getElementById('newRoute');
            const route = input.value.trim();

            if (!route) return;

            const routeList = document.getElementById('routeList');
            const index = routeList.children.length;

            const routeItem = document.createElement('div');
            routeItem.className = 'route-item';
            routeItem.innerHTML = `
                <span>${route}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRoute(${index})">
                    <i class="fas fa-times"></i>
                </button>
                <input type="hidden" name="cms[excluded_routes][]" value="${route}">
            `;

            routeList.appendChild(routeItem);
            input.value = '';
        }

        function removeRoute(index) {
            const routeList = document.getElementById('routeList');
            if (routeList.children[index]) {
                routeList.children[index].remove();
            }
        }

        function testRoute() {
            const route = document.getElementById('testRoute').value.trim();
            if (!route) return;

            fetch('{{ route("cms.settings.test-route") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ route: route })
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('testResult');
                resultDiv.innerHTML = `
                    <div class="alert ${data.cms_available ? 'alert-success' : 'alert-warning'} alert-dismissible fade show">
                        <i class="fas ${data.cms_available ? 'fa-check' : 'fa-exclamation-triangle'}"></i>
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // User group management
        function addUserGroup() {
            const input = document.getElementById('newUserGroup');
            const group = input.value.trim();

            if (!group) return;

            const groupList = document.getElementById('userGroupList');
            const index = groupList.children.length;

            const groupDiv = document.createElement('div');
            groupDiv.className = 'col-md-4 mb-2';
            groupDiv.innerHTML = `
                <div class="input-group">
                    <input type="text" class="form-control" name="cms[allowed_user_groups][]" value="${group}" readonly>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeUserGroup(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            groupList.appendChild(groupDiv);
            input.value = '';
        }

        function removeUserGroup(index) {
            const groupList = document.getElementById('userGroupList');
            if (groupList.children[index]) {
                groupList.children[index].remove();
            }
        }

        // IP management
        function addAllowedIP() {
            const input = document.getElementById('newAllowedIP');
            const ip = input.value.trim();

            if (!ip || !isValidIP(ip)) {
                alert('Please enter a valid IP address');
                return;
            }

            const ipList = document.getElementById('allowedIPList');

            const ipDiv = document.createElement('div');
            ipDiv.className = 'ip-input';
            ipDiv.innerHTML = `
                <div class="input-group">
                    <input type="text" class="form-control" name="cms[allowed_ips][]" value="${ip}" readonly>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.ip-input').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            ipList.appendChild(ipDiv);
            input.value = '';
        }

        function addBlockedIP() {
            const input = document.getElementById('newBlockedIP');
            const ip = input.value.trim();

            if (!ip || !isValidIP(ip)) {
                alert('Please enter a valid IP address');
                return;
            }

            const ipList = document.getElementById('blockedIPList');

            const ipDiv = document.createElement('div');
            ipDiv.className = 'ip-input';
            ipDiv.innerHTML = `
                <div class="input-group">
                    <input type="text" class="form-control" name="cms[blocked_ips][]" value="${ip}" readonly>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.ip-input').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            ipList.appendChild(ipDiv);
            input.value = '';
        }

        function removeAllowedIP(index) {
            const ipList = document.getElementById('allowedIPList');
            if (ipList.children[index]) {
                ipList.children[index].remove();
            }
        }

        function removeBlockedIP(index) {
            const ipList = document.getElementById('blockedIPList');
            if (ipList.children[index]) {
                ipList.children[index].remove();
            }
        }

        function isValidIP(ip) {
            const regex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            return regex.test(ip);
        }

        // Settings management
        function resetSettings() {
            if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
                window.location.href = '{{ route("cms.settings.reset") }}';
            }
        }

        function exportSettings() {
            fetch('{{ route("cms.settings.show") }}')
                .then(response => response.json())
                .then(data => {
                    const dataStr = JSON.stringify(data.settings, null, 2);
                    const dataBlob = new Blob([dataStr], { type: 'application/json' });
                    const url = URL.createObjectURL(dataBlob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'cms-settings.json';
                    link.click();
                });
        }

        // Form submission
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-save');
            const btnText = btn.querySelector('.btn-text');
            const loading = btn.querySelector('.loading');

            btnText.style.display = 'none';
            loading.style.display = 'inline';
            btn.disabled = true;
        });

        // Enter key handlers
        document.getElementById('newRoute').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addRoute();
            }
        });

        document.getElementById('newUserGroup').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addUserGroup();
            }
        });

        document.getElementById('newAllowedIP').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addAllowedIP();
            }
        });

        document.getElementById('newBlockedIP').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addBlockedIP();
            }
        });

        document.getElementById('testRoute').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                testRoute();
            }
        });
    </script>
</body>
</html>