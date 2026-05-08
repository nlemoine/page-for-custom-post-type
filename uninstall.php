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
// via the WP API. delete_transient is the only viable path when a persistent
// object cache is enabled, since transients then don't live in wp_options.
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

// Catch any orphan page_for_* options for CPTs no longer in the aggregated
// mapping. Transient orphans aren't covered: on sites with a persistent
// object cache they don't have wp_options rows at all; on sites without one
// the unbounded transients (set_transient(..., 0)) would persist, but this
// is rare enough to accept rather than scan the whole options table.
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('page_for_') . '%'
    )
);
