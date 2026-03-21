<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Acf;

\add_action('acf/include_location_rules', static function (int $acfMajorVersion): void {
    if ($acfMajorVersion !== 5) {
        return;
    }

    require_once __DIR__ . '/LocationPageType.php';

    $store = \acf_get_store('location-types');
    $locationType = new LocationPageType();
    $store->set($locationType->name, $locationType);
});
