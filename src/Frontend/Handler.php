<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Frontend;

use n5s\PageForCustomPostType\Core\Api;
use WP_Query;

/**
 * Handles query property modification and template hierarchy for PFCPT pages.
 */
final class Handler
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    /**
     * Add conditional properties to WP_Query object.
     *
     * Two properties are added:
     * - is_page_for_custom_post_type (string|false) Either the post type or false
     * - is_{$post_type}_page (bool) Whether the current page is a page for the post type
     */
    public function withQueryProperties(WP_Query $query): void
    {
        if (!$query->is_main_query()) {
            return;
        }

        $currentPageId = $this->api->getPageIdFromQuery($query);
        if (!$currentPageId) {
            return;
        }

        // Set default conditionals for all post types
        foreach ($this->api->getPostTypes() as $postType) {
            $query->{$this->api->getConditionalName($postType)} = false;
            // @phpstan-ignore property.notFound
            $query->{Api::QUERY_VAR_IS_PFCPT} = false;
        }

        $pageIds = $this->api->getPageIds();

        if (!\in_array($currentPageId, $pageIds, true)) {
            return;
        }

        $postType = array_search($currentPageId, $pageIds, true);

        if (empty($postType)) {
            return;
        }

        // Modify query conditionals
        $query->is_singular = false;
        $query->is_page = false;
        $query->is_home = true;
        $query->is_posts_page = true;
        $query->{$this->api->getConditionalName($postType)} = true;
        // @phpstan-ignore property.notFound
        $query->{Api::QUERY_VAR_IS_PFCPT} = $postType;
        $query->set('post_type', $postType);

        // Prevent WP from mistakenly thinking this is a front page
        // when 'posts' is set as show_on_front
        // @see https://github.com/WordPress/wordpress-develop/blob/781953641607c4d5b0743a6924af0e820fd54871/src/wp-includes/class-wp-query.php#L4323-L4325
        if (get_option('show_on_front') === 'posts') {
            add_filter('pre_option_show_on_front', static fn (): null => null);
        }

        add_filter('home_template_hierarchy', [$this, 'withHomeTemplateHierarchy']);
        add_filter('frontpage_template_hierarchy', '__return_empty_array');
        add_filter('body_class', [$this, 'filterBodyClass']);
    }

    /**
     * Replace 'blog' body class with 'home' and 'home-for-{post_type}' on PFCPT pages.
     *
     * WordPress core adds 'blog' when is_home() is true, but that class should
     * only apply to the actual page_for_posts. For custom post type pages, we
     * replace it with more specific classes.
     *
     * @param string[] $classes
     * @return string[]
     */
    public function filterBodyClass(array $classes): array
    {
        $wpQuery = $GLOBALS['wp_query'] ?? null;
        if (!$wpQuery instanceof WP_Query) {
            return $classes;
        }

        $postType = $wpQuery->{Api::QUERY_VAR_IS_PFCPT} ?? null;
        if (!\is_string($postType)) {
            return $classes;
        }

        $classes = array_diff($classes, ['blog']);
        $classes[] = 'home';
        $classes[] = "home-for-{$postType}";

        return array_values($classes);
    }

    /**
     * Change the template hierarchy on pages for custom post type.
     *
     * @param string[] $templates
     * @return string[]
     */
    public function withHomeTemplateHierarchy(array $templates): array
    {
        $wpQuery = $GLOBALS['wp_query'] ?? null;
        if (!$wpQuery instanceof WP_Query) {
            return $templates;
        }

        $postType = $wpQuery->{Api::QUERY_VAR_IS_PFCPT} ?? null;
        if (!\is_string($postType)) {
            return $templates;
        }

        // Match extension format of incoming templates (classic vs block themes)
        $first = reset($templates);
        $extension = \is_string($first) && str_ends_with($first, '.php') ? '.php' : '';

        return array_merge(["home-{$postType}{$extension}"], $templates);
    }
}
