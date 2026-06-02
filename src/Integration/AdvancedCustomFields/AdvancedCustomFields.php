<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\AdvancedCustomFields;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Integration\IntegrationInterface;

/**
 * Advanced Custom Fields integration.
 *
 * Extends the built-in `page_type` location rule with `<cpt>_page` values so
 * field groups can target PFCPT-bound pages.
 */
final class AdvancedCustomFields implements IntegrationInterface
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    public function isSupported(): bool
    {
        return \function_exists('acf_get_location_type');
    }

    public function registerHooks(): void
    {
        add_filter('acf/location/rule_values/type=page_type', [$this, 'addPageTypeValues'], 10, 2);
        add_filter('acf/location/match_rule/type=page_type', [$this, 'matchPageType'], 10, 4);
    }

    /**
     * Add `<cpt>_page` options to the Page Type rule values dropdown.
     *
     * @param array<string, string> $values
     * @param array<string, mixed> $rule
     * @return array<string, string>
     */
    public function addPageTypeValues(array $values, array $rule): array
    {
        foreach (array_keys($this->api->getPageIds()) as $postType) {
            $postTypeObject = get_post_type_object($postType);

            if ($postTypeObject && \is_string($postTypeObject->labels->archives)) {
                $values[$postType . '_page'] = $postTypeObject->labels->archives;
            }
        }

        return $values;
    }

    /**
     * Match a `page_type == <cpt>_page` rule against the current screen.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $screen
     * @param array<string, mixed> $fieldGroup
     */
    public function matchPageType(bool $match, array $rule, array $screen, array $fieldGroup): bool
    {
        if ($match) {
            return true;
        }

        if (!isset($screen['post_id'])) {
            return false;
        }

        $post = get_post($screen['post_id']);

        if (!$post instanceof \WP_Post) {
            return false;
        }

        foreach ($this->api->getPageIds() as $postType => $pageId) {
            if ($rule['value'] !== $postType . '_page') {
                continue;
            }

            $result = ($pageId === $post->ID);

            return ($rule['operator'] === '!=') ? !$result : $result;
        }

        return false;
    }
}
