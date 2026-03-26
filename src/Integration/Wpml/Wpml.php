<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Wpml;

use n5s\PageForCustomPostType\Integration\IntegrationInterface;

/**
 * WPML integration composite.
 *
 * Bootstraps all WPML-related integrations as a single unit.
 */
final class Wpml implements IntegrationInterface
{
    public function __construct(
        private readonly Translation $translation,
        private readonly UrlTranslation $urlTranslation,
        private readonly Admin $admin,
        private readonly Lifecycle $lifecycle
    ) {
    }

    public function isSupported(): bool
    {
        return defined('ICL_SITEPRESS_VERSION');
    }

    public function registerHooks(): void
    {
        $this->translation->registerHooks();
        $this->urlTranslation->registerHooks();
        $this->admin->registerHooks();
        $this->lifecycle->registerHooks();
    }
}
