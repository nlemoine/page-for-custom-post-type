<?php

namespace n5s\PageForCustomPostType\Integrations\ACF;

\add_action('acf/include_location_rules', function ($acf_major_version) {
    if ($acf_major_version !== 5) {
        return;
    }
    require_once __DIR__ . '/location-page-type.php';

    $store = \acf_get_store('location-types');

    $location_type = new Location_Page_Type();
    $name = $location_type->name;
    $store->set($name, $location_type);
});
