<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\CMSLogger;

class ContentController extends Controller
{
    protected $fileUpdater;
    protected $logger;

    public function __construct(FileUpdater $fileUpdater, CMSLogger $logger)
    {
        $this->fileUpdater = $fileUpdater;
        $this->logger = $logger;
    }

    /**
     * Update single content
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|string',
            'element_id' => 'required|string',
            'content' => 'required',
            'type' => 'string|in:text,html,blade,link'
        ]);

        // Convert relative path to absolute
        $filePath = base_path($validated['file']);

        // Security check - ensure file is within project
        if (!str_starts_with(realpath($filePath), realpath(base_path()))) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid file path'
            ], 403);
        }

        $result = $this->fileUpdater->updateContent(
            $filePath,
            $validated['element_id'],
            $validated['content'],
            $validated['type'] ?? 'html'
        );

        return response()->json($result);
    }

    /**
     * Update multiple contents
     */
    public function updateBulk(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|string',
            'updates' => 'required|array',
            'updates.*.id' => 'required|string',
            'updates.*.content' => 'required',
            'updates.*.type' => 'string|in:text,html,blade,link'
        ]);

        // Convert relative path to absolute
        $filePath = base_path($validated['file']);

        // Security check
        if (!str_starts_with(realpath($filePath), realpath(base_path()))) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid file path'
            ], 403);
        }

        $result = $this->fileUpdater->updateMultipleContents(
            $filePath,
            $validated['updates']
        );

        return response()->json($result);
    }

    /**
     * Save content from inline editor
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'element_id' => 'required|string',
            'content' => 'required',
            'original_content' => 'nullable|string',
            'type' => 'string|in:text,html,link,heading,image',
            'page_url' => 'required|string',
            'file_hint' => 'nullable|string'
        ]);

        // Try to determine the file from the page URL
        $file = $this->resolveFileFromUrl($validated['page_url'], $validated['file_hint'] ?? null);

        if (!$file) {
            return response()->json([
                'success' => false,
                'error' => 'Could not determine file location'
            ], 400);
        }

        // Handle link content specially
        if ($validated['type'] === 'link' && is_array($validated['content'])) {
            $content = $validated['content'];
        } else {
            $content = $validated['content'];
        }

        $result = $this->fileUpdater->updateContent(
            $file,
            $validated['element_id'],
            $content,
            $this->determineFileType($file),
            $validated['original_content'] ?? null
        );

        // Log the save action
        $this->logger->info('Content saved via inline editor', [
            'element' => $validated['element_id'],
            'type' => $validated['type'],
            'url' => $validated['page_url']
        ]);

        return response()->json($result);
    }

    /**
     * List backups for a file
     */
    public function backups(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|string'
        ]);

        $filePath = base_path($validated['file']);

        // Security check
        if (!str_starts_with(realpath($filePath), realpath(base_path()))) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid file path'
            ], 403);
        }

        $backups = $this->fileUpdater->listBackups($filePath);

        return response()->json([
            'success' => true,
            'backups' => $backups
        ]);
    }

    /**
     * Restore from backup
     */
    public function restore(Request $request)
    {
        $validated = $request->validate([
            'backup_file' => 'required|string',
            'original_file' => 'required|string'
        ]);

        $result = $this->fileUpdater->restoreFromBackup(
            $validated['backup_file'],
            base_path($validated['original_file'])
        );

        return response()->json($result);
    }

    /**
     * Resolve file path from URL
     */
    protected function resolveFileFromUrl($url, $hint = null)
    {
        // Remove hash fragment from URL
        $url = strtok($url, '#');

        // Parse the URL
        $path = parse_url($url, PHP_URL_PATH);
        $path = trim($path, '/');

        // If path is empty, assume it's the home page
        if (empty($path) || $path === '') {
            $path = 'index';
        }

        // Common view paths to check
        $viewPaths = [
            resource_path('views'),
            resource_path('views/pages'),
            resource_path('views/layouts'),
        ];

        // Try to find corresponding view file
        foreach ($viewPaths as $viewPath) {
            // Check for blade files
            $bladeFile = $viewPath . '/' . $path . '.blade.php';
            if (file_exists($bladeFile)) {
                return $bladeFile;
            }

            // Check with replaced slashes
            $bladeFile = $viewPath . '/' . str_replace('/', '.', $path) . '.blade.php';
            if (file_exists($bladeFile)) {
                return $bladeFile;
            }

            // Check for home/index pages
            if ($path === 'index' || empty($path)) {
                $homeFiles = ['home.blade.php', 'index.blade.php', 'welcome.blade.php'];
                foreach ($homeFiles as $homeFile) {
                    $file = $viewPath . '/' . $homeFile;
                    if (file_exists($file)) {
                        return $file;
                    }
                }
            }
        }

        // Check public HTML files
        $publicFile = public_path($path . '.html');
        if (file_exists($publicFile)) {
            return $publicFile;
        }

        $publicFile = public_path($path);
        if (file_exists($publicFile) && is_file($publicFile)) {
            return $publicFile;
        }

        // Use hint if provided
        if ($hint && file_exists(base_path($hint))) {
            return base_path($hint);
        }

        return null;
    }

    /**
     * Determine file type from extension
     */
    protected function determineFileType($file)
    {
        if (str_ends_with($file, '.blade.php')) {
            return 'blade';
        } elseif (str_ends_with($file, '.html') || str_ends_with($file, '.htm')) {
            return 'html';
        } else {
            return 'text';
        }
    }
}