<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use DOMDocument;
use DOMXPath;
use Exception;

class FileUpdater
{
    protected $logger;
    protected $backupPath;

    public function __construct(CMSLogger $logger)
    {
        $this->logger = $logger;
        $this->backupPath = storage_path('cms/backups');
    }

    /**
     * Update content in a file
     */
    public function updateContent($filePath, $elementId, $newContent, $type = 'text')
    {
        try {
            // Validate file exists
            if (!File::exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            // Create backup
            $backupFile = $this->createBackup($filePath);

            // Read file content
            $content = File::get($filePath);

            // Update content based on type
            if ($type === 'blade') {
                $updatedContent = $this->updateBladeContent($content, $elementId, $newContent);
            } else {
                $updatedContent = $this->updateHtmlContent($content, $elementId, $newContent);
            }

            // Write updated content
            File::put($filePath, $updatedContent);

            // Clear view cache if it's a blade file
            if (str_ends_with($filePath, '.blade.php')) {
                $this->clearViewCache($filePath);
            }

            // Log the update
            $this->logger->logContentChange(
                $filePath,
                $elementId,
                $this->extractElementContent($content, $elementId),
                $newContent
            );

            return [
                'success' => true,
                'backup' => $backupFile,
                'message' => 'Content updated successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Failed to update content', [
                'file' => $filePath,
                'element' => $elementId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update multiple contents in a file
     */
    public function updateMultipleContents($filePath, array $updates)
    {
        try {
            // Create single backup before multiple updates
            $backupFile = $this->createBackup($filePath);

            $content = File::get($filePath);
            $updatedContent = $content;

            foreach ($updates as $update) {
                $elementId = $update['id'];
                $newContent = $update['content'];
                $type = $update['type'] ?? 'text';

                if ($type === 'blade') {
                    $updatedContent = $this->updateBladeContent($updatedContent, $elementId, $newContent);
                } else {
                    $updatedContent = $this->updateHtmlContent($updatedContent, $elementId, $newContent);
                }

                $this->logger->logContentChange(
                    $filePath,
                    $elementId,
                    $this->extractElementContent($content, $elementId),
                    $newContent
                );
            }

            File::put($filePath, $updatedContent);

            // Clear view cache if it's a blade file
            if (str_ends_with($filePath, '.blade.php')) {
                $this->clearViewCache($filePath);
            }

            return [
                'success' => true,
                'backup' => $backupFile,
                'updates' => count($updates),
                'message' => 'All contents updated successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Failed to update multiple contents', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create backup of file
     */
    protected function createBackup($filePath)
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $relativePath = str_replace(base_path() . '/', '', $filePath);
        $backupDir = $this->backupPath . '/' . $timestamp . '/' . dirname($relativePath);

        // Create backup directory
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $backupFile = $backupDir . '/' . basename($filePath);

        // Copy file to backup location
        File::copy($filePath, $backupFile);

        $this->logger->logBackup($filePath, $backupFile);

        return $backupFile;
    }

    /**
     * Update HTML content using DOM
     */
    protected function updateHtmlContent($html, $elementId, $newContent)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);

        // Find element by data-cms-id
        $elements = $xpath->query("//*[@data-cms-id='{$elementId}']");

        if ($elements->length > 0) {
            $element = $elements->item(0);

            // Preserve attributes
            $tagName = $element->tagName;

            // For links, handle specially
            if ($tagName === 'a' && is_array($newContent)) {
                if (isset($newContent['text'])) {
                    $element->nodeValue = $newContent['text'];
                }
                if (isset($newContent['href'])) {
                    $element->setAttribute('href', $newContent['href']);
                }
                if (isset($newContent['target'])) {
                    if ($newContent['target'] === '_blank') {
                        $element->setAttribute('target', '_blank');
                        $element->setAttribute('rel', 'noopener noreferrer');
                    } else {
                        $element->removeAttribute('target');
                        $element->removeAttribute('rel');
                    }
                }
            } else {
                // Update inner HTML while preserving the element
                $this->setInnerHTML($element, $newContent);
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Update Blade template content
     */
    protected function updateBladeContent($content, $elementId, $newContent)
    {
        // For Blade files, we need to be more careful
        // Look for the element with data-cms-id in the rendered output pattern

        // This is a simplified version - in production you'd want more robust parsing
        $pattern = '/data-cms-id=["\']' . preg_quote($elementId, '/') . '["\'][^>]*>.*?<\/\w+>/s';

        if (preg_match($pattern, $content, $matches)) {
            $oldElement = $matches[0];

            // Extract the tag and attributes
            if (preg_match('/^([^>]+>)/', $oldElement, $tagMatch)) {
                $openTag = $tagMatch[1];
                $tagName = '';
                if (preg_match('/<(\w+)/', $openTag, $nameMatch)) {
                    $tagName = $nameMatch[1];
                }

                // Build new element with updated content
                $newElement = $openTag . $newContent . '</' . $tagName . '>';
                $content = str_replace($oldElement, $newElement, $content);
            }
        }

        return $content;
    }

    /**
     * Set inner HTML of a DOM element
     */
    protected function setInnerHTML($element, $html)
    {
        // Remove all child nodes
        while ($element->hasChildNodes()) {
            $element->removeChild($element->firstChild);
        }

        // Add new content
        $tmpDoc = new DOMDocument();
        libxml_use_internal_errors(true);
        $tmpDoc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $tmpDiv = $tmpDoc->getElementsByTagName('div')->item(0);

        foreach ($tmpDiv->childNodes as $node) {
            $importedNode = $element->ownerDocument->importNode($node, true);
            $element->appendChild($importedNode);
        }
    }

    /**
     * Extract element content from HTML
     */
    protected function extractElementContent($html, $elementId)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);
        $elements = $xpath->query("//*[@data-cms-id='{$elementId}']");

        if ($elements->length > 0) {
            $element = $elements->item(0);
            return $element->nodeValue;
        }

        return '';
    }

    /**
     * Restore file from backup
     */
    public function restoreFromBackup($backupFile, $originalFile)
    {
        try {
            if (!File::exists($backupFile)) {
                throw new Exception("Backup file not found: {$backupFile}");
            }

            File::copy($backupFile, $originalFile);

            $this->logger->info('File restored from backup', [
                'backup' => $backupFile,
                'original' => $originalFile
            ]);

            return [
                'success' => true,
                'message' => 'File restored successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Failed to restore from backup', [
                'backup' => $backupFile,
                'original' => $originalFile,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List available backups for a file
     */
    public function listBackups($filePath)
    {
        $relativePath = str_replace(base_path() . '/', '', $filePath);
        $fileName = basename($filePath);
        $backups = [];

        if (File::exists($this->backupPath)) {
            $timestamps = File::directories($this->backupPath);

            foreach ($timestamps as $timestampDir) {
                $backupFile = $timestampDir . '/' . $relativePath;

                if (File::exists($backupFile)) {
                    $timestamp = basename($timestampDir);
                    $backups[] = [
                        'timestamp' => $timestamp,
                        'date' => \Carbon\Carbon::createFromFormat('Y-m-d_H-i-s', $timestamp)->format('Y-m-d H:i:s'),
                        'file' => $backupFile,
                        'size' => File::size($backupFile)
                    ];
                }
            }
        }

        return array_reverse($backups);
    }

    /**
     * Clear Laravel view cache for a specific file
     */
    protected function clearViewCache($filePath)
    {
        try {
            // Get the view name from the file path
            $viewsPath = resource_path('views');
            $relativePath = str_replace($viewsPath . '/', '', $filePath);
            $viewName = str_replace('.blade.php', '', $relativePath);
            $viewName = str_replace('/', '.', $viewName);

            // Clear compiled view cache
            $compiledPath = storage_path('framework/views');
            $compiledFiles = File::glob($compiledPath . '/*.php');

            foreach ($compiledFiles as $compiledFile) {
                // Read the compiled file to check if it's for our view
                $content = File::get($compiledFile);
                if (strpos($content, $relativePath) !== false || strpos($content, $viewName) !== false) {
                    File::delete($compiledFile);
                    $this->logger->info('Cleared compiled view cache', [
                        'view' => $viewName,
                        'compiled' => $compiledFile
                    ]);
                }
            }

            // Also try to clear all view cache if available
            if (function_exists('artisan')) {
                \Artisan::call('view:clear');
            }

        } catch (Exception $e) {
            // Log but don't fail if cache clearing fails
            $this->logger->warning('Could not clear view cache', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }
}