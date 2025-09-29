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
        'button',
        'img'
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
                if ($element->hasAttribute('data-cms-editable') ||
                    $element->hasAttribute('data-cms-component')) {
                    continue;
                }

                // Skip toolbar elements and CMS injected elements
                if ($this->isToolbarElement($element) || $this->isCMSInjectedElement($element)) {
                    continue;
                }

                // Check if it's database content
                if ($this->isDatabaseContent($element)) {
                    // Mark as component with coming soon message
                    $element->setAttribute('data-cms-component', 'true');
                    $element->setAttribute('data-cms-type', 'database');
                    $element->setAttribute('data-cms-message', 'Editing database elements coming soon');
                    continue;
                }

                // For links, buttons, and images, don't skip if empty
                $tagName = strtolower($element->tagName);
                if (!in_array($tagName, ['a', 'button', 'img']) && trim($element->textContent) === '') {
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

    protected function isCMSInjectedElement($element)
    {
        // Check if element is within a script or style tag that we injected
        $current = $element;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($current->tagName);

            // Skip if inside script or style tags
            if ($tagName === 'script' || $tagName === 'style') {
                // Check if it's a CMS-injected script/style
                if ($current->hasAttribute('id')) {
                    $id = $current->getAttribute('id');
                    if (strpos($id, 'cms-') === 0) {
                        return true;
                    }
                }
                return true; // Skip all scripts and styles to be safe
            }

            $current = $current->parentNode;
        }
        return false;
    }

    protected function isToolbarElement($element)
    {
        // Check if element or any parent has cms-toolbar related classes/ids
        $current = $element;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            // Check for toolbar-related IDs - be more comprehensive
            if ($current->hasAttribute('id')) {
                $id = $current->getAttribute('id');
                if (strpos($id, 'cms') === 0 || // Any ID starting with 'cms'
                    strpos($id, 'cms-') !== false ||
                    $id === 'cms-toolbar' ||
                    $id === 'cms-modal-container' ||
                    strpos($id, 'cms-editable-') === 0) {
                    return true;
                }
            }

            // Check for toolbar-related classes - be more comprehensive
            if ($current->hasAttribute('class')) {
                $class = $current->getAttribute('class');
                if (strpos($class, 'cms-') !== false || // Any class with 'cms-'
                    strpos($class, 'cms') === 0) { // Any class starting with 'cms'
                    return true;
                }
            }

            // Check for data attributes that indicate CMS elements
            if ($current->hasAttribute('data-modal') ||
                $current->hasAttribute('data-mode') ||
                $current->hasAttribute('data-tab') ||
                $current->hasAttribute('data-lang') ||
                $current->hasAttribute('data-path') ||
                $current->hasAttribute('data-template') ||
                $current->hasAttribute('data-cms-ignore')) {
                return true;
            }

            // Move up to parent
            $current = $current->parentNode;
        }
        return false;
    }

    protected function isDatabaseContent($element)
    {
        // Look for common indicators of database content
        $indicators = [
            '@foreach', '@forelse', '@for', '@while', // Blade loop directives
            '{{', '{!!', // Blade echo statements
            'v-for', 'v-if', ':key', // Vue.js directives
            'ng-repeat', 'ng-for', '*ngFor', // Angular directives
            'data-id', 'data-model', 'data-entity' // Common data attributes
        ];

        // Check element's outer HTML for indicators
        $html = $element->ownerDocument->saveHTML($element);
        foreach ($indicators as $indicator) {
            if (strpos($html, $indicator) !== false) {
                return true;
            }
        }

        // Check if element has data attributes suggesting database content
        if ($element->hasAttribute('data-id') ||
            $element->hasAttribute('data-model') ||
            $element->hasAttribute('data-entity') ||
            $element->hasAttribute('data-record')) {
            return true;
        }

        // Check parent elements for loop structures
        $parent = $element->parentNode;
        $depth = 0;
        while ($parent && $depth < 5) {
            if ($parent->nodeType === XML_ELEMENT_NODE) {
                $parentHtml = $parent->ownerDocument->saveHTML($parent);
                // Check for Blade directives in parent
                if (preg_match('/@(foreach|forelse|for|while)\s*\(/', $parentHtml)) {
                    return true;
                }
            }
            $parent = $parent->parentNode;
            $depth++;
        }

        return false;
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

    /* Link, Button and Image specific styles */
    body.cms-edit-mode [data-cms-type="link"],
    body.cms-edit-mode [data-cms-type="button"] {
        position: relative;
    }

    body.cms-edit-mode [data-cms-type="image"] {
        position: relative;
        display: inline-block;
        outline: 2px dashed transparent;
        outline-offset: 4px;
        transition: all 0.2s ease;
    }

    body.cms-edit-mode [data-cms-type="image"]:hover {
        outline-color: #0066ff;
    }

    .cms-link-wrapper {
        position: relative;
        display: inline-block;
    }

    .cms-link-gear {
        position: absolute;
        top: 50%;
        right: -35px;
        transform: translateY(-50%);
        width: 28px;
        height: 28px;
        background: #0066ff;
        border-radius: 4px;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: all 0.2s ease;
    }

    /* Adjust gear position for images */
    body.cms-edit-mode [data-cms-type="image"] .cms-link-gear {
        top: 10px;
        right: 10px;
        transform: none;
    }

    .cms-link-gear:hover {
        background: #0052d4;
        transform: translateY(-50%) scale(1.1);
    }

    body.cms-edit-mode [data-cms-type="image"] .cms-link-gear:hover {
        transform: scale(1.1);
    }

    .cms-link-gear svg {
        width: 16px;
        height: 16px;
        fill: white;
    }

    /* Show gear on hover of link, button, or image */
    body.cms-edit-mode [data-cms-type="link"]:hover .cms-link-gear,
    body.cms-edit-mode [data-cms-type="button"]:hover .cms-link-gear,
    body.cms-edit-mode [data-cms-type="image"]:hover .cms-link-gear,
    body.cms-edit-mode .cms-link-gear:hover,
    body.cms-edit-mode .cms-link-gear.visible {
        display: flex;
    }

    /* Invisible bridge to maintain hover */
    body.cms-edit-mode [data-cms-type="link"]::after,
    body.cms-edit-mode [data-cms-type="button"]::after,
    body.cms-edit-mode [data-cms-type="image"]::after {
        content: '';
        position: absolute;
        top: 50%;
        right: -35px;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        z-index: 9999;
        pointer-events: none;
    }

    body.cms-edit-mode [data-cms-type="link"]:hover::after,
    body.cms-edit-mode [data-cms-type="button"]:hover::after,
    body.cms-edit-mode [data-cms-type="image"]:hover::after {
        pointer-events: auto;
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
        top: -48px;
        left: 0;
        background: #1a1a1a;
        border-radius: 4px;
        padding: 4px;
        display: flex;
        gap: 2px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 10001;
    }

    .cms-editor-toolbar button {
        background: transparent;
        border: none;
        color: #fff;
        padding: 6px 8px;
        cursor: pointer;
        border-radius: 2px;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 4px;
        min-width: 30px;
        justify-content: center;
    }

    .cms-editor-toolbar button:hover {
        background: #333;
    }

    .cms-editor-toolbar button.active {
        background: #0066ff;
    }

    .cms-editor-toolbar .separator {
        width: 1px;
        background: #444;
        margin: 4px 2px;
    }

    .cms-editor-toolbar button svg {
        width: 14px;
        height: 14px;
        fill: currentColor;
    }

    /* Database component styles */
    body.cms-edit-mode [data-cms-component="true"] {
        position: relative;
        outline: 2px dashed #ff6b6b;
        outline-offset: 4px;
        background-color: rgba(255, 107, 107, 0.05);
        cursor: not-allowed;
    }

    body.cms-edit-mode [data-cms-component="true"]::after {
        content: attr(data-cms-message);
        position: absolute;
        top: -28px;
        left: 0;
        background: linear-gradient(90deg, #ff6b6b, #ff8787);
        color: white;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 3px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
        z-index: 10001;
        white-space: nowrap;
        box-shadow: 0 2px 8px rgba(255, 107, 107, 0.3);
    }

    body.cms-edit-mode [data-cms-component="true"]:hover::after {
        opacity: 1;
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

                // Handle links, buttons, and images with gear icon
                if (type === 'link' || type === 'button' || type === 'image') {
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
            gear.setAttribute('data-cms-ignore', 'true'); // Mark to be ignored
            gear.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 8.666c-1.838 0-3.333 1.496-3.333 3.334s1.495 3.333 3.333 3.333 3.333-1.495 3.333-3.333-1.495-3.334-3.333-3.334zm0 5.334c-1.105 0-2-.896-2-2s.895-2 2-2 2 .896 2 2-.895 2-2 2zm7.04-2.404l1.313-.988c.185-.139.23-.386.119-.579l-1.24-2.148c-.11-.193-.359-.244-.534-.137l-1.540.617c-.449-.331-.949-.596-1.489-.784l-.234-1.644c-.038-.218-.237-.382-.456-.382h-2.478c-.219 0-.418.164-.456.382l-.234 1.644c-.540.188-1.04.453-1.489.784l-1.54-.617c-.175-.107-.424-.056-.534.137l-1.24 2.148c-.11.193-.066.44.119.579l1.313.988c-.05.261-.081.53-.081.809s.031.548.081.809l-1.313.988c-.185.139-.23.386-.119.579l1.24 2.148c.11.193.359.244.534.137l1.54-.617c.449.331.949.596 1.489.784l.234 1.644c.038.218.237.382.456.382h2.478c.219 0 .418-.164.456-.382l.234-1.644c.540-.188 1.04-.453 1.489-.784l1.54.617c.175.107.424.056.534-.137l1.24-2.148c.11-.193.066-.44-.119-.579l-1.313-.988c.05-.261.081-.53.081-.809s-.031-.548-.081-.809z"/></svg>';
            element.appendChild(gear);

            // Add hover timeout handling
            let hoverTimeout;
            let isHovering = false;

            // Show gear on element hover
            element.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                gear.classList.add('visible');
                isHovering = true;
            });

            element.addEventListener('mouseleave', function() {
                isHovering = false;
                // Delay hiding to allow moving to gear
                hoverTimeout = setTimeout(() => {
                    if (!gear.matches(':hover')) {
                        gear.classList.remove('visible');
                    }
                }, 200);
            });

            // Keep gear visible when hovering gear itself
            gear.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                gear.classList.add('visible');
            });

            gear.addEventListener('mouseleave', function() {
                if (!isHovering) {
                    hoverTimeout = setTimeout(() => {
                        gear.classList.remove('visible');
                    }, 200);
                }
            });

            // Add click handler to gear
            gear.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const type = element.getAttribute('data-cms-type');
                if (type === 'image') {
                    openImageEditor(element);
                } else {
                    openLinkEditor(element);
                }
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

        // Open image editor modal
        function openImageEditor(element) {
            const imageSrc = element.getAttribute('src') || '';
            const imageAlt = element.getAttribute('alt') || '';
            const imageTitle = element.getAttribute('title') || '';

            // Store reference to current element
            window.CMS = window.CMS || {};
            window.CMS.currentImageElement = element;

            // Dispatch event to open modal
            const event = new CustomEvent('cms:openImageEditor', {
                detail: {
                    src: imageSrc,
                    alt: imageAlt,
                    title: imageTitle,
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

            // Place cursor at the end instead of selecting all
            const range = document.createRange();
            const selection = window.getSelection();
            range.selectNodeContents(element);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);

            // Create toolbar
            const toolbar = createEditorToolbar(element);
            toolbar.setAttribute('data-cms-ignore', 'true'); // Mark to be ignored
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

            // Format buttons
            const formatButtons = [
                { command: 'bold', icon: 'B', title: 'Bold' },
                { command: 'italic', icon: 'I', title: 'Italic' },
                { command: 'underline', icon: 'U', title: 'Underline' },
                { separator: true },
                { command: 'createLink', icon: '🔗', title: 'Link', needsInput: true },
                { command: 'unlink', icon: '⛓️‍💥', title: 'Unlink' },
                { separator: true },
                { command: 'formatBlock', value: 'h1', icon: 'H1', title: 'Heading 1' },
                { command: 'formatBlock', value: 'h2', icon: 'H2', title: 'Heading 2' },
                { command: 'formatBlock', value: 'p', icon: 'P', title: 'Paragraph' },
                { separator: true },
                { command: 'insertOrderedList', icon: '1.', title: 'Ordered List' },
                { command: 'insertUnorderedList', icon: '•', title: 'Unordered List' },
                { separator: true }
            ];

            // Add format buttons
            formatButtons.forEach(btn => {
                if (btn.separator) {
                    const sep = document.createElement('div');
                    sep.className = 'separator';
                    toolbar.appendChild(sep);
                } else {
                    const button = document.createElement('button');
                    button.innerHTML = btn.icon;
                    button.title = btn.title;
                    button.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        if (btn.needsInput && btn.command === 'createLink') {
                            const url = prompt('Enter URL:');
                            if (url) {
                                document.execCommand(btn.command, false, url);
                            }
                        } else if (btn.value) {
                            document.execCommand(btn.command, false, btn.value);
                        } else {
                            document.execCommand(btn.command, false, null);
                        }

                        // Check if button should be active
                        updateToolbarState();
                        element.focus();
                    };
                    toolbar.appendChild(button);
                }
            });

            // Save button
            const saveBtn = document.createElement('button');
            saveBtn.innerHTML = '✓ Save';
            saveBtn.style.background = '#0066ff';
            saveBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                saveAndCloseEditor(element, toolbar);
            };

            // Cancel button
            const cancelBtn = document.createElement('button');
            cancelBtn.innerHTML = '✕';
            cancelBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                element.innerHTML = element.getAttribute('data-cms-original-html');
                saveAndCloseEditor(element, toolbar);
            };

            toolbar.appendChild(saveBtn);
            toolbar.appendChild(cancelBtn);

            // Update toolbar state function
            function updateToolbarState() {
                toolbar.querySelectorAll('button').forEach(btn => {
                    const command = btn.dataset.command;
                    if (command && document.queryCommandState(command)) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
            }

            // Listen for selection changes
            document.addEventListener('selectionchange', updateToolbarState);

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