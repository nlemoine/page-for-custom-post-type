<?php

declare(strict_types=1);

use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

use function Mantle\Testing\manager;

require_once __DIR__ . '/../vendor/autoload.php';
$rootDir = realpath(__DIR__ . '/..');

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
putenv("WP_CORE_DIR=$rootDir/tmp/wordpress");
putenv("CACHEDIR=$rootDir/tmp/test-cache");

/**
 * Determine which plugins to load based on PLUGINS env variable.
 */
$availablePlugins = [
    'wordpress-seo' => 'wordpress-seo/wp-seo.php',
    'polylang' => 'polylang/polylang.php',
    'autodescription' => 'autodescription/autodescription.php',
    'advanced-custom-fields' => 'advanced-custom-fields/acf.php',
];
$requestedPlugins = array_filter(explode(',', getenv('PLUGINS') ?: ''));
$plugins = array_values(array_filter(array_map(static function (string $p) use ($availablePlugins): ?string {
    return $availablePlugins[$p] ?? null;
}, $requestedPlugins)));

$isPolylang = \in_array('polylang', $requestedPlugins, true);

$manager = manager()
    ->with_sqlite()
    ->loaded(static function () use ($plugins): void {
        require __DIR__ . '/../plugin.php';
        foreach ($plugins as $plugin) {
            require __DIR__ . '/../wp-content/plugins/' . $plugin;
        }
    })
    ->init(static function (): void {
        TestCase::registerFixturePostTypes();
        register_taxonomy('genre', 'book', [
            'public' => true,
            'label' => 'Genres',
            'rewrite' => [
                'slug' => 'genres',
            ],
        ]);
        register_taxonomy_for_object_type('genre', 'book');
    });

if ($isPolylang) {
    // Initialize Polylang after install.
    // Language creation is handled per-test in TestCase::setPolylangDefaultLanguage()
    // because Refresh_Database rolls back DB changes between tests.
    $manager->after(static function (): void {
        if (!defined('POLYLANG_DIR')) {
            return;
        }

        $polylangBootstrap = new \Polylang();
        $polylangBootstrap->init();
    });
}

$manager->install();
