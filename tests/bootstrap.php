<?php

declare(strict_types=1);

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
    // Polylang language setup requires a two-phase approach:
    // 1. Use 'after' to create languages via PLL_Admin_Model (before Polylang::init).
    //    Suppress _doing_it_wrong notices since we're intentionally calling
    //    the model before pll_pre_init.
    // 2. Reinitialize Polylang so it picks up the new languages.
    $manager->after(static function (): void {
        if (!defined('POLYLANG_DIR')) {
            return;
        }

        $options = new \WP_Syntex\Polylang\Options\Options();
        $model = new \PLL_Admin_Model($options);

        $languages = [
            ['name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => false, 'term_group' => 0, 'flag' => 'us'],
            ['name' => 'Français', 'slug' => 'fr', 'locale' => 'fr_FR', 'rtl' => false, 'term_group' => 1, 'flag' => 'fr'],
        ];

        // Suppress _doing_it_wrong during language creation
        // (Polylang checks are_ready() which is false before pll_pre_init).
        \add_filter('doing_it_wrong_trigger_error', '__return_false');

        foreach ($languages as $language) {
            if (!$model->get_language($language['slug'])) {
                $model->add_language($language);
            }
        }

        \remove_filter('doing_it_wrong_trigger_error', '__return_false');

        $model->update_default_lang('en');

        // Reinitialize Polylang now that languages exist.
        $polylangBootstrap = new \Polylang();
        $polylangBootstrap->init();
    });
}

$manager->install();
