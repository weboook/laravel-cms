<?php

namespace Webook\LaravelCMS\Services\UpdateStrategies;

use Webook\LaravelCMS\Contracts\UpdateStrategyInterface;

/**
 * Abstract Update Strategy
 *
 * Base class providing common functionality for update strategies.
 */
abstract class AbstractUpdateStrategy implements UpdateStrategyInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get default configuration for the strategy.
     *
     * @return array Default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'preserve_whitespace' => true,
            'validate_after_update' => true,
            'case_sensitive' => true,
            'regex_flags' => '',
        ];
    }

    /**
     * Normalize line endings to Unix format.
     *
     * @param string $content Content to normalize
     * @return string Normalized content
     */
    protected function normalizeLineEndings(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    /**
     * Split content into lines while preserving line endings.
     *
     * @param string $content Content to split
     * @return array Array of lines
     */
    protected function splitLines(string $content): array
    {
        return explode("\n", $this->normalizeLineEndings($content));
    }

    /**
     * Join lines back into content.
     *
     * @param array $lines Array of lines
     * @return string Joined content
     */
    protected function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * Escape special regex characters.
     *
     * @param string $string String to escape
     * @return string Escaped string
     */
    protected function escapeRegex(string $string): string
    {
        return preg_quote($string, '/');
    }

    /**
     * Perform a safe regex replacement.
     *
     * @param string $pattern Regex pattern
     * @param string $replacement Replacement string
     * @param string $subject Subject string
     * @param int $limit Maximum replacements
     * @return string Updated string
     */
    protected function safeRegexReplace(string $pattern, string $replacement, string $subject, int $limit = -1): string
    {
        $result = preg_replace($pattern, $replacement, $subject, $limit);

        if ($result === null) {
            throw new \InvalidArgumentException('Invalid regex pattern or replacement failed');
        }

        return $result;
    }

    /**
     * Check if content contains specific pattern.
     *
     * @param string $content Content to search
     * @param string $pattern Pattern to find
     * @param bool $regex Whether pattern is regex
     * @return bool True if pattern found
     */
    protected function containsPattern(string $content, string $pattern, bool $regex = false): bool
    {
        if ($regex) {
            return preg_match($pattern, $content) === 1;
        }

        $flags = $this->config['case_sensitive'] ? 0 : CASE_INSENSITIVE;
        return strpos($content, $pattern, $flags) !== false;
    }

    /**
     * Default validation - can be overridden by specific strategies.
     *
     * @param string $content Content to validate
     * @param array $context Additional context
     * @return array Validation results
     */
    public function validate(string $content, array $context = []): array
    {
        return [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * Default priority - can be overridden by specific strategies.
     *
     * @return int Priority level
     */
    public function getPriority(): int
    {
        return 50; // Medium priority
    }

    /**
     * Default line number update implementation.
     *
     * @param string $content Original content
     * @param int $line Line number (1-based)
     * @param string $new New line content
     * @param array $context Additional context
     * @return string Updated content
     */
    public function updateByLineNumber(string $content, int $line, string $new, array $context = []): string
    {
        $lines = $this->splitLines($content);

        if ($line < 1 || $line > count($lines)) {
            throw new \InvalidArgumentException("Line number {$line} is out of range");
        }

        $lines[$line - 1] = $new;

        return $this->joinLines($lines);
    }

    /**
     * Log operation for debugging.
     *
     * @param string $operation Operation name
     * @param array $data Operation data
     */
    protected function logOperation(string $operation, array $data = []): void
    {
        if (function_exists('logger')) {
            logger()->debug("FileUpdater Strategy: {$this->getName()}", [
                'operation' => $operation,
                'data' => $data,
            ]);
        }
    }
}