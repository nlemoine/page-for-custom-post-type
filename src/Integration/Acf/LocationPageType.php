<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Acf;

use ACF_Location_Page_Type;
use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Plugin;

/**
 * Custom ACF location rule for pages assigned to post types.
 */
class LocationPageType extends ACF_Location_Page_Type
{
    private Api $api;

    public function initialize(): void
    {
        parent::initialize();
        $this->api = Plugin::getInstance()->getApi();
    }

    /**
     * Match the location rule against the current screen.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $screen
     * @param array<string, mixed> $fieldGroup
     */
    // phpcs:ignore Syde.Functions.ArgumentTypeDeclaration.NoArgumentType
    public function match($rule, $screen, $fieldGroup): bool
    {
        $match = parent::match($rule, $screen, $fieldGroup);

        if ($match) {
            return $match;
        }

        // Check screen args
        if (!isset($screen['post_id'])) {
            return false;
        }

        $postId = $screen['post_id'];
        $post = \get_post($postId);

        if (!$post instanceof \WP_Post) {
            return false;
        }

        $pageIds = $this->api->getPageIds();

        if (empty($pageIds)) {
            return false;
        }

        $result = null;

        foreach ($pageIds as $postType => $pageId) {
            if ($rule['value'] === $postType . '_page') {
                $result = ($pageId === $post->ID);
                break;
            }
        }

        if ($result === null) {
            return false;
        }

        // Reverse result for "!=" operator
        if ($rule['operator'] === '!=') {
            return !$result;
        }

        return $result;
    }

    /**
     * Get available values for the location rule.
     *
     * @param array<string, mixed> $rule
     * @return array<string, string>
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Syde.Functions.ArgumentTypeDeclaration.NoArgumentType
    public function get_values($rule): array
    {
        $values = parent::get_values($rule);
        $postTypes = \array_keys($this->api->getPageIds());

        if (empty($postTypes)) {
            return $values;
        }

        foreach ($postTypes as $postType) {
            $postTypeObject = \get_post_type_object($postType);

            if ($postTypeObject) {
                $values[$postType . '_page'] = $postTypeObject->labels->archives;
            }
        }

        return $values;
    }
}
