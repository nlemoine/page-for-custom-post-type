<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Autodescription;

use n5s\PageForCustomPostType\Core\Api;

/**
 * The SEO Framework Breadcrumbs integration.
 *
 * Inserts the PFCPT page into breadcrumb trails for single CPT posts
 * and taxonomy archives, since PFCPT disables has_archive (so TSF
 * won't add a post type archive crumb automatically).
 */
final class Breadcrumbs
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    public function registerHooks(): void
    {
        \add_filter('the_seo_framework_breadcrumb_list', [$this, 'addPfcptPageCrumb'], 10, 2);
    }

    /**
     * Add PFCPT page to breadcrumb list for single posts and taxonomy archives.
     *
     * @param array<int, array<string, string>> $list The breadcrumb list items.
     * @param array<string, mixed>|null         $args The query arguments (id, tax, pta, uid).
     * @return array<int, array<string, string>>
     */
    public function addPfcptPageCrumb(array $list, ?array $args): array
    {
        $postType = $this->resolvePostType($args);

        if ($postType === null) {
            return $list;
        }

        $pageId = $this->api->getPageIdFromPostType($postType, \function_exists('PLL'));

        if ($pageId === null) {
            return $list;
        }

        // Don't add if we're ON the PFCPT page itself (it's already the current crumb)
        if ($this->isCurrentPfcptPage($pageId, $args)) {
            return $list;
        }

        $url = \get_permalink($pageId);

        if ($url === false) {
            return $list;
        }

        $crumb = [
            'url' => $url,
            'name' => \get_the_title($pageId),
        ];

        // Insert after the first crumb (Home)
        \array_splice($list, 1, 0, [$crumb]);

        return $list;
    }

    /**
     * Resolve the post type that should have a PFCPT page in breadcrumbs.
     *
     * Returns null if we're not on a page that needs a PFCPT breadcrumb.
     */
    /**
     * @param array<string, mixed>|null $args
     */
    private function resolvePostType(?array $args): ?string
    {
        if ($args !== null) {
            return $this->resolvePostTypeFromArgs($args);
        }

        return $this->resolvePostTypeFromQuery();
    }

    /**
     * @param array<string, mixed> $args
     */
    private function resolvePostTypeFromArgs(array $args): ?string
    {
        // Single post
        if (!empty($args['id']) && empty($args['tax'])) {
            $argId = $args['id'];

            if (!\is_int($argId) && !$argId instanceof \WP_Post) {
                return null;
            }

            $post = \get_post($argId);

            if ($post === null) {
                return null;
            }

            $postType = $post->post_type;

            return $this->api->getPageIdFromPostType($postType) !== null ? $postType : null;
        }

        // Taxonomy archive
        if (!empty($args['id']) && !empty($args['tax'])) {
            $tax = $args['tax'];

            if (!\is_string($tax)) {
                return null;
            }

            return $this->getPostTypeForTaxonomy($tax);
        }

        return null;
    }

    private function resolvePostTypeFromQuery(): ?string
    {
        // Single post
        if (\is_singular()) {
            $postType = \get_post_type();

            if (!\is_string($postType)) {
                return null;
            }

            return $this->api->getPageIdFromPostType($postType) !== null ? $postType : null;
        }

        // Taxonomy archive
        if (\is_tax() || \is_category() || \is_tag()) {
            $term = \get_queried_object();

            if (!$term instanceof \WP_Term) {
                return null;
            }

            return $this->getPostTypeForTaxonomy($term->taxonomy);
        }

        return null;
    }

    /**
     * Get the post type associated with a taxonomy that has a PFCPT page.
     */
    private function getPostTypeForTaxonomy(string $taxonomy): ?string
    {
        $taxonomyObject = \get_taxonomy($taxonomy);

        if ($taxonomyObject === false) {
            return null;
        }

        foreach ($taxonomyObject->object_type as $postType) {
            if ($this->api->getPageIdFromPostType($postType) !== null) {
                return $postType;
            }
        }

        return null;
    }

    /**
     * Check if the current page IS the PFCPT page (to avoid duplicate crumb).
     */
    /**
     * @param array<string, mixed>|null $args
     */
    private function isCurrentPfcptPage(int $pageId, ?array $args): bool
    {
        if ($args !== null) {
            $argId = $args['id'] ?? null;

            return !empty($argId) && empty($args['tax']) && \is_numeric($argId) && (int) $argId === $pageId;
        }

        return $this->api->isQueryPageForCustomPostType();
    }
}
