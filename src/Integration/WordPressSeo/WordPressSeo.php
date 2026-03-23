<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\WordPressSeo;

use n5s\PageForCustomPostType\Integration\IntegrationInterface;

/**
 * Yoast SEO integration composite.
 *
 * Bootstraps all Yoast SEO-related integrations as a single unit.
 */
final class WordPressSeo implements IntegrationInterface
{
    public function __construct(
        private readonly Schema $schema,
        private readonly Breadcrumbs $breadcrumbs,
        private readonly Indexables $indexables
    ) {
    }

    public function isSupported(): bool
    {
        return function_exists('YoastSEO');
    }

    public function registerHooks(): void
    {
        $this->schema->registerHooks();
        $this->breadcrumbs->registerHooks();
        $this->indexables->registerHooks();
    }
}
