<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\WordPressSeo;

use n5s\PageForCustomPostType\Core\Api;
use Yoast\WP\SEO\Context\Meta_Tags_Context;
use Yoast\WP\SEO\Main;
use Yoast\WP\SEO\Models\Indexable;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Surfaces\Values\Meta;

/**
 * Yoast SEO Breadcrumbs integration.
 *
 * Fixes breadcrumb trails for posts, taxonomies, and home pages
 * when using Page for Custom Post Type.
 */
final class Breadcrumbs
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    public function registerHooks(): void
    {
        \add_filter('wpseo_breadcrumb_indexables', [$this, 'fixHomeBreadcrumbs'], 10, 2);
        \add_filter('wpseo_breadcrumb_indexables', [$this, 'fixTaxonomyBreadcrumbs'], 10, 2);
        \add_filter('wpseo_breadcrumb_indexables', [$this, 'fixPostBreadcrumbs'], 10, 2);
    }

    /**
     * Fix Yoast breadcrumbs on post.
     *
     * @param Indexable[] $indexables
     * @return Indexable[]
     */
    public function fixPostBreadcrumbs(array $indexables, Meta_Tags_Context $context): array
    {
        $currentPostType = $context->indexable->object_sub_type ?? null;

        if (!$currentPostType || !\is_singular($currentPostType)) {
            return $indexables;
        }

        $pageForPostTypeId = $this->api->getPageIdFromPostType($currentPostType, \function_exists('PLL'));

        if (!$pageForPostTypeId) {
            return $indexables;
        }

        $pageForPostTypeMeta = $this->getMetaForPost($pageForPostTypeId);

        if (!$pageForPostTypeMeta) {
            return $indexables;
        }

        $yoast = $this->getYoast();

        \array_splice(
            $indexables,
            $yoast->helpers->options->get('breadcrumbs-home') ? 1 : 0,
            0,
            [$pageForPostTypeMeta->indexable]
        );

        return $indexables;
    }

    /**
     * Fix Yoast breadcrumbs on taxonomy.
     *
     * @param Indexable[] $indexables
     * @return Indexable[]
     */
    public function fixTaxonomyBreadcrumbs(array $indexables, Meta_Tags_Context $context): array
    {
        $currentTaxonomy = $context->indexable->object_sub_type ?? '';

        if ($currentTaxonomy === '' || !\is_tax($currentTaxonomy)) {
            return $indexables;
        }

        $currentPostType = \get_post_type();

        if (!is_string($currentPostType)) {
            return $indexables;
        }

        $yoast = $this->getYoast();

        $mainTaxonomyForPostType = $yoast->helpers->options->get('post_types-' . $currentPostType . '-maintax');

        if ($mainTaxonomyForPostType !== $currentTaxonomy) {
            return $indexables;
        }

        $taxonomies = \get_object_taxonomies($currentPostType);

        if (!\in_array($currentTaxonomy, $taxonomies, true)) {
            return $indexables;
        }

        $pageForPostTypeId = $this->api->getPageIdFromPostType($currentPostType, \function_exists('PLL'));

        if (!$pageForPostTypeId) {
            return $indexables;
        }

        $pageForPostTypeMeta = $this->getMetaForPost($pageForPostTypeId);

        if (!$pageForPostTypeMeta) {
            return $indexables;
        }

        \array_splice(
            $indexables,
            $yoast->helpers->options->get('breadcrumbs-home') ? 1 : 0,
            0,
            [$pageForPostTypeMeta->indexable]
        );

        return $indexables;
    }

    /**
     * Fix Yoast breadcrumbs on home.
     *
     * @param Indexable[] $indexables
     * @return Indexable[]
     */
    public function fixHomeBreadcrumbs(array $indexables, Meta_Tags_Context $context): array
    {
        if (!$this->api->isQueryPageForCustomPostType()) {
            return $indexables;
        }

        $yoast = $this->getYoast();

        if ($yoast->helpers->current_page->get_page_type() !== 'Home_Page') {
            return $indexables;
        }

        /** @var Indexable_Repository $indexableRepository */
        $indexableRepository = $yoast->classes->get(Indexable_Repository::class);
        $staticAncestors = [];

        $breadcrumbsHome = $yoast->helpers->options->get('breadcrumbs-home');

        if ($breadcrumbsHome !== '') {
            $frontPageId = $yoast->helpers->current_page->get_front_page_id();

            if ($frontPageId === 0) {
                $homePageAncestor = $indexableRepository->find_for_home_page();

                if (!\is_bool($homePageAncestor) && $homePageAncestor instanceof Indexable) {
                    $staticAncestors[] = $homePageAncestor;
                }
            } else {
                $staticAncestor = $indexableRepository->find_by_id_and_type($frontPageId, 'post');

                if (!\is_bool($staticAncestor) && $staticAncestor instanceof Indexable && $staticAncestor->post_status !== 'unindexed') {
                    $staticAncestors[] = $staticAncestor;
                }
            }
        }

        if (!empty($staticAncestors)) {
            \array_unshift($indexables, ...$staticAncestors);
        }

        return $indexables;
    }

    private function getYoast(): Main
    {
        /** @var Main */
        return \YoastSEO();
    }

    private function getMetaForPost(int $postId): ?Meta
    {
        $yoast = $this->getYoast();

        /** @var Meta|false $meta */
        $meta = $yoast->meta->for_post($postId);

        return $meta !== false ? $meta : null;
    }
}
