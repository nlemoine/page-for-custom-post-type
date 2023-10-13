<?php

namespace n5s\PageForCustomPostType\Integrations;

use n5s\PageForCustomPostType\Plugin;
use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Main;
use Yoast\WP\SEO\Models\Indexable;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Surfaces\Values\Meta;

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
    if (!$current_post_type || !\is_singular($current_post_type)) {
        return $indexables;
    }

    $pfcpt = Plugin::get_instance();

    // Check if current post type has a page for custom post type
    $page_for_post_type_id = $pfcpt->get_page_id_from_post_type($current_post_type, \function_exists('PLL'));
    if (!$page_for_post_type_id) {
        return $indexables;
    }

    /** @var Main $yoast */
    $yoast = \YoastSEO();

    /** @var Meta|false $page_for_post_type_meta */
    $page_for_post_type_meta = $yoast->meta->for_post($page_for_post_type_id);
    if (!$page_for_post_type_meta) {
        return $indexables;
    }

    // Insert page for custom post type indexable after home indexable
    \array_splice(
        $indexables,
        $yoast->helpers->options->get('breadcrumbs-home') ? 1 : 0,
        0,
        [$page_for_post_type_meta->indexable]
    );

    return $indexables;
}

/**
 * Fix Yoast breadcrumbs on taxonomy
 *
 * @return array
 */
function fix_taxonomy_breadcrumbs(array $indexables, Meta_Tags_Context $context)
{
    $current_taxonomy = $context->indexable->object_sub_type ?? null;
    if (!\is_tax($current_taxonomy)) {
        return $indexables;
    }

    $current_post_type = \get_post_type();
    if (!$current_post_type) {
        return $indexables;
    }

    /** @var Main $yoast */
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

    /** @var Meta|false $page_for_post_type_meta */
    $page_for_post_type_meta = $yoast->meta->for_post($page_for_post_type_id);
    if (!$page_for_post_type_meta) {
        return $indexables;
    }

    \array_splice(
        $indexables,
        $yoast->helpers->options->get('breadcrumbs-home') ? 1 : 0,
        0,
        [$page_for_post_type_meta->indexable]
    );

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
    // @see https://github.com/Yoast/wordpress-seo/blob/c936afcfb8b32cdab8218431ea09f19e87470cab/src/generators/breadcrumbs-generator.php#L96-L111
    $breadcrumbs_home = $yoast->helpers->options->get('breadcrumbs-home');
    if ($breadcrumbs_home !== '') {
        $front_page_id = $yoast->helpers->current_page->get_front_page_id();
        if ($front_page_id === 0) {
            $home_page_ancestor = $indexable_repository->find_for_home_page();
            if (\is_a($home_page_ancestor, Indexable::class)) {
                $static_ancestors[] = $home_page_ancestor;
            }
        } else {
            $static_ancestor = $indexable_repository->find_by_id_and_type($front_page_id, 'post');
            if (\is_a($static_ancestor, Indexable::class) && $static_ancestor->post_status !== 'unindexed') {
                $static_ancestors[] = $static_ancestor;
            }
        }
    }

    if (!empty($static_ancestors)) {
        \array_unshift($indexables, ...$static_ancestors);
    }

    return $indexables;
}

function add_filter_once($hook, $callback, $priority = 10, $args = 1)
{
    $singular = function () use ($hook, $callback, $priority, $args, &$singular) {
        \call_user_func_array($callback, \func_get_args());
        \remove_filter($hook, $singular, $priority);
    };

    return \add_filter($hook, $singular, $priority, $args);
}

/**
 * Shortcircuit get_option to return the page ID for a custom post type
 *
 * @see https://github.com/Yoast/wordpress-seo/pull/18222
 */
function set_page_for_custom_post_type(): void
{
    $pfcpt = Plugin::get_instance();
    if (!$pfcpt->is_query_page_for_custom_post_type()) {
        return;
    }

    add_filter('wpseo_frontend_page_type_simple_page_id', function() {
        return get_queried_object_id();
    });

    /**
     * Trick Yoast SEO for_current_page logic which determines the current indexable
     *
     * @see \Yoast\WP\SEO\Repositories\Indexable_Repository::for_current_page
     *
     * @param mixed $value
     * @return mixed
     */
    if (get_option('show_on_front') === 'page') {
        \add_filter('pre_option_show_on_front', function ($value) {
            $bt = \debug_backtrace();
            if (
                isset($bt[3]['file'])
                && str_ends_with($bt[3]['file'], 'wordpress-seo/src/helpers/current-page-helper.php')
            ) {
                return null;
            }
            return $value;
        });
    }

    // /**
    //  * Trick Yoast SEO for_current_page logic which determines the current indexable
    //  *
    //  * @see \Yoast\WP\SEO\Repositories\Indexable_Repository::for_current_page
    //  *
    //  * @param mixed $value
    //  * @return mixed
    //  */
    // \add_filter('pre_option_page_for_posts', function ($value) {
    //     global $wp_current_filter;
    //     if (
    //         \is_array($wp_current_filter)
    //         && isset($wp_current_filter[1])
    //         && $wp_current_filter[1] === 'wp_robots'
    //     ) {
    //         $bt = \debug_backtrace();
    //         if (
    //             isset($bt[3])
    //             && isset($bt[3]['file'])
    //             && \basename($bt[3]['file']) === 'current-page-helper.php'
    //         ) {
    //             return \get_queried_object_id();
    //         }
    //     }
    //     // return \get_queried_object_id();
    //     return $value;
    // });
}

\add_action('wp', __NAMESPACE__ . '\\set_page_for_custom_post_type');
