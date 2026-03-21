<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\TheSeoFramework;

use n5s\PageForCustomPostType\Integration\IntegrationInterface;

/**
 * The SEO Framework integration composite.
 *
 * Bootstraps all TSF-related integrations as a single unit.
 */
final class TheSeoFramework implements IntegrationInterface
{
    public function __construct(
        private readonly QueryType $queryType,
        private readonly Breadcrumbs $breadcrumbs
    ) {
    }

    public function isSupported(): bool
    {
        return \function_exists('the_seo_framework');
    }

    public function registerHooks(): void
    {
        $this->queryType->registerHooks();
        $this->breadcrumbs->registerHooks();
    }
}
