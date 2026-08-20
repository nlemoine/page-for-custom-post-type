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
        do_action('pfcpt/flush_rewrite_rules', $postType);

        $this->clearPageSlugCache($postType);

        // Delete rewrite rules, will be regenerated on next request
        delete_option('rewrite_rules');
    }

    /**
     * Get page slug from page ID.
     *
     * The permalink is used rather than get_page_uri() so that translation
     * plugins filtering it are taken into account. Its path carries the rewrite
     * root (index.php/ on index permalinks), which is not part of the page path
     * and would be prepended a second time by add_permastruct(), so it is
     * stripped here.
     */
    public function getPageSlug(int $pageId): ?string
    {
        /** @var \WP_Rewrite */
        global $wp_rewrite;

        $pageUrl = get_permalink($pageId);

        if ($pageUrl === false) {
            return null;
        }

        $pagePath = parse_url($pageUrl, \PHP_URL_PATH);

        if (!\is_string($pagePath)) {
            return null;
        }

        $pagePath = trim($pagePath, '/');
        $root = trim($wp_rewrite->root, '/');

        if ($root !== '' && str_starts_with($pagePath, $root . '/')) {
            $pagePath = substr($pagePath, \strlen($root) + 1);
        }

        // Plain permalinks: the permalink has no path to speak of.
        return $pagePath === '' ? null : $pagePath;
    }

    /**
     * Get cached page slug for a post type, or compute and cache it.
     */
    public function getCachedPageSlug(string $postType): ?string
    {
        $cacheKey = $this->getPageSlugCacheKey($postType);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return \is_string($cached) && $cached !== '' ? $cached : null;
        }

        $pageId = $this->api->getPageIdFromPostType($postType, false);

        if (!$pageId) {
            return null;
        }

        // Make sure it's published
        if (get_post_status($pageId) !== 'publish') {
            return null;
        }

        $slug = $this->getPageSlug($pageId);
        set_transient($cacheKey, $slug ?? '', 0);

        return $slug;
    }

    /**
     * Clear the cached page slug for a post type.
     */
    public function clearPageSlugCache(string $postType): void
    {
        delete_transient($this->getPageSlugCacheKey($postType));
    }

    /**
     * Register the archive pagination rule for a post type's page.
     *
     * The plugin disables has_archive and serves the archive from the page, so
     * core never generates a pagination rule for that base. When the post type
     * is rebased on the page slug, /{page}/page/2/ is then swallowed by the
     * single rule (/{page}/%postname%/ matches with the post name set to
     * "page") and 404s. The same happens with a custom permastruct, wherever
     * the tag right after the page slug is greedy enough to match "page".
     *
     * Registering the rule on top resolves the URL to the page itself, which
     * is what core's generic page rule would have done without the collision.
     */
    public function addArchivePaginationRule(string $postType): void
    {
        /** @var \WP_Rewrite */
        global $wp_rewrite;

        $pageSlug = $this->getCachedPageSlug($postType);

        if ($pageSlug === null || $pageSlug === '') {
            return;
        }

        add_rewrite_rule(
            \sprintf(
                '%s%s/%s/?([0-9]{1,})/?$',
                $wp_rewrite->root,
                $pageSlug,
                $wp_rewrite->pagination_base
            ),
            \sprintf('index.php?pagename=%s&paged=$matches[1]', $pageSlug),
            'top'
        );
    }

    /**
     * Get cache key for page slug.
     */
    public function getPageSlugCacheKey(string $postType): string
    {
        return Api::OPTION_PREFIX . $postType . self::SLUG_CACHE_SUFFIX;
    }
}
