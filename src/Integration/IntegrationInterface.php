<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration;

/**
 * Interface for third-party plugin integrations.
 */
interface IntegrationInterface
{
    /**
     * Check if this integration should be loaded.
     *
     * Typically checks if the required third-party plugin is active.
     */
    public function isSupported(): bool;

    /**
     * Register WordPress hooks for this integration.
     */
    public function registerHooks(): void;
}
