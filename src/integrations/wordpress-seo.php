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
function fix_yoast_seo_breadcrumbs($indexables, $context)
{
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    $post_types = \array_keys($pfcpt->get_page_ids());
    if (empty($post_types)) {
        return $indexables;
    }
    if( !\is_singular($post_types) && !\is_tax() ) {
        return $indexables;
    }

    $post_type = \get_post_type();
    $page_id = \get_page_for_custom_post_type($post_type);
    if(!$page_id) {
        return $indexables;
    }

    \array_splice( $indexables, 1, 0, [\YoastSEO()->meta->for_post( $page_id )->context->indexable] );

    return $indexables;
}
