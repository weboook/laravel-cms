<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\ContentScanner;
use Webook\LaravelCMS\Services\PreviewService;
use Webook\LaravelCMS\Models\EditSession;
use Exception;

/**
 * Editor Controller
 *
 * Handles the CMS editor interface, including content preview,
 * scanning for editable elements, and editor configuration.
 *
 * @package Webook\LaravelCMS\Http\Controllers
 */
class EditorController extends Controller
{
    protected FileUpdater $fileUpdater;
    protected ContentScanner $contentScanner;
    protected PreviewService $previewService;

    public function __construct(
        FileUpdater $fileUpdater,
        ContentScanner $contentScanner,
        PreviewService $previewService
    ) {
        $this->fileUpdater = $fileUpdater;
        $this->contentScanner = $contentScanner;
        $this->previewService = $previewService;

        $this->middleware(['auth', 'can:access-cms-editor']);
    }

    /**
     * Display the main editor interface.
     *
     * Loads the editor with all necessary configuration, user preferences,
     * and initializes the editing state for the current user.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'url' => 'nullable|url|max:2048',
                'locale' => 'nullable|string|in:' . implode(',', $this->getAvailableLocaleCodes()),
                'mode' => 'nullable|string|in:edit,preview,scan',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Check permissions
            if (!Gate::allows('edit-content')) {
                abort(403, 'Insufficient permissions to access editor');
            }

            // Get current user and session
            $user = $request->user();
            $targetUrl = $request->get('url', request()->getSchemeAndHttpHost());

            // Detect and validate locale
            $locale = $this->detectLocale($request);
            if (!in_array($locale, $this->getAvailableLocaleCodes())) {
                $locale = config('app.locale', 'en');
            }

            // Load user preferences
            $preferences = $this->getUserPreferences($user);

            // Initialize editor state
            $editorState = $this->initializeEditorState($user, $targetUrl, $locale);

            // Get editor configuration
            $config = $this->getEditorConfig($user, $preferences);

            // Load recent edits for quick access
            $recentEdits = $this->getRecentEdits($user);

            // Get toolbar configuration
            $toolbar = $this->getToolbarConfig($user);

            Log::info('Editor loaded', [
                'user_id' => $user->id,
                'target_url' => $targetUrl,
                'locale' => $locale,
            ]);

            return view('cms::editor', [
                'targetUrl' => $targetUrl,
                'locale' => $locale,
                'locales' => $this->getAvailableLocales(),
                'user' => $user,
                'permissions' => $this->getUserPermissions($user),
                'config' => $config,
                'recentEdits' => $recentEdits,
                'toolbar' => $toolbar,
                'editorState' => $editorState,
                'preferences' => $preferences,
                'csrfToken' => csrf_token(),
            ]);

        } catch (Exception $e) {
            Log::error('Editor index failed', [
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
                'url' => $request->fullUrl(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to load editor: ' . $e->getMessage());
        }
    }

    /**
     * Preview content with editable markers injected.
     *
     * Fetches the target page content, injects CMS editing markers,
     * and returns the modified content for in-place editing.
     *
     * @param Request $request
     * @param string $url The URL to preview
     * @return Response|JsonResponse|BinaryFileResponse
     *
     * @throws ValidationException
     */
    public function preview(Request $request, string $url)
    {
        try {
            // Validate request
            $validator = Validator::make([
                'url' => $url,
                ...$request->all()
            ], [
                'url' => 'required|url|max:2048',
                'locale' => 'nullable|string|in:' . implode(',', $this->getAvailableLocaleCodes()),
                'mode' => 'nullable|string|in:edit,view',
                'inject_assets' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid request parameters',
                    'details' => $validator->errors(),
                ], 400);
            }

            // Check permissions
            if (!Gate::allows('preview-content')) {
                return response()->json([
                    'error' => 'Insufficient permissions'
                ], 403);
            }

            // Handle authentication for target URL
            $this->authenticateForPreview($request, $url);

            // Get locale
            $locale = $request->get('locale', $this->detectLocale($request));

            // Check cache first
            $cacheKey = "preview:" . md5($url . $locale . $request->user()->id);
            $cached = Cache::get($cacheKey);

            if ($cached && !$request->get('nocache')) {
                return $this->formatPreviewResponse($cached, $request);
            }

            // Fetch target page content
            $content = $this->previewService->fetchContent($url, [
                'locale' => $locale,
                'user' => $request->user(),
                'headers' => $this->getPreviewHeaders($request),
            ]);

            // Inject editable markers if in edit mode
            if ($request->get('mode') !== 'view') {
                $content = $this->injectEditableMarkers($content, $url, $locale);
            }

            // Add CMS assets if requested
            if ($request->get('inject_assets', true)) {
                $content = $this->addCMSAssets($content, $request);
            }

            // Cache the result
            Cache::put($cacheKey, $content, now()->addMinutes(5));

            Log::info('Content previewed', [
                'url' => $url,
                'locale' => $locale,
                'user_id' => $request->user()->id,
            ]);

            return $this->formatPreviewResponse($content, $request);

        } catch (Exception $e) {
            Log::error('Preview failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return $this->handlePreviewError($e, $request);
        }
    }

    /**
     * Scan URL for editable content elements.
     *
     * Analyzes the target URL and returns a structured list of
     * all editable elements with their metadata and permissions.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @throws ValidationException
     */
    public function scan(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'url' => 'required|url|max:2048',
                'locale' => 'nullable|string|in:' . implode(',', $this->getAvailableLocaleCodes()),
                'depth' => 'nullable|integer|min:1|max:5',
                'types' => 'nullable|array',
                'types.*' => 'string|in:text,image,link,component',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Check permissions
            if (!Gate::allows('scan-content')) {
                return response()->json([
                    'error' => 'Insufficient permissions'
                ], 403);
            }

            $url = $request->get('url');
            $locale = $request->get('locale', $this->detectLocale($request));
            $depth = $request->get('depth', 1);
            $types = $request->get('types', ['text', 'image', 'link']);

            // Check cache
            $cacheKey = "scan:" . md5($url . $locale . serialize($types) . $depth);
            $cached = Cache::get($cacheKey);

            if ($cached && !$request->get('nocache')) {
                return response()->json($cached);
            }

            // Perform content scan
            $scanResults = $this->contentScanner->scan($url, [
                'locale' => $locale,
                'depth' => $depth,
                'types' => $types,
                'user' => $request->user(),
            ]);

            // Transform results to required format
            $elements = [];
            $stats = [
                'total' => 0,
                'translated' => 0,
                'images' => 0,
                'links' => 0,
            ];

            foreach ($scanResults['elements'] as $element) {
                $transformedElement = [
                    'id' => $element['id'],
                    'type' => $element['type'],
                    'content' => $element['content'],
                    'metadata' => [
                        'file' => $element['file'] ?? null,
                        'line' => $element['line'] ?? null,
                        'key' => $element['translation_key'] ?? null,
                        'editable' => $this->isElementEditable($element, $request->user()),
                        'permissions' => $this->getElementPermissions($element, $request->user()),
                        'selector' => $element['selector'] ?? null,
                        'context' => $element['context'] ?? [],
                    ],
                ];

                $elements[] = $transformedElement;

                // Update stats
                $stats['total']++;
                if ($element['type'] === 'image') $stats['images']++;
                if ($element['type'] === 'link') $stats['links']++;
                if (!empty($element['translation_key'])) $stats['translated']++;
            }

            $response = [
                'elements' => $elements,
                'stats' => $stats,
                'meta' => [
                    'url' => $url,
                    'locale' => $locale,
                    'scanned_at' => now()->toISOString(),
                    'scan_depth' => $depth,
                    'scan_types' => $types,
                ],
            ];

            // Cache results
            Cache::put($cacheKey, $response, now()->addMinutes(10));

            Log::info('Content scanned', [
                'url' => $url,
                'locale' => $locale,
                'elements_found' => count($elements),
                'user_id' => $request->user()->id,
            ]);

            return response()->json($response);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Content scan failed', [
                'url' => $request->get('url'),
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Scan failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get editor configuration for the current user.
     *
     * Returns configuration settings, feature flags, and
     * user-specific editor preferences.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getConfig(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $config = $this->getEditorConfig($user);

            return response()->json([
                'config' => $config,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'permissions' => $this->getUserPermissions($user),
                ],
                'meta' => [
                    'version' => config('cms.version', '1.0.0'),
                    'locale' => $this->detectLocale($request),
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Config retrieval failed', [
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Failed to retrieve configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get toolbar configuration for the current user.
     *
     * Returns available toolbar buttons, shortcuts, and
     * user-customized toolbar layout.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getToolbar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $toolbar = $this->getToolbarConfig($user);

            return response()->json([
                'toolbar' => $toolbar,
                'shortcuts' => $this->getKeyboardShortcuts(),
                'customizations' => $this->getUserToolbarCustomizations($user),
            ]);

        } catch (Exception $e) {
            Log::error('Toolbar config retrieval failed', [
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => 'Failed to retrieve toolbar configuration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detect the current locale from request.
     */
    protected function detectLocale(Request $request): string
    {
        // Priority: URL parameter > user preference > session > header > default
        return $request->get('locale')
            ?? optional($request->user())->preferred_locale
            ?? session('locale')
            ?? $request->getPreferredLanguage($this->getAvailableLocaleCodes())
            ?? config('app.locale', 'en');
    }

    /**
     * Get available locales with full information.
     */
    protected function getAvailableLocales(): array
    {
        return config('cms.locales', [
            'en' => ['name' => 'English', 'flag' => '🇺🇸', 'rtl' => false],
            'es' => ['name' => 'Español', 'flag' => '🇪🇸', 'rtl' => false],
            'fr' => ['name' => 'Français', 'flag' => '🇫🇷', 'rtl' => false],
        ]);
    }

    /**
     * Get available locale codes.
     */
    protected function getAvailableLocaleCodes(): array
    {
        return array_keys($this->getAvailableLocales());
    }

    /**
     * Get user permissions for CMS operations.
     */
    protected function getUserPermissions($user): array
    {
        if (!$user) {
            return [];
        }

        return [
            'edit' => Gate::forUser($user)->allows('edit-content'),
            'translate' => Gate::forUser($user)->allows('translate-content'),
            'publish' => Gate::forUser($user)->allows('publish-content'),
            'delete' => Gate::forUser($user)->allows('delete-content'),
            'manage_media' => Gate::forUser($user)->allows('manage-media'),
            'manage_users' => Gate::forUser($user)->allows('manage-users'),
            'access_settings' => Gate::forUser($user)->allows('access-settings'),
        ];
    }

    /**
     * Get user preferences for the editor.
     */
    protected function getUserPreferences($user): array
    {
        $defaults = [
            'theme' => 'light',
            'auto_save' => true,
            'spell_check' => true,
            'show_minimap' => false,
            'font_size' => 14,
            'tab_size' => 2,
            'word_wrap' => true,
            'line_numbers' => true,
            'highlight_active_line' => true,
        ];

        if (!$user || !method_exists($user, 'getPreference')) {
            return $defaults;
        }

        return array_merge($defaults, $user->getPreference('editor', []));
    }

    /**
     * Initialize editor state for the user session.
     */
    protected function initializeEditorState($user, string $targetUrl, string $locale): array
    {
        return [
            'session_id' => session()->getId(),
            'target_url' => $targetUrl,
            'locale' => $locale,
            'edit_mode' => false,
            'unsaved_changes' => false,
            'last_save' => null,
            'active_element' => null,
            'selection' => null,
            'history' => [],
            'clipboard' => null,
        ];
    }

    /**
     * Get editor configuration.
     */
    protected function getEditorConfig($user, array $preferences = []): array
    {
        return [
            'features' => [
                'auto_save' => $preferences['auto_save'] ?? true,
                'spell_check' => $preferences['spell_check'] ?? true,
                'live_preview' => true,
                'version_control' => true,
                'collaborative_editing' => false,
                'ai_suggestions' => config('cms.features.ai_enabled', false),
            ],
            'limits' => [
                'max_file_size' => config('cms.limits.max_file_size', 10485760), // 10MB
                'max_image_size' => config('cms.limits.max_image_size', 5242880), // 5MB
                'auto_save_interval' => 30, // seconds
                'session_timeout' => 3600, // 1 hour
            ],
            'editor' => [
                'theme' => $preferences['theme'] ?? 'light',
                'font_size' => $preferences['font_size'] ?? 14,
                'tab_size' => $preferences['tab_size'] ?? 2,
                'word_wrap' => $preferences['word_wrap'] ?? true,
                'line_numbers' => $preferences['line_numbers'] ?? true,
                'minimap' => $preferences['show_minimap'] ?? false,
            ],
            'upload' => [
                'allowed_types' => config('cms.upload.allowed_types', ['jpg', 'png', 'gif', 'svg', 'pdf']),
                'max_files' => config('cms.upload.max_files', 10),
                'chunk_size' => config('cms.upload.chunk_size', 1048576), // 1MB
            ],
        ];
    }

    /**
     * Get recent edits for the user.
     */
    protected function getRecentEdits($user): array
    {
        if (!$user) {
            return [];
        }

        // This would typically query a database table
        return Cache::remember("recent_edits:{$user->id}", 300, function () use ($user) {
            return [
                [
                    'id' => '1',
                    'file' => 'resources/views/welcome.blade.php',
                    'line' => 42,
                    'description' => 'Updated welcome message',
                    'edited_at' => now()->subMinutes(15)->toISOString(),
                ],
                [
                    'id' => '2',
                    'file' => 'resources/lang/en/messages.php',
                    'line' => 12,
                    'description' => 'Added new translation key',
                    'edited_at' => now()->subHours(2)->toISOString(),
                ],
            ];
        });
    }

    /**
     * Get toolbar configuration for the user.
     */
    protected function getToolbarConfig($user): array
    {
        $permissions = $this->getUserPermissions($user);

        return [
            'groups' => [
                'file' => [
                    'label' => 'File',
                    'buttons' => array_filter([
                        'save' => ['icon' => 'save', 'label' => 'Save', 'shortcut' => 'Ctrl+S'],
                        'save_all' => ['icon' => 'save-all', 'label' => 'Save All', 'shortcut' => 'Ctrl+Shift+S'],
                        'revert' => ['icon' => 'undo', 'label' => 'Revert Changes'],
                    ]),
                ],
                'edit' => [
                    'label' => 'Edit',
                    'buttons' => array_filter([
                        'cut' => ['icon' => 'cut', 'label' => 'Cut', 'shortcut' => 'Ctrl+X'],
                        'copy' => ['icon' => 'copy', 'label' => 'Copy', 'shortcut' => 'Ctrl+C'],
                        'paste' => ['icon' => 'paste', 'label' => 'Paste', 'shortcut' => 'Ctrl+V'],
                        'find' => ['icon' => 'search', 'label' => 'Find', 'shortcut' => 'Ctrl+F'],
                        'replace' => ['icon' => 'replace', 'label' => 'Replace', 'shortcut' => 'Ctrl+H'],
                    ]),
                ],
                'view' => [
                    'label' => 'View',
                    'buttons' => [
                        'preview' => ['icon' => 'eye', 'label' => 'Preview', 'shortcut' => 'Ctrl+P'],
                        'split_view' => ['icon' => 'split', 'label' => 'Split View'],
                        'full_screen' => ['icon' => 'maximize', 'label' => 'Full Screen', 'shortcut' => 'F11'],
                    ],
                ],
                'tools' => [
                    'label' => 'Tools',
                    'buttons' => array_filter([
                        'scan' => ['icon' => 'scan', 'label' => 'Scan Content'],
                        'translate' => $permissions['translate'] ? ['icon' => 'globe', 'label' => 'Translate'] : null,
                        'media' => $permissions['manage_media'] ? ['icon' => 'image', 'label' => 'Media Library'] : null,
                        'settings' => $permissions['access_settings'] ? ['icon' => 'settings', 'label' => 'Settings'] : null,
                    ]),
                ],
            ],
            'layout' => 'horizontal', // horizontal, vertical, custom
            'size' => 'medium', // small, medium, large
            'position' => 'top', // top, bottom, left, right
        ];
    }

    /**
     * Get keyboard shortcuts configuration.
     */
    protected function getKeyboardShortcuts(): array
    {
        return [
            'global' => [
                'save' => 'Ctrl+S',
                'save_all' => 'Ctrl+Shift+S',
                'preview' => 'Ctrl+P',
                'find' => 'Ctrl+F',
                'replace' => 'Ctrl+H',
                'full_screen' => 'F11',
                'help' => 'F1',
                'close_panel' => 'Escape',
            ],
            'editor' => [
                'comment_line' => 'Ctrl+/',
                'duplicate_line' => 'Ctrl+D',
                'move_line_up' => 'Alt+Up',
                'move_line_down' => 'Alt+Down',
                'select_all' => 'Ctrl+A',
                'undo' => 'Ctrl+Z',
                'redo' => 'Ctrl+Y',
            ],
            'navigation' => [
                'goto_line' => 'Ctrl+G',
                'next_tab' => 'Ctrl+Tab',
                'prev_tab' => 'Ctrl+Shift+Tab',
                'close_tab' => 'Ctrl+W',
            ],
        ];
    }

    /**
     * Get user toolbar customizations.
     */
    protected function getUserToolbarCustomizations($user): array
    {
        if (!$user || !method_exists($user, 'getPreference')) {
            return [];
        }

        return $user->getPreference('toolbar_customizations', []);
    }

    /**
     * Handle authentication for preview requests.
     */
    protected function authenticateForPreview(Request $request, string $url): void
    {
        // Add preview authentication headers or session data
        // This allows the preview to access protected content
        session(['cms_preview_mode' => true]);
    }

    /**
     * Get headers for preview requests.
     */
    protected function getPreviewHeaders(Request $request): array
    {
        return [
            'User-Agent' => 'CMS-Preview/1.0',
            'X-CMS-Preview' => 'true',
            'X-CMS-User' => $request->user()->id,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];
    }

    /**
     * Inject editable markers into content.
     */
    protected function injectEditableMarkers(string $content, string $url, string $locale): string
    {
        // This would inject CMS editing markers into the content
        // Implementation depends on your specific CMS requirements
        return $this->previewService->injectEditingMarkers($content, [
            'url' => $url,
            'locale' => $locale,
            'edit_mode' => true,
        ]);
    }

    /**
     * Add CMS assets to the content.
     */
    protected function addCMSAssets(string $content, Request $request): string
    {
        $assets = [
            'css' => [
                asset('cms/css/editor.css'),
                asset('cms/css/preview.css'),
            ],
            'js' => [
                asset('cms/js/editor.js'),
                asset('cms/js/preview.js'),
            ],
        ];

        // Inject CSS into head
        $cssLinks = '';
        foreach ($assets['css'] as $css) {
            $cssLinks .= "<link rel=\"stylesheet\" href=\"{$css}\">\n";
        }

        // Inject JS before closing body
        $jsScripts = '';
        foreach ($assets['js'] as $js) {
            $jsScripts .= "<script src=\"{$js}\"></script>\n";
        }

        // Add CSRF token and configuration
        $jsScripts .= "<script>\n";
        $jsScripts .= "window.cms = window.cms || {};\n";
        $jsScripts .= "window.cms.token = '" . csrf_token() . "';\n";
        $jsScripts .= "window.cms.user = " . json_encode($request->user()->only(['id', 'name'])) . ";\n";
        $jsScripts .= "</script>\n";

        $content = str_replace('</head>', $cssLinks . '</head>', $content);
        $content = str_replace('</body>', $jsScripts . '</body>', $content);

        return $content;
    }

    /**
     * Format preview response based on content type.
     */
    protected function formatPreviewResponse($content, Request $request)
    {
        $format = $request->get('format', 'html');

        switch ($format) {
            case 'json':
                return response()->json([
                    'content' => $content,
                    'meta' => [
                        'generated_at' => now()->toISOString(),
                        'user_id' => $request->user()->id,
                    ],
                ]);

            case 'raw':
                return response($content, 200, [
                    'Content-Type' => 'text/plain',
                ]);

            default:
                return response($content, 200, [
                    'Content-Type' => 'text/html; charset=utf-8',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'X-CMS-Preview' => 'true',
                ]);
        }
    }

    /**
     * Handle preview errors gracefully.
     */
    protected function handlePreviewError(Exception $e, Request $request)
    {
        $format = $request->get('format', 'html');

        if ($format === 'json') {
            return response()->json([
                'error' => 'Preview failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->view('cms::errors.preview', [
            'error' => $e->getMessage(),
            'url' => $request->get('url'),
        ], 500);
    }

    /**
     * Check if an element is editable by the user.
     */
    protected function isElementEditable(array $element, $user): bool
    {
        if (!$user) {
            return false;
        }

        // Check file permissions
        if (isset($element['file']) && !$this->canEditFile($element['file'], $user)) {
            return false;
        }

        // Check element type permissions
        $requiredPermission = match($element['type']) {
            'text' => 'edit-content',
            'image' => 'manage-media',
            'link' => 'edit-content',
            'component' => 'edit-components',
            default => 'edit-content',
        };

        return Gate::forUser($user)->allows($requiredPermission);
    }

    /**
     * Get permissions for a specific element.
     */
    protected function getElementPermissions(array $element, $user): array
    {
        if (!$user) {
            return [];
        }

        $permissions = [];

        if ($this->isElementEditable($element, $user)) {
            $permissions[] = 'edit';
        }

        if (Gate::forUser($user)->allows('translate-content')) {
            $permissions[] = 'translate';
        }

        if (Gate::forUser($user)->allows('delete-content')) {
            $permissions[] = 'delete';
        }

        return $permissions;
    }

    /**
     * Check if user can edit a specific file.
     */
    protected function canEditFile(string $file, $user): bool
    {
        // Implement file-specific permission logic
        $restrictedPaths = config('cms.security.restricted_paths', [
            'config/',
            '.env',
            'vendor/',
        ]);

        foreach ($restrictedPaths as $path) {
            if (str_starts_with($file, $path)) {
                return Gate::forUser($user)->allows('edit-system-files');
            }
        }

        return Gate::forUser($user)->allows('edit-content');
    }
}