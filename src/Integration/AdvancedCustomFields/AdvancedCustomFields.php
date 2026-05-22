<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\AdvancedCustomFields;

use n5s\PageForCustomPostType\Integration\IntegrationInterface;

/**
 * Advanced Custom Fields integration composite.
 *
 * Registers a custom location type that exposes `<cpt>_page` values on the
 * `page_type` rule, so field groups can target PFCPT-bound pages.
 */
final class AdvancedCustomFields implements IntegrationInterface
{
    public function isSupported(): bool
    {
        return \function_exists('acf_register_location_type');
    }

    public function registerHooks(): void
    {
        add_action('acf/include_location_rules', [$this, 'registerLocationRules']);
    }

    public function registerLocationRules(int $acfFieldApiVersion): void
    {
        if ($acfFieldApiVersion !== 5) {
            return;
        }

        $store = acf_get_store('location-types');
        $store->remove('page_type');
        acf_register_location_type(LocationPageType::class);
    }
}
