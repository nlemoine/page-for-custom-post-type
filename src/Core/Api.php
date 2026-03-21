<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Core;

use WP_Post_Type;
use WP_Query;

/**
 * Read-only data access layer for page/post-type mappings.
 */
final class Api
{
    public const QUERY_VAR_IS_PFCPT = 'is_page_for_custom_post_type';
    public const OPTION_PREFIX = 'page_for_';
    public const OPTION_SUFFIX_USE_SLUG = '_use_slug';
    public const OPTION_PAGE_IDS = 'pages_for_custom_post_type';

    /**
     * Get page ID from query.
     */
    public function getPageIdFromQuery(WP_Query $query): ?int
    {
        // Check both 'pagename' and 'name' query vars.
        // Custom permastructs (e.g. extended-cpts) may set 'name' instead of 'pagename'.
        if (
            (
                !empty($query->query_vars['pagename'])
                || !empty($query->query_vars['name'])
            )
            && $query->queried_object_id
        ) {
            return (int) $query->queried_object_id;
        }

        if (isset($query->query_vars['page_id']) && is_numeric($query->query_vars['page_id'])) {
            return (int) $query->query_vars['page_id'];
        }

        return null;
    }

    /**
     * Check if current page is for a specific post type.
     */
    public function isPageForCustomPostType(?string $postType = null): bool
    {
        if (!$this->isQueryPageForCustomPostType()) {
            return false;
        }

        $currentPostType = $GLOBALS['wp_query']->{self::QUERY_VAR_IS_PFCPT} ?? null;

        if ($postType === null) {
            return (bool) $currentPostType;
        }

        return $postType === $currentPostType;
    }

    /**
     * Check if the query is for a page for custom post type.
     */
    public function isQueryPageForCustomPostType(?WP_Query $query = null): bool
    {
        $q = $query ?? $GLOBALS['wp_query'] ?? null;

        if (!$q instanceof WP_Query) {
            return false;
        }

        return !empty($q->{self::QUERY_VAR_IS_PFCPT});
    }

    /**
     * Get post type from page ID.
     */
    public function getPostTypeFromPageId(int $pageId): ?string
    {
        $pageIds = $this->getPageIds();
        $pageId = \apply_filters('pfcpt/post_type_from_id/page_id', $pageId);

        $postType = \array_search($pageId, $pageIds, true);

        return is_string($postType) ? $postType : null;
    }

    /**
     * Get page ID for a post type.
     */
    public function getPageIdFromPostType(string $postType, bool $applyFilters = true): ?int
    {
        return $this->getPageIds($applyFilters)[$postType] ?? null;
    }

    /**
     * Get all page IDs mapped to post types.
     *
     * @return array<string, int>
     */
    public function getPageIds(bool $applyFilters = true): array
    {
        $pageIds = (array) \get_option(self::OPTION_PAGE_IDS, []);
        $pageIds = $applyFilters ? \apply_filters('pfcpt/page_ids', $pageIds) : $pageIds;

        return \array_map(intval(...), $pageIds);
    }

    /**
     * Get option name for a post type.
     */
    public function getOptionName(string|WP_Post_Type $postType): string
    {
        $name = $postType instanceof WP_Post_Type ? $postType->name : $postType;

        return self::OPTION_PREFIX . $name;
    }

    /**
     * Get option name for the "use page slug" setting.
     */
    public function getUseSlugOptionName(string|WP_Post_Type $postType): string
    {
        return $this->getOptionName($postType) . self::OPTION_SUFFIX_USE_SLUG;
    }

    /**
     * Check if the page slug should be used as the CPT rewrite base.
     *
     * Defaults to false when option is not set.
     */
    public function shouldUsePageSlug(string $postType): bool
    {
        $optionName = $this->getUseSlugOptionName($postType);

        return (bool) \get_option($optionName, false);
    }

    /**
     * Get all eligible custom post types.
     *
     * @return array<string, WP_Post_Type>
     */
    public function getPostTypes(): array
    {
        return \get_post_types(
            [
                'publicly_queryable' => true,
                '_builtin' => false,
            ],
            'objects'
        );
    }

    /**
     * Check if a post type should be considered for PFCPT.
     */
    public function shouldConsiderPostType(WP_Post_Type $postType): bool
    {
        return !$postType->_builtin && $postType->publicly_queryable;
    }

    /**
     * Get the conditional property name for a post type.
     */
    public function getConditionalName(string|WP_Post_Type $postType): string
    {
        $name = $postType instanceof WP_Post_Type ? $postType->name : $postType;

        return "is_{$name}_page";
    }
}
