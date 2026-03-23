<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Polylang;

use n5s\PageForCustomPostType\Integration\IntegrationInterface;

/**
 * Polylang integration composite.
 *
 * Bootstraps all Polylang-related integrations as a single unit.
 */
final class Polylang implements IntegrationInterface
{
    public function __construct(
        private readonly UrlTranslation $urlTranslation,
        private readonly Translation $translation,
        private readonly SlugTranslation $slugTranslation,
        private readonly Admin $admin,
        private readonly Lifecycle $lifecycle
    ) {
    }

    public function isSupported(): bool
    {
        return function_exists('PLL');
    }

    public function registerHooks(): void
    {
        $this->urlTranslation->registerHooks();
        $this->translation->registerHooks();
        $this->slugTranslation->registerHooks();
        $this->admin->registerHooks();
        $this->lifecycle->registerHooks();
    }
}
