<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * Translation Manager Interface
 *
 * Defines the contract for managing multi-language content
 * within the CMS system.
 */
interface TranslationManagerInterface
{
    /**
     * Get translated content for a specific locale.
     *
     * @param string $key
     * @param string $locale
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, string $locale = null, $default = null);

    /**
     * Set translated content for a specific locale.
     *
     * @param string $key
     * @param mixed $value
     * @param string $locale
     * @return bool
     */
    public function set(string $key, $value, string $locale = null): bool;

    /**
     * Get all available locales.
     *
     * @return array
     */
    public function getAvailableLocales(): array;

    /**
     * Get the current active locale.
     *
     * @return string
     */
    public function getCurrentLocale(): string;
}