<?php

namespace HelloNico\PageForCustomPostType\Integrations;

use HelloNico\PageForCustomPostType\Plugin;
use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Repositories\Indexable_Repository;

// Fix Yoast SEO breadcrumbs
\add_filter('wpseo_breadcrumb_indexables', __NAMESPACE__ . '\\fix_home_breadcrumbs', 10, 2);
\add_filter('wpseo_breadcrumb_indexables', __NAMESPACE__ . '\\fix_taxonomy_breadcrumbs', 10, 2);
\add_filter('wpseo_breadcrumb_indexables', __NAMESPACE__ . '\\fix_post_breadcrumbs', 10, 2);

/**
 * Fix Yoast breadcrumbs on post
 *
 * @param Meta_Tags_Context $context
 * @return array
 */
function fix_post_breadcrumbs(array $indexables, $context)
{
    $current_post_type = $context->indexable->object_sub_type ?? null;
    if (!\is_singular($current_post_type)) {
        return $indexables;
    }

    $pfcpt = Plugin::get_instance();

    // Check if current post type has a page for custom post type
    $page_for_post_type_id = $pfcpt->get_page_id_from_post_type($current_post_type, \function_exists('PLL'));
    if (!$page_for_post_type_id) {
        return $indexables;
    }

    $yoast = \YoastSEO();

    /** @var Indexable_Repository $indexable_repository */
    $indexable_repository = $yoast->classes->get(Indexable_Repository::class);
    $page_for_post_type_indexable = $indexable_repository->find_by_id_and_type($page_for_post_type_id, 'post');
    if (!$page_for_post_type_indexable) {
        return $indexables;
    }

    \array_splice($indexables, $yoast->helpers->options->get('breadcrumbs-home') ? 1 : 0, 0, [$page_for_post_type_indexable]);

    return $indexables;
}

/**
 * Fix Yoast breadcrumbs on taxonomy
 *
 * @param Meta_Tags_Context $context
 * @return array
 */
function fix_taxonomy_breadcrumbs(array $indexables, $context)
{
    $current_taxonomy = $context->indexable->object_sub_type ?? null;
    if (!\is_tax($current_taxonomy)) {
        return $indexables;
    }

    $current_post_type = \get_post_type();
    if (!$current_post_type) {
        return $indexables;
    }

    $yoast = \YoastSEO();

    // Check if current taxonomy is the main taxonomy for this post type
    $main_taxonomy_for_post_type = $yoast->helpers->options->get('post_types-' . $current_post_type . '-maintax');
    if ($main_taxonomy_for_post_type !== $current_taxonomy) {
        return $indexables;
    }

    // Check if current taxonomy is in this post type
    $taxonomies = \get_object_taxonomies($current_post_type);
    if (!\in_array($current_taxonomy, $taxonomies, true)) {
        return $indexables;
    }

    $pfcpt = Plugin::get_instance();

    // Check if current post type has a page for custom post type
    $page_for_post_type_id = $pfcpt->get_page_id_from_post_type($current_post_type, \function_exists('PLL'));
    if (!$page_for_post_type_id) {
        return $indexables;
    }

    /** @var Indexable_Repository $indexable_repository */
    $indexable_repository = $yoast->classes->get(Indexable_Repository::class);
    $page_for_post_type_indexable = $indexable_repository->find_by_id_and_type($page_for_post_type_id, 'post');
    if (!$page_for_post_type_indexable) {
        return $indexables;
    }

    \array_splice($indexables, $yoast->helpers->options->get('breadcrumbs-home') ? 1 : 0, 0, [$page_for_post_type_indexable]);

    return $indexables;
}

/**
 * Fix Yoast breadcrumbs on home
 *
 * @param Meta_Tags_Context $context
 * @return array
 */
function fix_home_breadcrumbs(array $indexables, $context)
{
    $pfcpt = Plugin::get_instance();
    if (!$pfcpt->is_query_page_for_custom_post_type()) {
        return $indexables;
    }

    $yoast = \YoastSEO();
    if ($yoast->helpers->current_page->get_page_type() !== 'Home_Page') {
        return $indexables;
    }

    /** @var Indexable_Repository $indexable_repository */
    $indexable_repository = $yoast->classes->get(Indexable_Repository::class);
    $static_ancestors = [];

    // Push home to breadcrumbs because our PFCPT page is considered a Home_Page type
    // @see https://github.com/Yoast/wordpress-seo/blob/250d40f33bc5423fa3a937bf5734085c57035718/src/generators/breadcrumbs-generator.php#L97-L108
    if ($yoast->helpers->options->get('breadcrumbs-home')) {
        $front_page_id = $yoast->helpers->current_page->get_front_page_id();

        if ($front_page_id === 0) {
            $static_ancestors[] = $indexable_repository->find_for_home_page();
        } else {
            $static_ancestor = $indexable_repository->find_by_id_and_type($front_page_id, 'post');
            if ($static_ancestor->post_status !== 'unindexed') {
                $static_ancestors[] = $static_ancestor;
            }
        }
    }

    if (!empty($static_ancestors)) {
        \array_unshift($indexables, ...$static_ancestors);
    }

    return $indexables;
}

/**
 * Shortcircuit get_option to return the page ID for a custom post type
 */
function set_page_for_custom_post_type(): void
{
    $pfcpt = Plugin::get_instance();
    if (!$pfcpt->is_query_page_for_custom_post_type()) {
        return;
    }

    /**
     * Hijack Yoast SEO for_current_page logic which determines the current indexable
     * @see \Yoast\WP\SEO\Repositories\Indexable_Repository::for_current_page
     */
    \add_filter('pre_option_show_on_front', function () {
        return 'page';
    });

    /**
     * Hijack Yoast SEO for_current_page logic which determines the current indexable
     * @see \Yoast\WP\SEO\Repositories\Indexable_Repository::for_current_page
     */
    \add_filter('pre_option_page_for_posts', function () {
        return \get_queried_object_id();
    });
}

\add_action('pfcpt/template_redirect', __NAMESPACE__ . '\\set_page_for_custom_post_type');
