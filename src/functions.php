<?php

function is_page_for_custom_post_type(?string $post_type = null): bool
{
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    return $pfcpt->is_page_for_custom_post_type($post_type);
}

function get_custom_post_type_for_page($post_id): ?string
{
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    return $pfcpt->get_post_type_from_page_id((int) $post_id);
}

function get_page_for_custom_post_type(?string $post_type): ?int
{
    if (is_null($post_type)) {
        return null;
    }
    $pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    return $pfcpt->get_page_id_from_post_type($post_type);
}

function get_page_for_custom_post_type_link(?string $post_type): ?string
{
    if (is_null($post_type)) {
        return null;
    }
    $page_id = get_page_for_custom_post_type($post_type);
    return $page_id ? get_permalink($page_id) : null;
}
