<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType {

    use n5s\PageForCustomPostType\Core\Api;

    /**
     * Check if the current page is a page for custom post type.
     *
     * @param string|null $postType Optional post type to check for specifically.
     */
    function is_page_for_custom_post_type(?string $postType = null): bool
    {
        return Plugin::getInstance()->getApi()->isPageForCustomPostType($postType);
    }

    /**
     * Get the custom post type associated with a page ID.
     *
     * @param int $pageId The page ID to check.
     * @return string|null The post type name, or null if not found.
     */
    function get_custom_post_type_for_page(int $pageId): ?string
    {
        return Plugin::getInstance()->getApi()->getPostTypeFromPageId($pageId);
    }

    /**
     * Get the page ID assigned to a custom post type.
     *
     * @param string|null $postType The post type name. If null, uses current query's post type.
     * @return int|null The page ID, or null if not found.
     */
    function get_page_id_for_custom_post_type(?string $postType = null): ?int
    {
        if ($postType === null) {
            $postType = $GLOBALS['wp_query']->{Api::QUERY_VAR_IS_PFCPT} ?? null;

            if (!\is_string($postType)) {
                return null;
            }
        }

        return Plugin::getInstance()->getApi()->getPageIdFromPostType($postType);
    }

    /**
     * Get the URL for a custom post type's archive page.
     *
     * @param string|null $postType The post type name. If null, uses current query's post type.
     * @return string|null The page URL, or null if not found.
     */
    function get_page_url_for_custom_post_type(?string $postType = null): ?string
    {
        $pageId = get_page_id_for_custom_post_type($postType);

        if (!$pageId) {
            return null;
        }

        $url = \get_permalink($pageId);

        return $url !== false ? $url : null;
    }
}

namespace {
    if (!\function_exists('is_page_for_custom_post_type')) {
        /**
         * Check if the current page is a page for custom post type.
         */
        function is_page_for_custom_post_type(?string $postType = null): bool
        {
            return \n5s\PageForCustomPostType\is_page_for_custom_post_type($postType);
        }
    }

    if (!\function_exists('get_custom_post_type_for_page')) {
        /**
         * Get the custom post type associated with a page ID.
         */
        function get_custom_post_type_for_page(int $pageId): ?string
        {
            return \n5s\PageForCustomPostType\get_custom_post_type_for_page($pageId);
        }
    }

    if (!\function_exists('get_page_id_for_custom_post_type')) {
        /**
         * Get the page ID assigned to a custom post type.
         */
        function get_page_id_for_custom_post_type(?string $postType = null): ?int
        {
            return \n5s\PageForCustomPostType\get_page_id_for_custom_post_type($postType);
        }
    }

    if (!\function_exists('get_page_url_for_custom_post_type')) {
        /**
         * Get the URL for a custom post type's archive page.
         */
        function get_page_url_for_custom_post_type(?string $postType = null): ?string
        {
            return \n5s\PageForCustomPostType\get_page_url_for_custom_post_type($postType);
        }
    }
}
