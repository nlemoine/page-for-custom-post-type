<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\WordPressSeo;

use n5s\PageForCustomPostType\Core\Api;

/**
 * Yoast SEO Schema integration.
 *
 * Adds CollectionPage schema type to custom post type archive pages.
 */
final class Schema
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    public function registerHooks(): void
    {
        \add_filter('wpseo_schema_webpage_type', [$this, 'addCollectionPageType']);
    }

    /**
     * Add CollectionPage schema to custom post type pages.
     *
     * @param string|string[] $type
     * @return string|string[]
     */
    public function addCollectionPageType(string|array $type): string|array
    {
        if (!$this->api->isQueryPageForCustomPostType()) {
            return $type;
        }

        $type = (array) $type;

        if (\in_array('CollectionPage', $type, true)) {
            return $type;
        }

        $type[] = 'CollectionPage';

        return $type;
    }
}
