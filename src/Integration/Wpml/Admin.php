<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Wpml;

/**
 * WPML Admin integration.
 *
 * Filters admin UI elements for WPML compatibility,
 * such as limiting page dropdowns to the default language.
 */
final class Admin
{
    public function registerHooks(): void
    {
        add_filter('pfcpt/dropdown_page_args', [$this, 'filterDropdownArgs']);
    }

    /**
     * Only show default language pages in dropdown.
     *
     * Temporarily switches WPML to the default language before the
     * dropdown query runs, then restores the original language after.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filterDropdownArgs(array $args): array
    {
        /** @var string|null $defaultLanguage */
        $defaultLanguage = apply_filters('wpml_default_language', null);

        if (empty($defaultLanguage)) {
            return $args;
        }

        do_action('wpml_switch_language', $defaultLanguage);

        // Restore language after wp_dropdown_pages() queries pages.
        $restoreLanguage = static function (array $pages) use (&$restoreLanguage): array {
            remove_filter('get_pages', $restoreLanguage, \PHP_INT_MAX);
            do_action('wpml_switch_language', null);
            return $pages;
        };

        add_filter('get_pages', $restoreLanguage, \PHP_INT_MAX);

        return $args;
    }
}
