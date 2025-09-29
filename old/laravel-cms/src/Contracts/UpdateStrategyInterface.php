<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * Update Strategy Interface
 *
 * Defines the contract for different file update strategies.
 * Each strategy handles specific content types and update methods.
 */
interface UpdateStrategyInterface
{
    /**
     * Check if this strategy can handle the given content type.
     *
     * @param string $content The content to check
     * @param array $context Additional context information
     * @return bool True if strategy can handle the content
     */
    public function canHandle(string $content, array $context = []): bool;

    /**
     * Update content using the strategy.
     *
     * @param string $content The original content
     * @param string $old The old value to replace
     * @param string $new The new value
     * @param array $context Additional context information
     * @return string The updated content
     */
    public function updateContent(string $content, string $old, string $new, array $context = []): string;

    /**
     * Update content by line number.
     *
     * @param string $content The original content
     * @param int $line The line number (1-based)
     * @param string $new The new line content
     * @param array $context Additional context information
     * @return string The updated content
     */
    public function updateByLineNumber(string $content, int $line, string $new, array $context = []): string;

    /**
     * Update content by selector (CSS, XPath, etc.).
     *
     * @param string $content The original content
     * @param string $selector The selector to find content
     * @param string $new The new content
     * @param array $context Additional context information
     * @return string The updated content
     */
    public function updateBySelector(string $content, string $selector, string $new, array $context = []): string;

    /**
     * Update an attribute value.
     *
     * @param string $content The original content
     * @param string $selector The selector to find element
     * @param string $attribute The attribute name
     * @param string $value The new attribute value
     * @param array $context Additional context information
     * @return string The updated content
     */
    public function updateAttribute(string $content, string $selector, string $attribute, string $value, array $context = []): string;

    /**
     * Validate that the content is well-formed after update.
     *
     * @param string $content The content to validate
     * @param array $context Additional context information
     * @return array Validation results with 'valid' boolean and 'errors' array
     */
    public function validate(string $content, array $context = []): array;

    /**
     * Get the strategy priority (higher = more specific).
     *
     * @return int Priority level
     */
    public function getPriority(): int;

    /**
     * Get the strategy name.
     *
     * @return string Strategy name
     */
    public function getName(): string;
}