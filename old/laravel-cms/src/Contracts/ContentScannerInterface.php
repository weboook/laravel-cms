<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * Content Scanner Interface
 *
 * Defines the contract for scanning and analyzing content files
 * within the Laravel application for CMS management.
 */
interface ContentScannerInterface
{
    /**
     * Scan directories for editable content files.
     *
     * @param array $paths
     * @return array
     */
    public function scan(array $paths): array;

    /**
     * Analyze content for CMS directives.
     *
     * @param string $content
     * @return array
     */
    public function analyze(string $content): array;

    /**
     * Extract editable regions from content.
     *
     * @param string $content
     * @return array
     */
    public function extractEditableRegions(string $content): array;
}