<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Frontend;

use n5s\PageForCustomPostType\Core\Api;
use WP_Query;
use wpdb;

/**
 * Handles SQL query filtering and menu navigation highlighting.
 */
final class QueryFilter
{
    public function __construct(
        private readonly Api $api,
        private readonly wpdb $wpdb
    ) {
    }

    /**
     * Remove page ID condition from WHERE clause.
     *
     * When viewing a PFCPT page, WordPress initially queries for the page itself.
     * We need to remove that condition so it queries the CPT posts instead.
     */
    public function filterPostsWhere(string $where, WP_Query $query): string
    {
        if (!$this->api->isQueryPageForCustomPostType($query)) {
            return $where;
        }

        $currentPageId = $this->api->getPageIdFromQuery($query);

        if (!$currentPageId) {
            return $where;
        }

        if (!in_array($currentPageId, $this->api->getPageIds(), true)) {
            return $where;
        }

        return str_replace(
            "AND ({$this->wpdb->posts}.ID = '{$currentPageId}')",
            '',
            $where
        );
    }

    /**
     * Set current ancestor class on menu items.
     *
     * When viewing a single CPT post, mark the PFCPT page menu item as ancestor.
     *
     * @param \WP_Post[] $menuItems
     * @param object $args
     * @return \WP_Post[]
     */
    public function withCurrentAncestor(array $menuItems, object $args): array
    {
        global $wp_query;

        if (!$wp_query instanceof WP_Query || !$wp_query->is_singular) {
            return $menuItems;
        }

        $pageIds = $this->api->getPageIds();

        foreach ($menuItems as $key => $menuItem) {
            if (!$this->shouldMarkAsAncestor($menuItem, $wp_query, $pageIds)) {
                continue;
            }

            // @phpstan-ignore property.notFound
            $classes = is_array($menuItems[$key]->classes) ? $menuItems[$key]->classes : [];
            $classes[] = 'current-menu-ancestor';
            $menuItems[$key]->classes = $classes;
            // @phpstan-ignore property.notFound
            $menuItems[$key]->current_item_ancestor = true;
        }

        return $menuItems;
    }

    /**
     * Check if a menu item should be marked as current ancestor.
     *
     * @param array<string, int> $pageIds
     */
    private function shouldMarkAsAncestor(object $menuItem, WP_Query $query, array $pageIds): bool
    {
        if (($menuItem->type ?? '') !== 'post_type') {
            return false;
        }

        $rawObjectId = $menuItem->object_id ?? 0;
        if (!is_numeric($rawObjectId)) {
            return false;
        }
        $menuObjectId = (int) $rawObjectId;
        if ($menuObjectId === 0) {
            return false;
        }

        if (!in_array($menuObjectId, $pageIds, true)) {
            return false;
        }

        if (empty($query->query['post_type'])) {
            return false;
        }

        return $this->api->getPostTypeFromPageId($menuObjectId) === $query->query['post_type'];
    }
}
