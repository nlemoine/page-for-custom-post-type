<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Polylang;

use PLL_Language;

/**
 * Polylang URL Translation integration.
 *
 * Works around Polylang's translation URL generation by temporarily
 * tricking it into treating PFCPT pages as posts pages.
 *
 * @see https://github.com/polylang/polylang/blob/88ee8ed65af4f92c0225e4faa46d5f3e640173ba/frontend/frontend-static-pages.php#L122-L146
 */
final class UrlTranslation
{
    public function registerHooks(): void
    {
        \add_filter('pll_pre_translation_url', [$this, 'beforeTranslationUrl'], 9, 3);
        \add_filter('pll_pre_translation_url', [$this, 'afterTranslationUrl'], 11, 3);
    }

    /**
     * Set is_posts_page before Polylang processes the URL.
     */
    public function beforeTranslationUrl(string $url, PLL_Language $_language, int $_queriedObjectId): string
    {
        if (!\is_home()) {
            return $url;
        }

        $wpQuery = $GLOBALS['wp_query'] ?? null;

        if (!$wpQuery instanceof \WP_Query) {
            return $url;
        }

        $GLOBALS['pfcpt_is_posts_page'] = $wpQuery->is_posts_page;
        $wpQuery->is_posts_page = true;

        return $url;
    }

    /**
     * Reset is_posts_page after Polylang URL generation.
     */
    public function afterTranslationUrl(string $url, PLL_Language $_language, int $_queriedObjectId): string
    {
        if (!\is_home()) {
            return $url;
        }

        $wpQuery = $GLOBALS['wp_query'] ?? null;

        if (!$wpQuery instanceof \WP_Query) {
            return $url;
        }

        $savedValue = $GLOBALS['pfcpt_is_posts_page'] ?? false;
        $wpQuery->is_posts_page = \is_bool($savedValue) ? $savedValue : false;
        unset($GLOBALS['pfcpt_is_posts_page']);

        return $url;
    }
}
