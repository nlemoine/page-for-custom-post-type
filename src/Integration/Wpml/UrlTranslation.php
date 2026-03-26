<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Wpml;

use n5s\PageForCustomPostType\Core\Api;
use WP_Post;
use WP_Query;

/**
 * WPML URL Translation integration.
 *
 * Adjusts language switcher URLs for PFCPT archive pages
 * so each language points to the correct translated page.
 */
final class UrlTranslation
{
    /**
     * Cache for computed URLs to avoid recalculation on multiple filter calls.
     *
     * @var array<string, string>|null
     */
    private ?array $urlCache = null;

    public function __construct(
        private readonly Api $api
    ) {
    }

    public function registerHooks(): void
    {
        // Different WPML language switcher filters depending on how it's rendered
        add_filter('wpml_ls_languages', [$this, 'filterLanguageSwitcherUrls'], 20);
        add_filter('icl_ls_languages', [$this, 'filterLanguageSwitcherUrls'], 20);
        add_filter('wpml_active_languages', [$this, 'filterLanguageSwitcherUrls'], 20);
    }

    /**
     * Fix language switcher URLs on PFCPT pages.
     *
     * @param array<string, array<string, mixed>> $languages
     * @return array<string, array<string, mixed>>
     */
    public function filterLanguageSwitcherUrls(array $languages): array
    {
        // Return cached URLs if already computed (filter may be called multiple times)
        if ($this->urlCache !== null) {
            return $this->applyUrlCache($languages);
        }

        global $wp_the_query;

        // Use the main query, not $wp_query which might be modified
        $mainQuery = $wp_the_query ?? $GLOBALS['wp_query'] ?? null;
        if (!$mainQuery instanceof WP_Query) {
            return $languages;
        }

        // Check if the main query has PFCPT flag
        $postType = $mainQuery->{Api::QUERY_VAR_IS_PFCPT} ?? null;
        if (!\is_string($postType) || $postType === '') {
            return $languages;
        }

        // Get the page ID for this post type (without translation filter)
        $pageId = $this->api->getPageIdFromPostType($postType, false);
        if ($pageId === null || $pageId <= 0) {
            return $languages;
        }

        // Save current language to restore later
        /** @var string|null $currentLanguage */
        $currentLanguage = apply_filters('wpml_current_language', null);

        // Build URL cache
        $this->urlCache = [];

        foreach ($languages as $langCode => $language) {
            $url = $this->getTranslatedPageUrl($pageId, $langCode);
            if ($url !== null) {
                $this->urlCache[$langCode] = $url;
            }
        }

        // Restore original language
        do_action('wpml_switch_language', $currentLanguage);

        return $this->applyUrlCache($languages);
    }

    /**
     * Get the URL for a translated PFCPT page.
     *
     * Note: This method switches WPML language context. Caller is responsible for restoring.
     */
    private function getTranslatedPageUrl(int $pageId, string $langCode): ?string
    {
        /** @var int|null $translatedId */
        $translatedId = apply_filters('wpml_object_id', $pageId, 'page', true, $langCode);

        if (!\is_int($translatedId) || $translatedId <= 0) {
            return null;
        }

        // Switch to target language to get correct page data and slug
        do_action('wpml_switch_language', $langCode);

        $translatedPage = get_post($translatedId);
        if (!$translatedPage instanceof WP_Post) {
            return null;
        }

        // Get the page slug in the target language
        $pageSlug = $translatedPage->post_name;

        // For hierarchical pages, build the full path
        if ($translatedPage->post_parent > 0) {
            $pageSlug = get_page_uri($translatedPage);
        }

        if (!\is_string($pageSlug) || $pageSlug === '') {
            return null;
        }

        /** @var string $langHomeUrl */
        $langHomeUrl = apply_filters('wpml_home_url', home_url('/'), $langCode);

        return trailingslashit($langHomeUrl) . $pageSlug . '/';
    }

    /**
     * Apply cached URLs to languages array.
     *
     * @param array<string, array<string, mixed>> $languages
     * @return array<string, array<string, mixed>>
     */
    private function applyUrlCache(array $languages): array
    {
        if ($this->urlCache === null) {
            return $languages;
        }

        foreach ($this->urlCache as $langCode => $url) {
            if (isset($languages[$langCode])) {
                $languages[$langCode]['url'] = $url;
            }
        }

        return $languages;
    }
}