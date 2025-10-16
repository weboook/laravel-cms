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
    public function updateContent($filePath, $elementId, $newContent, $type = 'text', $originalContent = null)
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
                $updatedContent = $this->updateBladeContent($content, $elementId, $newContent, $originalContent);
            } else {
                $updatedContent = $this->updateHtmlContent($content, $elementId, $newContent, $originalContent);
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
    protected function updateHtmlContent($html, $elementId, $newContent, $originalContent = null)
    {
        // The data-cms-id doesn't exist in source files, it's injected at runtime
        // We need to use the original content that was stored in data-cms-original
        // For now, we'll use a content-based search

        // Extract the original content from the element ID
        // The ID is generated from content, so we need to find by content

        // First check if this is a Blade file - if so, we need special handling
        $isBladeFile = strpos($html, '@extends') !== false || strpos($html, '@section') !== false;

        if ($isBladeFile) {
            // For Blade files, we'll need to be more careful
            // Try to find the content and replace it
            return $this->updateBladeContent($html, $elementId, $newContent, $originalContent);
        }

        // For regular HTML, look for the element with data-cms-id
        $pattern = '/(data-cms-id=["\']' . preg_quote($elementId, '/') . '["\'][^>]*>)(.*?)(<\/[^>]+>)/s';

        if (preg_match($pattern, $html, $matches)) {
            $openTag = $matches[1];
            $oldContent = $matches[2];
            $closeTag = $matches[3];

            // Build the replacement
            $replacement = $openTag . $newContent . $closeTag;

            // Replace in the HTML
            $html = str_replace($matches[0], $replacement, $html);

            $this->logger->info('Updated content using regex', [
                'element_id' => $elementId,
                'old_content' => substr($oldContent, 0, 50),
                'new_content' => substr($newContent, 0, 50)
            ]);

            return $html;
        }

        // Fallback to DOM approach if regex doesn't work
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);

        // Find element by data-cms-id
        $elements = $xpath->query("//*[@data-cms-id='{$elementId}']");

        $this->logger->info('DOM search for element', [
            'element_id' => $elementId,
            'found_count' => $elements->length
        ]);

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
    protected function updateBladeContent($content, $elementId, $newContent, $originalContent = null)
    {
        // Check if this is a component file with source markers
        $hasSourceMarkers = strpos($content, '@cmsSourceStart') !== false ||
                           strpos($content, '@cmsSourceEnd') !== false;

        if ($hasSourceMarkers) {
            $this->logger->info('Updating component content with source markers', [
                'element_id' => $elementId,
                'has_markers' => true
            ]);
        }

        // Handle image updates specially
        if (is_array($newContent) && isset($newContent['type']) && $newContent['type'] === 'image') {
            // For images, we need to find and replace the src attribute
            if (isset($newContent['src'])) {
                // First, try to find by data-cms-id attribute (most reliable)
                $pattern = '/<img[^>]*data-cms-id=["\']' . preg_quote($elementId, '/') . '["\'][^>]*>/i';
                if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $imgTag = $matches[0][0];
                    $imgPos = $matches[0][1];

                    // Update src
                    $newImgTag = preg_replace(
                        '/src=["\'][^"\']*["\']/',
                        'src="' . $newContent['src'] . '"',
                        $imgTag
                    );

                    // Update alt if provided
                    if (isset($newContent['alt'])) {
                        if (preg_match('/alt=["\'][^"\']*["\']/', $newImgTag)) {
                            $newImgTag = preg_replace(
                                '/alt=["\'][^"\']*["\']/',
                                'alt="' . $newContent['alt'] . '"',
                                $newImgTag
                            );
                        } else {
                            // Add alt attribute if it doesn't exist
                            $newImgTag = preg_replace(
                                '/<img/',
                                '<img alt="' . $newContent['alt'] . '"',
                                $newImgTag
                            );
                        }
                    }

                    $content = substr_replace($content, $newImgTag, $imgPos, strlen($imgTag));

                    $this->logger->info('Updated image by element ID in Blade file', [
                        'element_id' => $elementId,
                        'new_src' => $newContent['src']
                    ]);

                    return $content;
                }

                // If data-cms-id not in source, but we have it from the element
                // Try to find the image by matching the filename or path pattern
                if (str_starts_with($elementId, 'img-')) {
                    // This is an auto-generated ID, we need to be smarter about finding it

                    // Extract filename from the original content URL
                    $filename = '';
                    if ($originalContent && !empty($originalContent)) {
                        $parsedUrl = parse_url($originalContent);
                        $path = $parsedUrl['path'] ?? '';
                        $filename = basename($path);
                    }

                    // Try multiple patterns to find the image
                    $foundMatch = false;
                    $imgTag = '';
                    $imgPos = 0;

                    // Pattern 1: Look for image with exact filename in asset() helper
                    if ($filename && !$foundMatch) {
                        // Match {{ asset('...filename...') }} or {!! asset('...filename...') !!}
                        $pattern = '/<img[^>]*src=["\']\{\{[^}]*asset\([^)]*' . preg_quote($filename, '/') . '[^)]*\)[^}]*\}\}["\'][^>]*>/i';
                        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                            $imgTag = $matches[0][0];
                            $imgPos = $matches[0][1];
                            $foundMatch = true;
                            $this->logger->info('Found image by asset() helper pattern', [
                                'pattern' => 'asset() with filename',
                                'filename' => $filename
                            ]);
                        }
                    }

                    // Pattern 2: Look for image with exact filename in url() helper
                    if ($filename && !$foundMatch) {
                        $pattern = '/<img[^>]*src=["\']\{\{[^}]*url\([^)]*' . preg_quote($filename, '/') . '[^)]*\)[^}]*\}\}["\'][^>]*>/i';
                        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                            $imgTag = $matches[0][0];
                            $imgPos = $matches[0][1];
                            $foundMatch = true;
                            $this->logger->info('Found image by url() helper pattern', [
                                'pattern' => 'url() with filename'
                            ]);
                        }
                    }

                    // Pattern 3: Look for image with partial path match (e.g., 'images/innovation.jpg')
                    if (!$foundMatch && $originalContent) {
                        // Extract relative path like 'images/innovation.jpg'
                        $relativePath = preg_replace('/^.*\/(images\/[^\/]+)$/', '$1', $originalContent);
                        if ($relativePath !== $originalContent) {
                            $pattern = '/<img[^>]*src=[^>]*' . preg_quote($relativePath, '/') . '[^>]*>/i';
                            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                                $imgTag = $matches[0][0];
                                $imgPos = $matches[0][1];
                                $foundMatch = true;
                                $this->logger->info('Found image by relative path pattern', [
                                    'pattern' => 'relative path',
                                    'path' => $relativePath
                                ]);
                            }
                        }
                    }

                    // Pattern 4: Find ANY img tag with similar class/alt attributes
                    if (!$foundMatch && $filename) {
                        // Try to find by class or alt that might contain the filename
                        $filenameBase = pathinfo($filename, PATHINFO_FILENAME);
                        $pattern = '/<img[^>]*(alt|class)[^>]*' . preg_quote($filenameBase, '/') . '[^>]*>/i';
                        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                            $imgTag = $matches[0][0];
                            $imgPos = $matches[0][1];
                            $foundMatch = true;
                            $this->logger->info('Found image by alt/class pattern', [
                                'pattern' => 'alt/class with filename base'
                            ]);
                        }
                    }

                    // If we found a match, update it
                    if ($foundMatch && $imgTag) {
                        // Store original length before modification
                        $originalImgTagLength = strlen($imgTag);

                        // Add data-cms-id if not present
                        if (!str_contains($imgTag, 'data-cms-id')) {
                            $imgTag = str_replace('<img', '<img data-cms-id="' . $elementId . '"', $imgTag);
                        }

                        // Update src - preserve the Blade syntax
                        $newImgTag = $imgTag;

                        // If src uses asset() helper, update just the path inside
                        if (preg_match('/src=["\']\{\{[^}]*asset\([\'"]([^\'"]+)[\'"]\)[^}]*\}\}["\']/', $imgTag, $srcMatch)) {
                            $oldPath = $srcMatch[1];
                            // Extract just the path from the new URL
                            $newPath = preg_replace('/^https?:\/\/[^\/]+\//', '', $newContent['src']);
                            $newImgTag = str_replace("asset('{$oldPath}')", "asset('{$newPath}')", $newImgTag);
                            $newImgTag = str_replace('asset("' . $oldPath . '")', 'asset("' . $newPath . '")', $newImgTag);
                        } else {
                            // Fallback: replace the entire src attribute
                            $newImgTag = preg_replace(
                                '/src=["\'][^"\']*["\']/',
                                'src="' . $newContent['src'] . '"',
                                $imgTag
                            );
                        }

                        // Update alt if provided
                        if (isset($newContent['alt'])) {
                            if (preg_match('/alt=["\'][^"\']*["\']/', $newImgTag)) {
                                $newImgTag = preg_replace(
                                    '/alt=["\'][^"\']*["\']/',
                                    'alt="' . $newContent['alt'] . '"',
                                    $newImgTag
                                );
                            } else {
                                $newImgTag = preg_replace(
                                    '/<img/',
                                    '<img alt="' . $newContent['alt'] . '"',
                                    $newImgTag
                                );
                            }
                        }

                        $content = substr_replace($content, $newImgTag, $imgPos, $originalImgTagLength);

                        $this->logger->info('Updated image with Blade syntax preservation', [
                            'element_id' => $elementId,
                            'original_src' => $originalContent,
                            'new_src' => $newContent['src'],
                            'found_by' => $foundMatch ? 'pattern matching' : 'unknown'
                        ]);

                        return $content;
                    }
                }

                // If no data-cms-id found, try to use original content to find the specific image
                if ($originalContent && !empty($originalContent)) {
                    // Find all img tags with their positions
                    preg_match_all('/<img[^>]*>/i', $content, $imgMatches, PREG_OFFSET_CAPTURE);

                    // Try to find the specific image by matching the original src
                    $bestMatch = null;
                    $bestMatchPos = -1;

                    foreach ($imgMatches[0] as $index => $imgMatch) {
                        $imgTag = $imgMatch[0];
                        $imgPos = $imgMatch[1];

                        // Extract src from this img tag
                        if (preg_match('/src=["\']([^"\']*)["\']/', $imgTag, $srcMatch)) {
                            $currentSrc = $srcMatch[1];

                            // Check for exact match with original content
                            if ($currentSrc === $originalContent ||
                                htmlspecialchars_decode($currentSrc) === $originalContent ||
                                $currentSrc === htmlspecialchars($originalContent)) {

                                // Found exact match - this is definitely our image
                                $bestMatch = $imgTag;
                                $bestMatchPos = $imgPos;
                                break;
                            }

                            // Check if this img tag contains our original src hint (partial match)
                            if ($bestMatch === null &&
                                (strpos($imgTag, $originalContent) !== false ||
                                 strpos($imgTag, htmlspecialchars($originalContent)) !== false)) {
                                $bestMatch = $imgTag;
                                $bestMatchPos = $imgPos;
                            }
                        }
                    }

                    // Update the best match we found
                    if ($bestMatch !== null && $bestMatchPos >= 0) {
                        // This is our target image - update its src
                        $newImgTag = preg_replace(
                            '/src=["\'][^"\']*["\']/',
                            'src="' . $newContent['src'] . '"',
                            $bestMatch
                        );

                        // Update alt if provided
                        if (isset($newContent['alt'])) {
                            if (preg_match('/alt=["\'][^"\']*["\']/', $newImgTag)) {
                                $newImgTag = preg_replace(
                                    '/alt=["\'][^"\']*["\']/',
                                    'alt="' . $newContent['alt'] . '"',
                                    $newImgTag
                                );
                            } else {
                                // Add alt attribute if it doesn't exist
                                $newImgTag = preg_replace(
                                    '/<img/',
                                    '<img alt="' . $newContent['alt'] . '"',
                                    $newImgTag
                                );
                            }
                        }

                        // Replace in content
                        $content = substr_replace($content, $newImgTag, $bestMatchPos, strlen($bestMatch));

                        $this->logger->info('Updated specific image in Blade file', [
                            'element_id' => $elementId,
                            'original_hint' => substr($originalContent, 0, 50),
                            'new_src' => $newContent['src']
                        ]);

                        return $content;
                    }
                }

                // NO DANGEROUS FALLBACK - If we can't find the specific image, we should fail safely
                // The previous fallback would update the WRONG image which is destructive behavior

                $this->logger->error('Could not find specific image to update in Blade content', [
                    'element_id' => $elementId,
                    'original_content_hint' => $originalContent ? substr($originalContent, 0, 50) : 'none',
                    'message' => 'Image update aborted to prevent updating wrong image'
                ]);

                // Return content unchanged - safer to do nothing than update wrong element
                return $content;
            }

            $this->logger->warning('Could not find image to update in Blade content', [
                'element_id' => $elementId
            ]);
            return $content;
        }

        // For non-image content, find and replace by original content
        if ($originalContent && !empty(trim($originalContent))) {
            // Simple string replacement based on original content
            $originalContent = trim($originalContent);

            // Try exact match first
            if (strpos($content, $originalContent) !== false) {
                $content = str_replace($originalContent, $newContent, $content);
                $this->logger->info('Updated Blade content by exact match', [
                    'element_id' => $elementId,
                    'original' => substr($originalContent, 0, 50),
                    'new' => substr((string)$newContent, 0, 50)
                ]);
                return $content;
            }

            // Try with HTML entities decoded
            $decodedOriginal = html_entity_decode($originalContent);
            if (strpos($content, $decodedOriginal) !== false) {
                $content = str_replace($decodedOriginal, $newContent, $content);
                $this->logger->info('Updated Blade content by decoded match', [
                    'element_id' => $elementId
                ]);
                return $content;
            }

            // Try to find within tags
            $patterns = [
                '/(<[^>]*>)(' . preg_quote($originalContent, '/') . ')(<\/[^>]+>)/',
                '/(' . preg_quote($originalContent, '/') . ')/'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, '$1' . $newContent . '$3', $content, 1);
                    $this->logger->info('Updated Blade content by pattern match', [
                        'element_id' => $elementId,
                        'pattern' => $pattern
                    ]);
                    return $content;
                }
            }
        }

        $this->logger->warning('Could not update Blade content', [
            'element_id' => $elementId,
            'has_original' => !empty($originalContent)
        ]);

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
     * Convert a hard-coded string to a translation directive
     *
     * @param string $filePath Path to the Blade file
     * @param string $elementId Element ID for targeting
     * @param string $originalContent The original hard-coded string
     * @param string $translationKey The translation key to use
     * @return array Result with success status
     */
    public function convertToTranslationDirective($filePath, $elementId, $originalContent, $translationKey)
    {
        try {
            if (!File::exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            // Create backup
            $backupFile = $this->createBackup($filePath);

            // Read file content
            $content = File::get($filePath);

            // Find the original content and replace it with translation directive
            // We'll use the @lang() directive for Blade templates
            $translationDirective = "@lang('{$translationKey}')";

            // Try exact match first
            if (strpos($content, $originalContent) !== false) {
                // Count occurrences to warn if there are multiple
                $occurrences = substr_count($content, $originalContent);

                if ($occurrences > 1) {
                    $this->logger->warning('Multiple occurrences of content found, replacing first', [
                        'file' => $filePath,
                        'content' => substr($originalContent, 0, 50),
                        'occurrences' => $occurrences
                    ]);
                }

                // Replace first occurrence
                $pos = strpos($content, $originalContent);
                $updatedContent = substr_replace($content, $translationDirective, $pos, strlen($originalContent));

                // Write updated content
                File::put($filePath, $updatedContent);

                // Clear view cache
                if (str_ends_with($filePath, '.blade.php')) {
                    $this->clearViewCache($filePath);
                }

                $this->logger->info('Converted string to translation directive', [
                    'file' => $filePath,
                    'original' => substr($originalContent, 0, 50),
                    'translation_key' => $translationKey,
                    'backup' => $backupFile
                ]);

                return [
                    'success' => true,
                    'backup' => $backupFile,
                    'message' => 'Successfully converted to translation'
                ];
            }

            // Try with HTML entity decoding
            $decodedOriginal = html_entity_decode($originalContent);
            if (strpos($content, $decodedOriginal) !== false) {
                $pos = strpos($content, $decodedOriginal);
                $updatedContent = substr_replace($content, $translationDirective, $pos, strlen($decodedOriginal));

                File::put($filePath, $updatedContent);

                if (str_ends_with($filePath, '.blade.php')) {
                    $this->clearViewCache($filePath);
                }

                $this->logger->info('Converted string to translation directive (decoded)', [
                    'file' => $filePath,
                    'translation_key' => $translationKey
                ]);

                return [
                    'success' => true,
                    'backup' => $backupFile,
                    'message' => 'Successfully converted to translation'
                ];
            }

            // If not found, try to find it within HTML tags
            // Pattern: >original content<
            $pattern = '/(>)(' . preg_quote($originalContent, '/') . ')(<)/';
            if (preg_match($pattern, $content)) {
                $updatedContent = preg_replace($pattern, '$1' . $translationDirective . '$3', $content, 1);

                File::put($filePath, $updatedContent);

                if (str_ends_with($filePath, '.blade.php')) {
                    $this->clearViewCache($filePath);
                }

                $this->logger->info('Converted string to translation directive (tag pattern)', [
                    'file' => $filePath,
                    'translation_key' => $translationKey
                ]);

                return [
                    'success' => true,
                    'backup' => $backupFile,
                    'message' => 'Successfully converted to translation'
                ];
            }

            // Content not found
            $this->logger->warning('Could not find content to convert', [
                'file' => $filePath,
                'element_id' => $elementId,
                'original_content' => substr($originalContent, 0, 50)
            ]);

            return [
                'success' => false,
                'error' => 'Could not locate the original content in the file'
            ];

        } catch (Exception $e) {
            $this->logger->error('Failed to convert to translation directive', [
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
     * Check if content is within source markers (for component files)
     *
     * @param string $content
     * @param string $originalContent
     * @return bool
     */
    protected function isWithinSourceMarkers($content, $originalContent)
    {
        // If the file has source markers and the original content is between them
        if (preg_match('/@cmsSourceStart.*?' . preg_quote($originalContent, '/') . '.*?@cmsSourceEnd/s', $content)) {
            return true;
        }
        return false;
    }

    /**
     * Extract content within source markers
     *
     * @param string $content
     * @return string|null
     */
    protected function extractSourceMarkerContent($content)
    {
        // Extract content between @cmsSourceStart and @cmsSourceEnd
        if (preg_match('/@cmsSourceStart(.*?)@cmsSourceEnd/s', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
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