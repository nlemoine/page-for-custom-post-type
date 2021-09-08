<?php

function is_page_for_custom_post_type(?string $post_type = null): bool
{
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    return $pfcpt->is_page_for_custom_post_type($post_type);
}

function get_custom_post_type_for_page($post_id)
{
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    $page_ids = $pfcpt->get_page_ids();

    $post_id = (int) $post_id;

    if (!in_array($post_id, $page_ids, true)) {
        return false;
    }
    return array_search($post_id, $page_ids, true);
}

function get_page_for_custom_post_type($post_type)
{
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    $page_ids = $pfcpt->get_page_ids();
    return $page_ids[$post_type] ?? false;
}

function get_page_for_custom_post_type_link($post_type)
{
    $page_id = get_page_for_custom_post_type($post_type);
    return $page_id ? get_permalink($page_id) : false;
}
