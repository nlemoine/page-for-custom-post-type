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

    /**
     * Lookahead preventing a post name from matching the "page" pagination segment.
     */
    private const PAGE_EXCLUSION = '(?!page)';

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
     */
    public function getPageSlug(int $pageId): ?string
    {
        $pageUrl = get_permalink($pageId);

        if ($pageUrl === false) {
            return null;
        }

        $pagePath = parse_url($pageUrl, \PHP_URL_PATH);

        return \is_string($pagePath) ? trim($pagePath, '/') : null;
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
     * Add rewrite tags for a post type.
     *
     * Handles both standard and custom permalink structures (permastructs).
     * For custom permastructs (e.g. from extended-cpts), we need to add a
     * (?!page) exclusion to tags that precede %postname%/%post_id% in the
     * structure, otherwise /page/2/ pagination URLs get incorrectly matched.
     */
    public function addRewriteTags(WP_Post_Type $postType): void
    {
        $rewrite = $postType->rewrite;
        $permastruct = \is_array($rewrite) ? ($rewrite['permastruct'] ?? null) : null;

        if (!\is_string($permastruct)) {
            remove_rewrite_tag("%{$postType->name}%");

            $regex = $postType->hierarchical ? '(.+?)' : '([^/]+)';
            $queryParam = $postType->hierarchical ? 'pagename' : 'name';

            add_rewrite_tag(
                "%{$postType->name}%",
                self::PAGE_EXCLUSION . $regex,
                $postType->query_var ? "{$postType->query_var}=" : "post_type={$postType->name}&{$queryParam}="
            );

            return;
        }

        // Custom permastruct: find tags before %postname%/%post_id% that need
        // the (?!page) exclusion added to their regex.
        $this->fixPermastructRewriteTags($permastruct);
    }

    /**
     * Restore the (?!page) exclusion in rules where WordPress stripped its parentheses.
     *
     * WP_Rewrite::generate_rewrite_rules() builds the attachment sub-rules of a
     * single from its own match, with str_replace(['(', ')'], '', $match) to get
     * rid of the capture groups, so that the attachment name is always
     * $matches[1]. That also strips the parentheses of the exclusion added in
     * addRewriteTags() and leaves a literal "?!page" behind, which matches
     * nothing: attachment URLs under a single (/books/a-book/an-image/) end up
     * matching no rule at all and 404.
     *
     * Put the lookahead back, still without a capture group so the indexes
     * WordPress computed for those rules stay valid.
     *
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    public function restorePageExclusion(array $rules): array
    {
        $restored = [];

        foreach ($rules as $regex => $query) {
            $fixed = preg_replace('/(?<!\()\?!page/', self::PAGE_EXCLUSION, $regex);
            $restored[\is_string($fixed) ? $fixed : $regex] = $query;
        }

        return $restored;
    }

    /**
     * Fix rewrite tags in a custom permastruct to exclude "page" from matching.
     *
     * Parses the permastruct backwards from %postname%/%post_id% and adds
     * (?!page) to preceding tags like %category% or %author%.
     */
    private function fixPermastructRewriteTags(string $permastruct): void
    {
        /** @var \WP_Rewrite */
        global $wp_rewrite;

        $parts = array_reverse(explode('/', ltrim($permastruct, '/')));

        $triggerTags = ['%postname%', '%post_id%'];
        $replaceTags = ['%category%', '%author%'];
        $shouldWatchNext = false;
        $replacements = [];

        foreach ($parts as $part) {
            if (!$shouldWatchNext && !\in_array($part, $triggerTags, true)) {
                continue;
            }

            if (!$shouldWatchNext) {
                $shouldWatchNext = true;
                continue;
            }

            if (!\in_array($part, $replaceTags, true)) {
                continue;
            }

            $tagIndex = array_search($part, $wp_rewrite->rewritecode, true);

            if ($tagIndex === false) {
                continue;
            }

            if (
                !isset($wp_rewrite->rewritereplace[$tagIndex])
                || str_contains($wp_rewrite->rewritereplace[$tagIndex], self::PAGE_EXCLUSION)
            ) {
                continue;
            }

            $replacements[] = [
                'tag' => $part,
                'regex' => self::PAGE_EXCLUSION . $wp_rewrite->rewritereplace[$tagIndex],
                'query' => $wp_rewrite->queryreplace[$tagIndex],
            ];
        }

        foreach ($replacements as $replacement) {
            remove_rewrite_tag($replacement['tag']);
            add_rewrite_tag($replacement['tag'], $replacement['regex'], $replacement['query']);
        }
    }

    /**
     * Get cache key for page slug.
     */
    public function getPageSlugCacheKey(string $postType): string
    {
        return Api::OPTION_PREFIX . $postType . self::SLUG_CACHE_SUFFIX;
    }
}
