<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Core;

use WP_Post_Type;

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
     * Exclude the pagination base from a post type's rewrite tag.
     *
     * The plugin turns has_archive off because the page is the archive, so
     * core generates no pagination rule for that base. When the post type is
     * rebased on the page slug, /{page}/page/2/ is then swallowed by the
     * single rule, which matches with the post name set to the pagination
     * base, and 404s. A lookahead on the tag keeps the single rules off it,
     * and the page rules resolve the URL as they would anywhere else.
     *
     * It goes on the tag rather than in a rule of its own so that every rule
     * derived from it inherits it, the copies a multilingual plugin builds by
     * prefixing the language included. A standalone rule would carry a page
     * path those plugins have no reason to translate, and would not be
     * duplicated per language either.
     */
    public function addRewriteTags(WP_Post_Type $postType): void
    {
        remove_rewrite_tag("%{$postType->name}%");

        $regex = $postType->hierarchical ? '(.+?)' : '([^/]+)';
        $queryParam = $postType->hierarchical ? 'pagename' : 'name';

        add_rewrite_tag(
            "%{$postType->name}%",
            $this->getPaginationExclusion() . $regex,
            $postType->query_var ? "{$postType->query_var}=" : "post_type={$postType->name}&{$queryParam}="
        );
    }

    /**
     * Put back the parentheses WordPress strips from the lookahead.
     *
     * WP_Rewrite::generate_rewrite_rules() derives the attachment sub-rules of
     * a single from the single's own match, with
     * str_replace(['(', ')'], '', $match) to drop the capture groups so that
     * the attachment name is always $matches[1]. That also strips the ones of
     * the lookahead added by addRewriteTags() and leaves a literal "?!page"
     * behind, which matches nothing: attachment URLs under a single end up
     * matching no rule at all and 404.
     *
     * Put the lookahead back, still without a capture group so the indexes
     * WordPress computed for those rules stay valid. This is filtered on a
     * single post type's rules, so every regex here comes from our own tag.
     *
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    public function restorePaginationExclusion(array $rules): array
    {
        $exclusion = $this->getPaginationExclusion();
        $mangled = str_replace(['(', ')'], '', $exclusion);

        // Skip an exclusion that is still intact: it contains the mangled form.
        $pattern = '/(?<!\()' . preg_quote($mangled, '/') . '/';

        $restored = [];

        foreach ($rules as $regex => $query) {
            $fixed = preg_replace($pattern, $exclusion, $regex);

            // Never restore onto a regex that already exists, that would drop
            // a rule.
            if (!\is_string($fixed) || isset($rules[$fixed]) || isset($restored[$fixed])) {
                $restored[$regex] = $query;

                continue;
            }

            $restored[$fixed] = $query;
        }

        return $restored;
    }

    /**
     * The lookahead keeping a post name from matching the pagination base.
     *
     * The base is translatable, so it is read from WP_Rewrite rather than
     * hardcoded, the way core builds its own pagination rules.
     */
    private function getPaginationExclusion(): string
    {
        /** @var \WP_Rewrite */
        global $wp_rewrite;

        return \sprintf('(?!%s)', $wp_rewrite->pagination_base);
    }

    /**
     * Restore the feed rules of a post type's permastruct.
     *
     * WP_Post_Type::set_props() derives rewrite['feeds'] from has_archive, and
     * overrides an explicit value when has_archive is false, so there is no
     * way to keep the feeds through the registration args. The flag is copied
     * to the permastruct right before add_permastruct(), which is where it can
     * still be put back.
     */
    public function restoreFeedRules(string $postType): void
    {
        /** @var \WP_Rewrite */
        global $wp_rewrite;

        $permastruct = $wp_rewrite->extra_permastructs[$postType] ?? null;

        if (!\is_array($permastruct) || !isset($permastruct['feed'])) {
            return;
        }

        $wp_rewrite->extra_permastructs[$postType]['feed'] = true;
    }

    /**
     * Get cache key for page slug.
     */
    public function getPageSlugCacheKey(string $postType): string
    {
        return Api::OPTION_PREFIX . $postType . self::SLUG_CACHE_SUFFIX;
    }
}
