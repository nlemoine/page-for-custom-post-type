<?php

namespace HelloNico\PageForCustomPostType\Integrations;

use PLL_Language;
use \HelloNico\PageForCustomPostType\Plugin;

/**
 * Get translated page id cache key
 *
 * @param string $language_slug
 * @return string
 */
function get_translated_page_id_cache_key(string $language_slug): string {
    return Plugin::OPTION_PAGE_IDS . '_' . $language_slug;
}

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
    // Current language is not set
    $current_language = \pll_current_language();
    if(empty($current_language)) {
        return $page_ids;
    }

    $cache_key = get_translated_page_id_cache_key($current_language);
    $page_ids_for_current_language = \get_transient($cache_key);
    if($page_ids_for_current_language === false) {
        $page_ids_for_current_language = \array_filter(\array_map(function(int $id) use($current_language): ?int {
            return \pll_get_post($id, $current_language);
        }, $page_ids));

        \set_transient($cache_key, $page_ids_for_current_language);
    }

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

/**
 * Translate slugs
 *
 * @param array $slugs
 * @param PLL_Language $language
 * @return array
 */
function translate_slugs(array $slugs, PLL_Language $language): array {
    $default_language = \pll_default_language();

    // Page slug is already set for the default language
    if($language->slug === $default_language) {
        return $slugs;
    }

    $pfcpt = Plugin::get_instance();
    $page_ids = $pfcpt->get_page_ids(false);
    $post_types = array_keys($page_ids);
    foreach($slugs as $post_type => $post_type_slugs) {
        if(!in_array($post_type, $post_types, true)) {
            continue;
        }
        if(!isset($post_type_slugs['translations'])) {
            continue;
        }

        foreach($post_type_slugs['translations'] as $lang => $slug) {
            if($lang === $default_language) {
                continue;
            }
            $page_id = pll_get_post($page_ids[$post_type], $lang);
            if(empty($page_id)) {
                continue;
            }
            $page_slug = $pfcpt->get_page_slug($page_id);
            if(!$page_slug) {
                continue;
            }
            $slugs[$post_type]['translations'][$lang] = substr($page_slug, strlen($lang . '/'));
        }
    }
    return $slugs;
}
add_filter('pll_translated_slugs', __NAMESPACE__ . '\\translate_slugs', 10, 2);

/**
 * Flush Polylang slugs
 */
function flush_slugs() {
    $languages_list = pll_languages_list();
    if (!empty($languages_list)) {
        foreach ($languages_list as $language_slug) {
            $cache_key = get_translated_page_id_cache_key($language_slug);
            delete_transient($cache_key);
        }
    }
    delete_transient('pll_translated_slugs');
}
add_action('pfcpt/flush_rewrite_rules', __NAMESPACE__ . '\\flush_slugs');

/**
 * Get default language page id
 *
 * @param int $page_id
 * @return int
 */
function get_default_language_page_id(int $page_id): int {
    if(!empty(\pll_current_language())) {
        return $page_id;
    }
    $default_page_id = \pll_get_post($page_id, \pll_default_language());
    return $default_page_id ? $default_page_id : $page_id;
}
add_filter('pfcpt/post_type_from_id/page_id', __NAMESPACE__ . '\\get_default_language_page_id');

/**
 * Clear cache when a page for post type is saved (in case translations changes)
 *
 * @param int $post_id
 * @param WP_Post $post
 * @param array $translations
 * @return void
 */
function on_page_for_custom_post_type_change($post_id, $post, $translations) {
    $default_language = \pll_default_language();
    if(!isset($translations[$default_language])) {
        return;
    }

    $default_page_for_post_type_id = $translations[$default_language];
    $pfcpt = Plugin::get_instance();
    $page_ids = $pfcpt->get_page_ids(false);
    if(!in_array($default_page_for_post_type_id, $page_ids, true)) {
        return;
    }

    $post_type = array_search($default_page_for_post_type_id, $page_ids, true);

    // Flush cache/rules
    $pfcpt->flush_rewrite_rules($post_type);
}
add_action('pll_save_post', __NAMESPACE__ . '\\on_page_for_custom_post_type_change', 10, 3);
