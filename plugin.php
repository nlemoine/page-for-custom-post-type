<?php

/**
 * Plugin Name: Page for custom post type
 * Plugin URI: https://github.com/nlemoine/page-for-custom-post-type
 * Description: Allows you to set pages for any custom post type archive
 * Version: 0.5.0
 * Author: Nicolas Lemoine
 * Author URI: https://n5s.dev/
 * Requires PHP: 8.2
 */

declare(strict_types=1);

namespace n5s\PageForCustomPostType;

use n5s\PageForCustomPostType\Integration\IntegrationInterface;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize plugin (hook before Polylang)
add_action('plugins_loaded', static function (): void {
    $plugin = Plugin::getInstance();
    $plugin->init();

    $container = $plugin->getContainer();
    foreach ($plugin->getIntegrations() as $integrationClass) {
        $integration = $container->get($integrationClass);

        if ($integration->isSupported()) {
            $integration->registerHooks();
        }
    }
}, 0);
