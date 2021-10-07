<?php

namespace HelloNico\PageForCustomPostType\Integrations;

// Fix Yoast SEO breadcrumbs
\add_filter('wpseo_breadcrumb_indexables', __NAMESPACE__ . '\\fix_yoast_seo_breadcrumbs', 10, 2);

/**
 * Fix Yoast breadcrumbs
 *
 * @param [type] $indexables
 * @param [type] $context
 * @return void
 */
function fix_yoast_seo_breadcrumbs(array $indexables, $context)
{
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    $post_types = \array_keys($pfcpt->get_page_ids(false));
    if (empty($post_types)) {
        return $indexables;
    }

    if( !\is_singular($post_types) && !\is_tax() ) {
        return $indexables;
    }

    $post_type = \get_post_type();
    $post_type_object = \get_post_type_object($post_type);
    if($post_type_object->has_archive) {
        return $indexables;
    }

    $page_id = \get_page_for_custom_post_type($post_type);
    if(!$page_id) {
        return $indexables;
    }

    \array_splice( $indexables, 1, 0, [\YoastSEO()->meta->for_post( $page_id )->context->indexable] );

    return $indexables;
}

/**
 * Shortcircuit get_option to return the page ID for a custom post type
 *
 * @return void
 */
function set_page_for_custom_post_type(): void {
    // This will make Yoast SEO think it's a static page
    \add_filter('pre_option_show_on_front', function() {
        return 'page';
    });

    // Give it the right ID
    \add_filter('pre_option_page_for_posts', function () {
        return \get_queried_object_id();
    });
}

\add_action('pfcpt/template_redirect', __NAMESPACE__ . '\\set_page_for_custom_post_type');
