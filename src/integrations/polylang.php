<?php

namespace HelloNico\PageForCustomPostType\Integrations;

/**
 * Trick Polylang to make it think it's a page for posts.
 *
 * Change `$wp_query` before Polylang hook into `pll_pre_translation_url` and restore the original value after.
 * It will retrieve the translated URLs with no extra queries
 *
 * @see https://github.com/polylang/polylang/blob/88ee8ed65af4f92c0225e4faa46d5f3e640173ba/frontend/frontend-static-pages.php#L122-L146
 *
 * @param string $url               not used
 * @param object $language          language in which we want the translation
 * @param int    $queried_object_id id of the queried object
 *
 * @return string
 */
function set_is_posts_page($url, $language, $queried_object_id)
{
    $GLOBALS['pfcpt_is_posts_page'] = $GLOBALS['wp_query']->is_posts_page;
    $GLOBALS['wp_query']->is_posts_page = true;
    return $url;
}
function reset_is_posts_page($url, $language, $queried_object_id)
{
    $GLOBALS['wp_query']->is_posts_page = $GLOBALS['pfcpt_is_posts_page'];
    unset($GLOBALS['pfcpt_is_posts_page']);
    return $url;
}
\add_filter('pll_pre_translation_url', __NAMESPACE__ . '\\set_is_posts_page', 9, 3);
\add_filter('pll_pre_translation_url', __NAMESPACE__ . '\\reset_is_posts_page', 11, 3);


/**
 * Set translasted IDs
 *
 * @param array $page_ids
 *
 * @return array
 */
function set_translated_page_id(array $page_ids): array {
    return $page_ids;
    $default_lang = \pll_default_language();
    $current_language = \pll_current_language();

    if($default_lang === $current_language) {
        return $page_ids;
    }

    $cache_key = \HelloNico\PageForCustomPostType\Plugin::OPTION_PAGE_IDS . '_' . $current_language;
    // $page_ids_for_current_language = \get_transient($cache_key);
    // if($page_ids_for_current_language === false) {
        $page_ids_for_current_language = \array_filter(\array_map(function(int $id) use($current_language): ?int {
            return \pll_get_post($id, $current_language);
        }, $page_ids));

        // \set_transient($cache_key, $page_ids_for_current_language);
    // }

    return $page_ids_for_current_language;
}
\add_filter('pfcpt/page_ids', __NAMESPACE__ . '\\set_translated_page_id', 10);

/**
 * Only show default language pages
 *
 * @param array $args
 *
 * @return array
 */
function set_dropdown_args(array $args): array {
    $args['lang'] = \pll_default_language();
    return $args;
}
add_filter('pfcpt/dropdown_page_args', __NAMESPACE__ . '\\set_dropdown_args');
