<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Polylang;

/**
 * Polylang Admin integration.
 *
 * Filters admin UI elements for Polylang compatibility,
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
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filterDropdownArgs(array $args): array
    {
        $args['lang'] = pll_default_language();
        return $args;
    }
}
