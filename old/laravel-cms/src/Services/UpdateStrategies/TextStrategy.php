<?php

namespace Webook\LaravelCMS\Services\UpdateStrategies;

/**
 * Text Update Strategy
 *
 * Handles plain text updates with support for exact matching,
 * regex patterns, and basic text transformations.
 */
class TextStrategy extends AbstractUpdateStrategy
{
    /**
     * Check if this strategy can handle the content.
     *
     * @param string $content Content to check
     * @param array $context Additional context
     * @return bool True if can handle
     */
    public function canHandle(string $content, array $context = []): bool
    {
        // Text strategy can handle any content as fallback
        return true;
    }

    /**
     * Update content using text replacement.
     *
     * @param string $content Original content
     * @param string $old Old value to replace
     * @param string $new New value
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateContent(string $content, string $old, string $new, array $context = []): string
    {
        $this->logOperation('updateContent', [
            'old_length' => strlen($old),
            'new_length' => strlen($new),
            'is_regex' => $context['regex'] ?? false,
        ]);

        if (empty($old)) {
            throw new \InvalidArgumentException('Old value cannot be empty');
        }

        // Handle regex patterns
        if ($context['regex'] ?? false) {
            return $this->updateWithRegex($content, $old, $new, $context);
        }

        // Handle exact text replacement
        return $this->updateWithExactMatch($content, $old, $new, $context);
    }

    /**
     * Update using exact text matching.
     *
     * @param string $content Original content
     * @param string $old Old value
     * @param string $new New value
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateWithExactMatch(string $content, string $old, string $new, array $context): string
    {
        $caseSensitive = $context['case_sensitive'] ?? $this->config['case_sensitive'];
        $limit = $context['limit'] ?? -1;

        if ($caseSensitive) {
            if ($limit > 0) {
                $pos = 0;
                $count = 0;
                while (($pos = strpos($content, $old, $pos)) !== false && $count < $limit) {
                    $content = substr_replace($content, $new, $pos, strlen($old));
                    $pos += strlen($new);
                    $count++;
                }
                return $content;
            } else {
                return str_replace($old, $new, $content);
            }
        } else {
            return str_ireplace($old, $new, $content, $limit);
        }
    }

    /**
     * Update using regex patterns.
     *
     * @param string $content Original content
     * @param string $pattern Regex pattern
     * @param string $replacement Replacement string
     * @param array $context Additional context
     * @return string Updated content
     */
    protected function updateWithRegex(string $content, string $pattern, string $replacement, array $context): string
    {
        $flags = $context['regex_flags'] ?? $this->config['regex_flags'];
        $limit = $context['limit'] ?? -1;

        // Ensure pattern has delimiters
        if (!preg_match('/^[\/\#\~\!\@\%\^\&\*\+\=\|\:\;\<\>\?]/', $pattern)) {
            $pattern = '/' . $pattern . '/' . $flags;
        }

        try {
            return $this->safeRegexReplace($pattern, $replacement, $content, $limit);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Regex replacement failed: {$e->getMessage()}");
        }
    }

    /**
     * Update by selector (treats selector as text to find).
     *
     * @param string $content Original content
     * @param string $selector Selector (treated as text)
     * @param string $new New content
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateBySelector(string $content, string $selector, string $new, array $context = []): string
    {
        $this->logOperation('updateBySelector', [
            'selector' => $selector,
            'new_length' => strlen($new),
        ]);

        // For text strategy, treat selector as exact text match
        return $this->updateContent($content, $selector, $new, $context);
    }

    /**
     * Update attribute (not applicable for text strategy).
     *
     * @param string $content Original content
     * @param string $selector Selector
     * @param string $attribute Attribute name
     * @param string $value New value
     * @param array $context Additional context
     * @return string Original content (unchanged)
     */
    public function updateAttribute(string $content, string $selector, string $attribute, string $value, array $context = []): string
    {
        $this->logOperation('updateAttribute', [
            'message' => 'Attribute updates not supported by TextStrategy',
        ]);

        // Text strategy doesn't support attribute updates
        return $content;
    }

    /**
     * Validate text content.
     *
     * @param string $content Content to validate
     * @param array $context Additional context
     * @return array Validation results
     */
    public function validate(string $content, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        // Check for basic text issues
        if (empty(trim($content)) && ($context['allow_empty'] ?? false) === false) {
            $warnings[] = 'Content is empty or contains only whitespace';
        }

        // Check for suspicious patterns that might indicate malformed content
        if (preg_match('/[^\x20-\x7E\n\r\t]/', $content)) {
            $warnings[] = 'Content contains non-printable characters';
        }

        // Check encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $errors[] = 'Content is not valid UTF-8';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get strategy priority.
     *
     * @return int Priority (lowest for fallback)
     */
    public function getPriority(): int
    {
        return 10; // Low priority - fallback strategy
    }

    /**
     * Get strategy name.
     *
     * @return string Strategy name
     */
    public function getName(): string
    {
        return 'TextStrategy';
    }

    /**
     * Get default configuration.
     *
     * @return array Default configuration
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'max_replacement_length' => 1000000, // 1MB
            'preserve_line_endings' => true,
            'trim_whitespace' => false,
        ]);
    }
}