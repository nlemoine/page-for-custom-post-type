<?php

require_once __DIR__ . '/../vendor/autoload.php';

$plugins = [
    'wordpress-seo/wp-seo.php',
    'polylang/polylang.php',
];
$plugins_env = $_ENV['PLUGINS'] ?? $_SERVER['PLUGINS'] ?? '';
$plugins_env = explode(',', $plugins_env);
$plugins_to_load = array_filter(array_map(function ($p) use ($plugins_env) {
    return in_array(dirname($p), $plugins_env, true) ? $p : null;
}, $plugins));

\Mantle\Testing\manager()
    // ->with_sqlite()
    ->loaded(function () use ($plugins_to_load): void {
        require __DIR__ . '/../plugin.php';
        foreach ($plugins_to_load as $plugin) {
            require __DIR__ . '/../wp-content/plugins/' . $plugin;
        }
    })
    ->plugins(array_merge([
        'page-for-custom-post-type/plugin.php',
    ], $plugins_to_load))
    ->init(function () {
        register_post_type('book', [
            'public'  => true,
            'label'   => 'Books',
            'rewrite' => [
                'slug' => 'books',
            ],
        ]);
        register_taxonomy('genre', 'book', [
            'public'  => true,
            'label'   => 'Genres',
            'rewrite' => [
                'slug' => 'genres',
            ],
        ]);
        register_taxonomy_for_object_type('genre', 'book');
    })
    ->install();
