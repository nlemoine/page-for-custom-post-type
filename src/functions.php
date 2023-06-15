<?php

function is_page_for_custom_post_type(?string $post_type = null): bool
{
    $pfcpt = \n5s\PageForCustomPostType\Plugin::get_instance();
    return $pfcpt->is_page_for_custom_post_type($post_type);
}

function get_custom_post_type_for_page(int $post_id): ?string
{
    $pfcpt = \n5s\PageForCustomPostType\Plugin::get_instance();
    return $pfcpt->get_post_type_from_page_id((int) $post_id);
}

function get_page_id_for_custom_post_type(?string $post_type): ?int
{
    if ($post_type === null) {
        return null;
    }
    $pfcpt = \n5s\PageForCustomPostType\Plugin::get_instance();
    return $pfcpt->get_page_id_from_post_type($post_type);
}

function get_page_url_for_custom_post_type(?string $post_type): ?string
{
    if ($post_type === null) {
        return null;
    }
    $page_id = get_page_id_for_custom_post_type($post_type);
    if ($page_id === null) {
        return null;
    }
    $permalink = get_permalink($page_id);
    if ($permalink === false) {
        return null;
    }
    return $permalink;
}
