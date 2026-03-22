<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Autodescription;

use n5s\PageForCustomPostType\Core\Api;

/**
 * The SEO Framework query type integration.
 *
 * Tells TSF that PFCPT pages are "singular archives" — pages that display
 * a collection of posts. This enables correct schema (CollectionPage) and
 * meta tag resolution from the page's post meta.
 *
 * TSF's Query::is_singular() returns is_singular() || is_singular_archive(),
 * so this filter makes PFCPT pages behave as singular for meta output.
 */
final class QueryType
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    public function registerHooks(): void
    {
        \add_filter('the_seo_framework_is_singular_archive', [$this, 'markPfcptAsSingularArchive'], 10, 2);
    }

    /**
     * Mark PFCPT pages as singular archives.
     *
     * @param bool     $isSingularArchive Whether the post is a singular archive.
     * @param int|null $id                The post ID. Null when in the loop.
     */
    public function markPfcptAsSingularArchive(bool $isSingularArchive, ?int $id): bool
    {
        if ($isSingularArchive) {
            return true;
        }

        if ($id === null) {
            return $this->api->isQueryPageForCustomPostType();
        }

        return $this->api->getPostTypeFromPageId($id) !== null;
    }
}
