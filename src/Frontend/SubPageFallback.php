<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Frontend;

use n5s\PageForCustomPostType\Core\Api;
use WP;
use WP_MatchesMapRegex;
use WP_Post;
use WP_Query;

/**
 * Resolves child pages of a PFCPT page that the post type rules shadow.
 *
 * When a post type is rebased on its page slug, the rules generated for that
 * post type sit in extra_rules_top, above the page rules, and swallow every
 * URL under the page:
 *
 *     /books/a-child/          => post_type=book&name=a-child
 *     /books/a-child/deeper/   => attachment=deeper
 *
 * so a child page of the archive page 404s. WordPress matches the first rule
 * whose regex fits and never reconsiders, and only page rules get the "does it
 * exist" treatment (WP::parse_request(), verbose page rules), which post type
 * rules don't.
 *
 * This runs from handle_404(), once the main query has come back empty: the
 * request is then matched again, against the page rules alone. Hooking there
 * rather than on 'request' is what keeps it free. Deciding before the query
 * would mean looking the page up on every single post, so either an extra
 * query on every single, or a cache saying whether the page has children, and
 * a cache that has to survive pages being added, moved, trashed and restored.
 * Here nothing runs until WordPress has established there is nothing to show.
 * That is also what gives the post type its priority for free: when a post
 * exists the query found it, and none of this happens.
 *
 * Core's own page rules are reused, so pagination, feeds, embeds and comment
 * pages behave on those URLs as they would anywhere else in the page tree.
 */
final class SubPageFallback
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    /**
     * Serve a child page instead of 404ing.
     *
     * @param mixed $preempt Whether something already handled the 404.
     */
    public function fallbackToSubPage(mixed $preempt, WP_Query $query): mixed
    {
        if ($preempt !== false || !$query->is_main_query() || !empty($query->posts)) {
            return $preempt;
        }

        $wp = $GLOBALS['wp'] ?? null;

        if (!$wp instanceof WP || $wp->matched_rule === '') {
            return $preempt;
        }

        $request = $wp->request;

        if (!\is_string($request) || $request === '') {
            return $preempt;
        }

        // The page rules already won, there is nothing to undo.
        if (!empty($wp->query_vars['pagename'])) {
            return $preempt;
        }

        $match = $this->matchPageRules($request);

        if ($match === null) {
            return $preempt;
        }

        [$pageQueryVars, $page] = $match;

        $postType = $this->getPostTypeForPage($page);

        if ($postType === null) {
            return $preempt;
        }

        /**
         * Filter whether to fall back to a child page of the post type's page.
         *
         * @param bool   $enabled  Whether the fallback runs. Default true.
         * @param string $postType The post type the request was matched for.
         * @param string $request  The requested path, without the leading slash.
         */
        if (!apply_filters('pfcpt/fallback_to_sub_page', true, $postType, $request)) {
            return $preempt;
        }

        $wp->query_vars = $this->replaceMatchedQueryVars($wp->query_vars, $wp->matched_query, $pageQueryVars);
        $query->query($wp->query_vars);

        // Not preempting: handle_404() now sees the page and sets the status.
        return $preempt;
    }

    /**
     * Get the post type the found page belongs to, as its page or below it.
     *
     * Walking the page's ancestors rather than comparing the request to a page
     * path is what makes this work whatever the URL carries. A multilingual
     * plugin in directory mode leaves its language segment in the request, and
     * the page path it resolves to is per language anyway.
     */
    private function getPostTypeForPage(WP_Post $page): ?string
    {
        $pageIds = $this->api->getPageIds();

        // The page itself counts: /{page}/feed/ is matched as a single too,
        // and resolves back to the page.
        $candidates = array_merge([$page->ID], get_post_ancestors($page));

        foreach ($candidates as $ancestorId) {
            $postType = array_search($ancestorId, $pageIds, true);

            if (!\is_string($postType) || !$this->api->shouldUsePageSlug($postType)) {
                continue;
            }

            return $postType;
        }

        return null;
    }

    /**
     * Match the request against the page rules only.
     *
     * Mirrors what WP::parse_request() does with verbose page rules: run the
     * rules in order, and skip a match whose page doesn't exist.
     *
     * @return array{0: array<string, mixed>, 1: WP_Post}|null
     */
    private function matchPageRules(string $request): ?array
    {
        /** @var \WP_Rewrite */
        global $wp_rewrite;

        foreach ($wp_rewrite->wp_rewrite_rules() as $match => $query) {
            // Page rules, and not the ones a hierarchical post type without a
            // query var generates, which also fill pagename.
            if (!str_contains($query, 'pagename=$matches[') || str_contains($query, 'post_type=')) {
                continue;
            }

            if (preg_match("#^{$match}#", $request, $matches) !== 1) {
                continue;
            }

            $query = preg_replace('!^.+\?!', '', $query);

            if (!\is_string($query)) {
                continue;
            }

            $pageQueryVars = $this->parseQueryString(WP_MatchesMapRegex::apply($query, $matches));

            $pagename = $pageQueryVars['pagename'] ?? null;

            if (!\is_string($pagename) || $pagename === '') {
                continue;
            }

            $page = get_page_by_path($pagename);

            if (!$page instanceof WP_Post || !$this->isViewable($page)) {
                continue;
            }

            return [$pageQueryVars, $page];
        }

        return null;
    }

    /**
     * Swap the query vars the matched rule produced for the page ones.
     *
     * Query vars coming from the query string (preview, p, ...) are kept.
     *
     * @param array<string, mixed> $queryVars
     * @param array<string, mixed> $pageQueryVars
     * @return array<string, mixed>
     */
    private function replaceMatchedQueryVars(array $queryVars, string $matchedQuery, array $pageQueryVars): array
    {
        $matchedQueryVars = $this->parseQueryString($matchedQuery);

        // WP::parse_request() fills post_type and name from a post type query
        // var before the query runs, so they belong to the match too even
        // though the rule never named them.
        $matchedQueryVars['post_type'] = null;
        $matchedQueryVars['name'] = null;

        return array_merge(array_diff_key($queryVars, $matchedQueryVars), $pageQueryVars);
    }

    /**
     * Parse a rewrite rule query string into query vars.
     *
     * @return array<string, mixed>
     */
    private function parseQueryString(string $query): array
    {
        parse_str($query, $parsed);

        $queryVars = [];

        foreach ($parsed as $key => $value) {
            if (\is_string($key)) {
                $queryVars[$key] = $value;
            }
        }

        return $queryVars;
    }

    /**
     * Check that a page can be served, the way WP::parse_request() does.
     */
    private function isViewable(WP_Post $page): bool
    {
        // Carries the is_post_status_viewable filter, which a site can use to
        // make a custom status viewable.
        if (is_post_status_viewable($page->post_status)) {
            return true;
        }

        $status = get_post_status_object($page->post_status);

        if ($status === null) {
            return false;
        }

        // WP::parse_request() is more permissive than is_post_status_viewable()
        // on protected and private statuses: it matches them and leaves the
        // capability check to WP_Query, so that a private child page stays
        // reachable for a user allowed to read it. Same predicate here, so the
        // same pages resolve as anywhere else in the page tree.
        return $status->protected || $status->private || !$status->exclude_from_search;
    }
}
