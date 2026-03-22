<?php

declare(strict_types=1);

use Mantle\Testing\EarlyIncorrectUsageHandler;

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
];
$requestedPlugins = array_filter(explode(',', getenv('PLUGINS') ?: ''));
$plugins = array_values(array_filter(array_map(static function ($p) use ($availablePlugins): ?string {
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
        register_post_type('bike', [
            'public' => true,
            'publicly_queryable' => true,
            'label' => 'Bikes',
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'bikes',
            ],
        ]);
        register_post_type('book', [
            'public' => true,
            'publicly_queryable' => true,
            'label' => 'Books',
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'books',
            ],
        ]);
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
    // Create Polylang languages during bootstrap.
    //
    // PLL_Admin_Model::add_language() calls Languages::get_list() which
    // triggers _doing_it_wrong before pll_pre_init. We temporarily suppress
    // Mantle's EarlyIncorrectUsageHandler to prevent test failure.
    $manager->after(static function (): void {
        if (!defined('POLYLANG_DIR')) {
            return;
        }

        // Suppress Mantle's early _doing_it_wrong handler
        EarlyIncorrectUsageHandler::unregister();

        $options = new \WP_Syntex\Polylang\Options\Options();
        $model = new \PLL_Admin_Model($options);

        $languages = [
            ['name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => false, 'term_group' => 0, 'flag' => 'us'],
            ['name' => 'Français', 'slug' => 'fr', 'locale' => 'fr_FR', 'rtl' => false, 'term_group' => 1, 'flag' => 'fr'],
        ];

        foreach ($languages as $language) {
            if (!$model->get_language($language['slug'])) {
                $model->add_language($language);
            }
        }

        $model->update_default_lang('en');

        // Restore Mantle's handler
        EarlyIncorrectUsageHandler::register();

        // Reinitialize Polylang now that languages exist.
        $polylangBootstrap = new \Polylang();
        $polylangBootstrap->init();
    });
}

$manager->install();
