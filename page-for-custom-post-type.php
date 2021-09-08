<?php
/**
 * Plugin Name: Page for custom post type
 * Plugin URI: https://github.com/nlemoine/page-for-custom-post-type
 * Description: Allows you to set pages for any custom post type archive
 * Version: 0.2.0
 * Author: Nicolas Lemoine
 * Author URI: https://hellonic.co/.
 */

require_once __DIR__ . '/src/plugin.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/integrations/wordpress-seo.php';
require_once __DIR__ . '/src/integrations/acf/acf.php';

  // Hook before Polylang
add_action('plugins_loaded', [HelloNico\PageForCustomPostType\Plugin::class, 'get_instance'], 0);
