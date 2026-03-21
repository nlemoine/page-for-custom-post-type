<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Core;

/**
 * Manages rewrite rules, page slugs, and related caching.
 */
final class RewriteManager
{
    private const SLUG_CACHE_SUFFIX = '_slug';

    public function __construct(
        private readonly Api $api
    ) {
    }

    /**
     * Flush rewrite rules for a post type.
     */
    public function flushRewriteRules(string $postType): void
    {
        \do_action('pfcpt/flush_rewrite_rules', $postType);

        $this->clearPageSlugCache($postType);

        // Delete rewrite rules, will be regenerated on next request
        \delete_option('rewrite_rules');
    }

    /**
     * Get page slug from page ID.
     */
    public function getPageSlug(int $pageId): ?string
    {
        $pageUrl = \get_permalink($pageId);

        if ($pageUrl === false) {
            return null;
        }

        $pagePath = \parse_url($pageUrl, PHP_URL_PATH);

        return \is_string($pagePath) ? \trim($pagePath, '/') : null;
    }

    /**
     * Get cached page slug for a post type, or compute and cache it.
     */
    public function getCachedPageSlug(string $postType): ?string
    {
        $cacheKey = $this->getPageSlugCacheKey($postType);
        $slug = \get_transient($cacheKey);

        if ($slug !== false) {
            return $slug ?: null;
        }

        $pageId = $this->api->getPageIdFromPostType($postType, false);

        if (!$pageId) {
            return null;
        }

        // Make sure it's published
        if (\get_post_status($pageId) !== 'publish') {
            return null;
        }

        $slug = $this->getPageSlug($pageId);
        \set_transient($cacheKey, $slug ?? '', 0);

        return $slug;
    }

    /**
     * Clear the cached page slug for a post type.
     */
    public function clearPageSlugCache(string $postType): void
    {
        \delete_transient($this->getPageSlugCacheKey($postType));
    }

    /**
     * Add rewrite tags for a post type.
     */
    public function addRewriteTags(string $postType): void
    {
        \remove_rewrite_tag("%{$postType}%");
        // Exclude "page" from regex so pagination works
        \add_rewrite_tag("%{$postType}%", '(?!page)([^/]+)', "{$postType}=");
    }

    /**
     * Get cache key for page slug.
     */
    public function getPageSlugCacheKey(string $postType): string
    {
        return Api::OPTION_PREFIX . $postType . self::SLUG_CACHE_SUFFIX;
    }
}
