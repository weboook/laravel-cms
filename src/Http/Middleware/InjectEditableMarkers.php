<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use DOMDocument;
use DOMXPath;

class InjectEditableMarkers
{
    protected $editableSelectors = [
        'p',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'span',
        'div[class*="content"]',
        'div[class*="text"]',
        'article',
        'section > p',
        'section > h1', 'section > h2', 'section > h3',
        'main p',
        'main h1', 'main h2', 'main h3',
        'li',
        'td',
        'blockquote',
        'figcaption',
        'a',
        'button'
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Skip if CMS is not enabled
        if (!config('cms.enabled', true)) {
            return $response;
        }

        // Skip API and CMS routes
        if ($request->is('api/*') || $request->is('cms/*')) {
            return $response;
        }

        // Skip non-HTML responses
        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        $content = $response->getContent();

        // Only process HTML responses with body tag
        if ($content && strpos($content, '</body>') !== false) {
            $content = $this->injectMarkers($content);
            $response->setContent($content);
        }

        return $response;
    }

    protected function injectMarkers($html)
    {
        // Add data attributes to editable elements
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);

        // Process each editable selector
        foreach ($this->editableSelectors as $selector) {
            $elements = $this->querySelectorAll($xpath, $selector);

            foreach ($elements as $element) {
                // Skip if already marked
                if ($element->hasAttribute('data-cms-editable')) {
                    continue;
                }

                // For links and buttons, don't skip if empty (they might have only href)
                $tagName = strtolower($element->tagName);
                if (!in_array($tagName, ['a', 'button']) && trim($element->textContent) === '') {
                    continue;
                }

                // Skip if contains other editable elements (avoid nesting)
                if ($this->containsEditableChildren($element)) {
                    continue;
                }

                // Add editable attributes
                $element->setAttribute('data-cms-editable', 'true');
                $element->setAttribute('data-cms-type', $this->getContentType($element));
                $element->setAttribute('data-cms-id', $this->generateContentId($element));
                $element->setAttribute('data-cms-original', trim($element->textContent));
            }
        }

        // Inject the editable styles and scripts
        $editableAssets = $this->getEditableAssets();
        $html = $dom->saveHTML();

        // Insert assets before closing body tag
        $html = str_replace('</body>', $editableAssets . '</body>', $html);

        return $html;
    }

    protected function querySelectorAll($xpath, $selector)
    {
        // Convert CSS selector to XPath
        $xpathQuery = $this->cssToXPath($selector);
        return $xpath->query($xpathQuery);
    }

    protected function cssToXPath($selector)
    {
        // Basic CSS to XPath conversion
        $selector = trim($selector);

        // Handle basic tag selectors
        if (preg_match('/^[a-z]+$/i', $selector)) {
            return "//{$selector}";
        }

        // Handle class selectors with contains
        if (strpos($selector, '[class*=') !== false) {
            preg_match('/([a-z]+)\[class\*="([^"]+)"\]/i', $selector, $matches);
            if ($matches) {
                return "//{$matches[1]}[contains(@class, '{$matches[2]}')]";
            }
        }

        // Handle parent > child
        if (strpos($selector, '>') !== false) {
            $parts = explode('>', $selector);
            $parent = trim($parts[0]);
            $child = trim($parts[1]);
            return "//{$parent}/{$child}";
        }

        // Handle descendant selectors
        if (strpos($selector, ' ') !== false) {
            $parts = explode(' ', $selector);
            $parent = trim($parts[0]);
            $child = trim($parts[1]);
            return "//{$parent}//{$child}";
        }

        return "//{$selector}";
    }

    protected function containsEditableChildren($element)
    {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                if ($child->hasAttribute('data-cms-editable')) {
                    return true;
                }
                // Check common editable tags
                $tagName = strtolower($child->tagName);
                if (in_array($tagName, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span'])) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function getContentType($element)
    {
        $tagName = strtolower($element->tagName);

        if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            return 'heading';
        }

        if ($tagName === 'img') {
            return 'image';
        }

        if ($tagName === 'a') {
            return 'link';
        }

        if ($tagName === 'button') {
            return 'button';
        }

        return 'text';
    }

    protected function generateContentId($element)
    {
        $text = substr(trim($element->textContent), 0, 20);
        $tagName = $element->tagName;
        return 'cms-' . md5($tagName . '-' . $text . '-' . uniqid());
    }

    protected function getEditableAssets()
    {
        return <<<HTML
<style id="cms-editable-styles">
    [data-cms-editable] {
        position: relative;
        transition: all 0.2s ease;
    }

    body.cms-edit-mode [data-cms-editable]:not([data-cms-type="link"]):not([data-cms-type="button"]) {
        outline: 2px dashed transparent;
        outline-offset: 4px;
        cursor: pointer;
        min-height: 20px;
    }

    body.cms-edit-mode [data-cms-editable]:not([data-cms-type="link"]):not([data-cms-type="button"]):hover {
        outline-color: #0066ff;
        background-color: rgba(0, 102, 255, 0.05);
    }

    body.cms-edit-mode [data-cms-editable].cms-editing {
        outline: 2px solid #0066ff;
        background-color: rgba(0, 102, 255, 0.1);
    }

    /* Link and Button specific styles */
    body.cms-edit-mode [data-cms-type="link"],
    body.cms-edit-mode [data-cms-type="button"] {
        position: relative;
    }

    .cms-link-gear {
        position: absolute;
        top: 50%;
        right: -30px;
        transform: translateY(-50%);
        width: 24px;
        height: 24px;
        background: #0066ff;
        border-radius: 4px;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .cms-link-gear:hover {
        background: #0052d4;
    }

    .cms-link-gear svg {
        width: 16px;
        height: 16px;
        fill: white;
    }

    body.cms-edit-mode [data-cms-type="link"]:hover .cms-link-gear,
    body.cms-edit-mode [data-cms-type="button"]:hover .cms-link-gear {
        display: flex;
    }

    body.cms-edit-mode [data-cms-editable]::before {
        content: attr(data-cms-type);
        position: absolute;
        top: -24px;
        left: 0;
        background: #0066ff;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 2px;
        text-transform: uppercase;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
        z-index: 10000;
    }

    body.cms-edit-mode [data-cms-editable]:hover::before {
        opacity: 1;
    }

    .cms-inline-editor {
        border: none;
        outline: none;
        background: transparent;
        font: inherit;
        color: inherit;
        padding: 0;
        margin: 0;
        width: 100%;
        resize: none;
        overflow: hidden;
    }

    .cms-editor-toolbar {
        position: absolute;
        top: -40px;
        left: 0;
        background: #1a1a1a;
        border-radius: 4px;
        padding: 4px;
        display: flex;
        gap: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 10001;
    }

    .cms-editor-toolbar button {
        background: transparent;
        border: none;
        color: #fff;
        padding: 4px 8px;
        cursor: pointer;
        border-radius: 2px;
        font-size: 12px;
    }

    .cms-editor-toolbar button:hover {
        background: #333;
    }
</style>

<script id="cms-editable-script">
(function() {
    'use strict';

    // Wait for CMS toolbar to be ready
    document.addEventListener('DOMContentLoaded', function() {

        // Set initial mode
        if (window.CMS && window.CMS.mode === 'edit') {
            document.body.classList.add('cms-edit-mode');
        }

        // Listen for mode changes
        document.addEventListener('cms:modeChanged', function(e) {
            if (e.detail.mode === 'edit') {
                document.body.classList.add('cms-edit-mode');
                initializeEditableElements();
            } else {
                document.body.classList.remove('cms-edit-mode');
                closeAllEditors();
            }
        });

        // Initialize editable elements
        function initializeEditableElements() {
            const editables = document.querySelectorAll('[data-cms-editable]');

            editables.forEach(element => {
                const type = element.getAttribute('data-cms-type');

                // Handle links and buttons differently
                if (type === 'link' || type === 'button') {
                    initializeLinkElement(element);
                } else {
                    // Remove any existing listeners
                    element.removeEventListener('click', handleEditableClick);
                    // Add click listener
                    element.addEventListener('click', handleEditableClick);
                }
            });
        }

        // Initialize link elements with gear icon
        function initializeLinkElement(element) {
            // Remove existing gear if any
            const existingGear = element.querySelector('.cms-link-gear');
            if (existingGear) {
                existingGear.remove();
            }

            // Create gear icon
            const gear = document.createElement('div');
            gear.className = 'cms-link-gear';
            gear.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 8.666c-1.838 0-3.333 1.496-3.333 3.334s1.495 3.333 3.333 3.333 3.333-1.495 3.333-3.333-1.495-3.334-3.333-3.334zm0 5.334c-1.105 0-2-.896-2-2s.895-2 2-2 2 .896 2 2-.895 2-2 2zm7.04-2.404l1.313-.988c.185-.139.23-.386.119-.579l-1.24-2.148c-.11-.193-.359-.244-.534-.137l-1.540.617c-.449-.331-.949-.596-1.489-.784l-.234-1.644c-.038-.218-.237-.382-.456-.382h-2.478c-.219 0-.418.164-.456.382l-.234 1.644c-.540.188-1.04.453-1.489.784l-1.54-.617c-.175-.107-.424-.056-.534.137l-1.24 2.148c-.11.193-.066.44.119.579l1.313.988c-.05.261-.081.53-.081.809s.031.548.081.809l-1.313.988c-.185.139-.23.386-.119.579l1.24 2.148c.11.193.359.244.534.137l1.54-.617c.449.331.949.596 1.489.784l.234 1.644c.038.218.237.382.456.382h2.478c.219 0 .418-.164.456-.382l.234-1.644c.540-.188 1.04-.453 1.489-.784l1.54.617c.175.107.424.056.534-.137l1.24-2.148c.11-.193.066-.44-.119-.579l-1.313-.988c.05-.261.081-.53.081-.809s-.031-.548-.081-.809z"/></svg>';
            element.appendChild(gear);

            // Add click handler to gear
            gear.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openLinkEditor(element);
            });
        }

        // Handle click on editable element
        function handleEditableClick(e) {
            if (!document.body.classList.contains('cms-edit-mode')) return;

            e.preventDefault();
            e.stopPropagation();

            const element = e.currentTarget;

            // Close other editors
            closeAllEditors();

            // Mark as editing
            element.classList.add('cms-editing');

            // Create inline editor
            createInlineEditor(element);
        }

        // Open link editor modal
        function openLinkEditor(element) {
            const linkText = element.textContent;
            const linkHref = element.getAttribute('href') || '';
            const linkTarget = element.getAttribute('target') === '_blank';

            // Store reference to current element
            window.CMS = window.CMS || {};
            window.CMS.currentLinkElement = element;

            // Dispatch event to open modal
            const event = new CustomEvent('cms:openLinkEditor', {
                detail: {
                    text: linkText,
                    href: linkHref,
                    newTab: linkTarget,
                    element: element
                }
            });
            document.dispatchEvent(event);
        }

        // Create inline editor
        function createInlineEditor(element) {
            const type = element.getAttribute('data-cms-type');
            const originalContent = element.innerHTML;
            const originalText = element.textContent;

            // Store original content
            element.setAttribute('data-cms-original-html', originalContent);

            // Make it contenteditable
            element.contentEditable = true;
            element.focus();

            // Select all text
            const range = document.createRange();
            range.selectNodeContents(element);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            // Create toolbar
            const toolbar = createEditorToolbar(element);
            element.parentElement.appendChild(toolbar);

            // Handle blur
            element.addEventListener('blur', function onBlur(e) {
                // Don't close if clicking toolbar
                if (toolbar.contains(e.relatedTarget)) {
                    element.focus();
                    return;
                }

                setTimeout(() => {
                    if (!toolbar.contains(document.activeElement)) {
                        saveAndCloseEditor(element, toolbar);
                    }
                }, 100);
            });

            // Handle Enter key for headings and paragraphs
            element.addEventListener('keydown', function onKeydown(e) {
                if (e.key === 'Enter' && type === 'heading') {
                    e.preventDefault();
                    saveAndCloseEditor(element, toolbar);
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    // Restore original content
                    element.innerHTML = originalContent;
                    saveAndCloseEditor(element, toolbar);
                }
            });
        }

        // Create editor toolbar
        function createEditorToolbar(element) {
            const toolbar = document.createElement('div');
            toolbar.className = 'cms-editor-toolbar';

            // Save button
            const saveBtn = document.createElement('button');
            saveBtn.textContent = '✓ Save';
            saveBtn.onclick = () => saveAndCloseEditor(element, toolbar);

            // Cancel button
            const cancelBtn = document.createElement('button');
            cancelBtn.textContent = '✕ Cancel';
            cancelBtn.onclick = () => {
                element.innerHTML = element.getAttribute('data-cms-original-html');
                saveAndCloseEditor(element, toolbar);
            };

            toolbar.appendChild(saveBtn);
            toolbar.appendChild(cancelBtn);

            // Position toolbar
            const rect = element.getBoundingClientRect();
            toolbar.style.left = '0';

            return toolbar;
        }

        // Save and close editor
        function saveAndCloseEditor(element, toolbar) {
            const newContent = element.innerHTML;
            const contentId = element.getAttribute('data-cms-id');

            // Remove contenteditable
            element.contentEditable = false;
            element.classList.remove('cms-editing');

            // Remove toolbar
            if (toolbar && toolbar.parentElement) {
                toolbar.parentElement.removeChild(toolbar);
            }

            // Trigger save event (to be handled by save functionality)
            const event = new CustomEvent('cms:contentChanged', {
                detail: {
                    id: contentId,
                    content: newContent,
                    element: element
                }
            });
            document.dispatchEvent(event);
        }

        // Close all editors
        function closeAllEditors() {
            document.querySelectorAll('.cms-editing').forEach(element => {
                element.contentEditable = false;
                element.classList.remove('cms-editing');
            });

            document.querySelectorAll('.cms-editor-toolbar').forEach(toolbar => {
                toolbar.remove();
            });
        }

        // Initialize if already in edit mode
        if (document.body.classList.contains('cms-edit-mode')) {
            initializeEditableElements();
        }
    });
})();
</script>
HTML;
    }
}