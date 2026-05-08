<?php

/**
 * Plugin Name: Page for custom post type
 * Plugin URI: https://github.com/nlemoine/page-for-custom-post-type
 * Description: Allows you to set pages for any custom post type archive
 * x-release-please-start-version
 * Version: 0.5.0
 * x-release-please-end
 * Author: Nicolas Lemoine
 * Author URI: https://n5s.dev/
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 8.2
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pfcpt
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace n5s\PageForCustomPostType;

// Prevent direct access
if (!\defined('ABSPATH')) {
    exit;
}

// @bundle-autoload

// Load translations on init (plugin is not on wordpress.org, so the
// auto-loading introduced in WP 4.6 doesn't apply).
add_action('init', static function (): void {
    load_plugin_textdomain('pfcpt', false, basename(__DIR__) . '/languages');
});

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
