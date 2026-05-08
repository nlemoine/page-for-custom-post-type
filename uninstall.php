<?php

/**
 * Plugin uninstall handler.
 *
 * Removes every option and transient created by the plugin so deleting it
 * leaves no orphaned data behind.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Walk the aggregated mapping to clear known per-CPT options and transients
// via the WP API. This handles object caches and the alloptions cache.
$pfcptMapping = get_option('pages_for_custom_post_type', []);
if (is_array($pfcptMapping)) {
    foreach (array_keys($pfcptMapping) as $pfcptPostType) {
        if (is_string($pfcptPostType) && $pfcptPostType !== '') {
            delete_option('page_for_' . $pfcptPostType);
            delete_option('page_for_' . $pfcptPostType . '_use_slug');
            delete_transient('page_for_' . $pfcptPostType . '_slug');
        }
    }
}
unset($pfcptMapping, $pfcptPostType);

delete_option('pages_for_custom_post_type');
delete_option('pfcpt_db_version');

// Catch any orphaned rows in wp_options for CPTs no longer present in the
// aggregated mapping (e.g. a CPT was unregistered without cleanup).
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('page_for_') . '%',
        $wpdb->esc_like('_transient_page_for_') . '%',
        $wpdb->esc_like('_transient_timeout_page_for_') . '%'
    )
);
