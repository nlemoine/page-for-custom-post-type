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

        // Polylang only loads its API (the pll_* functions and PLL()) when
        // init() enters a context via init_context(). In PHPUnit there is no
        // request context and no languages exist yet, so init() detects an
        // empty context and returns before requiring src/api.php — leaving
        // PLL() undefined and every PolylangTest skipped. Force a frontend
        // context so the API loads; languages are (re)created per test by
        // TestCase::setPolylangDefaultLanguage() after Refresh_Database rolls
        // them back.
        add_filter('pll_context', static fn (string $class): string => $class ?: 'PLL_Frontend');

        $polylangBootstrap = new \Polylang();
        $polylangBootstrap->init();
    });
}

$manager->install();
