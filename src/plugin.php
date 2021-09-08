<?php
/**
 * Plugin Name: Page for custom post type
 * Plugin URI: https://github.com/nlemoine/page-for-custom-post-type
 * Description: Allows you to set pages for any custom post type archive
 * Version: 0.2.0
 * Author: Nicolas Lemoine
 * Author URI: https://hellonic.co/.
 */

namespace HelloNico\PageForCustomPostType;

class Plugin
{
    const PREFIX = 'page_for_';
    const CACHE_KEY = 'pages_for_custom_post_type';

    protected static $instance;

    public function __construct()
    {
        // admin init
        \add_action('admin_init', [$this, 'admin_init']);

        \add_action('admin_menu', [$this, 'add_post_type_submenus']);

        \add_action('admin_bar_menu', [$this, 'add_admin_bar_archive_link'], 80);

        // update post type objects
        \add_filter('register_post_type_args', [$this, 'update_post_type_args'], 11, 2);

        // Replace rewrite tag
        \add_action('registered_post_type', function ($post_type, $post_type_object) {
            if (!\in_array($post_type, \array_keys($this->get_page_ids()))) {
                return;
            }
            \remove_rewrite_tag("%{$post_type_object->name}%");
            // Exclude page from regex so pagination works
            // add_rewrite_tag("%{$post_type_object->name}%", '(\b(?!page\b)[^/]+)', "{$post_type_object->name}=");
            \add_rewrite_tag("%{$post_type_object->name}%", '(?!page)([^/]+)', "{$post_type_object->name}=");
        }, 10, 2);

        // edit.php view
        \add_filter('display_post_states', [$this, 'display_post_states'], 100, 2);

        // post status changes / deletion
        \add_action('transition_post_status', [$this, 'action_transition_post_status'], 10, 3);
        \add_action('deleted_post', [$this, 'action_deleted_post'], 10);

        if (!\is_admin()) {
            \add_filter('parse_query', [$this, 'set_page_for_custom_post_type_query'], 1);
            \add_filter('posts_where', [$this, 'posts_where'], 10, 2);
        }

        // Update cache
        \add_action('admin_init', function () {
            foreach ($this->get_post_types() as $post_type) {
                \add_action('update_option_'.$this->get_option_name($post_type), function ($old_value, $value, $option) use ($post_type) {
                    $this->update_cache($value, $post_type->name);
                }, 10, 3);
                \add_action('add_option_'.$this->get_option_name($post_type), function ($option, $value) use ($post_type) {
                    $this->update_cache($value, $post_type->name);
                }, 10, 3);
            }
        }, PHP_INT_MAX);

        // Polylang
        if (!\is_admin()) {
            \add_action('init', function () {
                foreach ($this->get_post_types() as $post_type) {
                    $option_name = $this->get_option_name($post_type);
                    \add_action('option_'.$option_name, function ($value) use ($post_type, $option_name) {
                        $pll = \PLL();

                        return isset($pll->curlang->{$option_name}) && !\doing_action('switch_blog') ? $pll->curlang->{$option_name} : $value;
                    });
                }
            }, PHP_INT_MAX);
        }
        \add_filter('pll_languages_list', [$this, 'add_post_for_page_to_language'], 10, 2);
        \add_filter('pll_set_language_from_query', [$this, 'page_for_custom_post_type_query'], 10, 2);
        \add_filter('pll_pre_translation_url', [$this, 'translate_page_for_custom_post_type'], 1, 3);

        // Fix Yoast SEO breadcrumbs
        \add_filter('wpseo_breadcrumb_indexables', function($indexables, $context) {
            if( !\is_singular() && !\is_tax() ) {
                return $indexables;
            }
            $post_type = \get_post_type();
            $page_id = \get_page_for_custom_post_type($post_type);
            if(!$page_id) {
                return $indexables;
            }

            \array_splice( $indexables, 1, 0, [\YoastSEO()->meta->for_post( $page_id )->context->indexable] );

            return $indexables;
        }, 10, 2);


        \add_action('template_redirect', function () {
            if (!$this->is_page_for_custom_post_type()) {
                return;
            }

            // Yoast SEO
            // Make Yoast SEO it's a static page for posts
            \add_filter('pre_option_show_on_front', function() {
                return 'page';
            });
            \add_filter('pre_option_page_for_posts', function () {
                return \get_queried_object_id();
            });

            // Template hierarchy
            \add_filter('home_template_hierarchy', [$this, 'set_template_hierarchy']);
            \add_filter('frontpage_template_hierarchy', function ($templates) {
                return [];
            });
        });
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function add_admin_bar_archive_link($admin_bar)
    {
        if (! \is_admin()) {
            return $admin_bar;
        }
        $current_screen = \get_current_screen();
        $post_type_object = null;
        if ('edit' !== $current_screen->base) {
            return $admin_bar;
        }
        $post_type_object = \get_post_type_object($current_screen->post_type);
        if(
            ( $post_type_object )
            && ( $post_type_object->public )
            && ( $post_type_object->show_in_admin_bar )
            && ( \get_page_for_custom_post_type_link( $post_type_object->name ) )
        ) {
            $admin_bar->add_menu([
                'id'    => 'archive',
                'title' => $post_type_object->labels->view_items,
                'href'  => \get_page_for_custom_post_type_link( $post_type_object->name ),
                'meta'  => [
                    'target' => '_blank',
                ]
            ]);
        }
    }

    public function add_post_type_submenus()
    {
        $page_ids = $this->get_page_ids();
        if (empty($page_ids)) {
            return;
        }
        foreach ($page_ids as $post_type => $page_id) {
            $post_type_object = \get_post_type_object($post_type);
            \add_submenu_page(
                'edit.php?post_type=' . $post_type,
                $post_type_object->labels->archives,
                $post_type_object->labels->archives,
                'edit_pages',
                \get_edit_post_link($page_id)
            );
        }
    }

    /**
     * Undocumented function
     *
     * @param boolean $query
     * @return boolean
     */
    private function is_query_page_for_custom_post_type($query = false)
    {
        $q = $query;
        unset($q);
        $_q = $_q ?? $GLOBALS['wp_query'] ?? false;
        if (!$_q) {
            return false;
        }
        return $_q->is_page_for_custom_post_type;
    }

    /**
     * Undocumented function.
     *
     * @param [type] $templates
     */
    public function set_template_hierarchy($templates)
    {
        $temps = \array_merge(["home-{$GLOBALS['wp_query']->is_page_for_custom_post_type}.php"], $templates);
        return $temps;
    }

    /**
     * Change query.
     *
     * @param WP_Query $query
     */
    public function set_page_for_custom_post_type_query($query)
    {
        if (!$query->is_main_query()) {
            return;
        }

        $current_page_id = $this->get_page_id_from_query($query);
        if (!$current_page_id) {
            return;
        }

        $page_ids = $this->get_page_ids();

        // Set conditionals
        foreach (\array_keys($page_ids) as $post_type) {
            $query->{$this->get_conditional_name($post_type)} = false;
            $query->is_page_for_custom_post_type = false;
        }

        if (!\in_array($current_page_id, $page_ids, true)) {
            return;
        }

        $post_type = \array_search($current_page_id, $page_ids);
        if (empty($post_type)) {
            return;
        }

        $query->is_singular = $query->is_page = false;
        $query->is_home = true;
        $query->{$this->get_conditional_name($post_type)} = true;
        $query->set('post_type', $post_type);
        $query->is_page_for_custom_post_type = $post_type;
    }

    /**
     * Handle Polylang translations.
     *
     * @param PLL_Language $lang
     * @param WP_Query     $query
     *
     * @see https://github.com/polylang/polylang/blob/master/frontend/frontend-static-pages.php#L220-L244
     */
    public function page_for_custom_post_type_query($lang, $query)
    {
        if (!$this->is_query_page_for_custom_post_type($query)) {
            return $lang;
        }

        if (!empty($lang)) {
            return $lang;
        }

        $current_post_type = $query->is_page_for_custom_post_type;
        $current_page_id = $this->get_page_id_from_query($query);

        $page_ids = $this->get_page_ids();
        if (!\in_array($current_page_id, $page_ids, true)) {
            return;
        }

        $page_for_name = $this->get_option_name($current_post_type);

        $pll = \PLL();
        if ($pll->curlang->{$page_for_name}) {
            $pages = $pll->model->get_languages_list(['fields' => $page_for_name]);
            if (!empty($current_page_id) && \in_array($current_page_id, $pages)) {
                // Fill the cache with all pages for post type to avoid one query per page later
                // The posts_per_page limit is a trick to avoid splitting the query
                \get_posts(['posts_per_page' => 999, 'post_type' => 'page', 'post__in' => $pages, 'lang' => '']);

                $lang = $pll->model->post->get_language($current_page_id);
            }
        }

        return $lang;
    }

    /**
     * Translates the url of the page on front and page for posts.
     *
     * @param string $url               not used
     * @param object $language          language in which we want the translation
     * @param int    $queried_object_id id of the queried object
     *
     * @return string
     */
    public function translate_page_for_custom_post_type($url, $language, $queried_object_id)
    {
        if (!empty($queried_object_id)) {
            $pll = \PLL();
            // Page for custom post type
            if ($this->is_query_page_for_custom_post_type() && ($id = $pll->model->post->get($queried_object_id, $language))) {
                $url = \get_permalink($id);
            }
        }

        return $url;
    }

    /**
     * Add post type for page to Polylang languages list.
     *
     * @param array     $languages
     * @param PLL_Model $model
     *
     * @return array
     */
    public function add_post_for_page_to_language($languages, $model)
    {
        foreach ($languages as $k => $language) {
            foreach ($this->get_page_ids() as $post_type => $page_id) {
                $name = $this->get_option_name($post_type);
                $languages[$k]->{$name} = $model->post->get(\get_option($name), $language);
            }
        }

        return $languages;
    }

    /**
     * Modify post type object before it is registered.
     *
     * @param array  $args
     * @param string $name
     * @param mixed  $post_type
     *
     * @return array
     */
    public function update_post_type_args($args, $post_type)
    {
        if (!\in_array($post_type, \array_keys($this->get_page_ids()))) {
            return $args;
        }

        $post_type_page = \get_option($this->get_option_name($post_type));

        // Make sure we have a page for this post type
        if (!$post_type_page) {
            return $args;
        }

        // Make sure it's published
        if ('publish' !== \get_post_status($post_type_page)) {
            return $args;
        }

        // Get the page slug
        $page_url = \get_permalink($post_type_page);
        $page_slug = \trim(\parse_url($page_url, PHP_URL_PATH), '/');

        // Set page slug
        $args['rewrite']['slug'] = $page_slug;

        // Disable archive
        if (isset($args['has_archive']) && $args['has_archive']) {
            $args['has_archive'] = false;
        }

        return $args;
    }

    /**
     * Remove page id condition.
     *
     * @param string   $where
     * @param WP_Query $query
     */
    public function posts_where($where, $query)
    {
        if (!$this->is_query_page_for_custom_post_type($query)) {
            return $where;
        }
        $current_page_id = $this->get_page_id_from_query($query);
        if (!$current_page_id) {
            return $where;
        }

        if (!\in_array($current_page_id, $this->get_page_ids(), true)) {
            return $where;
        }

        global $wpdb;

        return \str_replace("AND ({$wpdb->posts}.ID = '{$current_page_id}')", '', $where);
    }

    public function admin_init()
    {
        $post_types = $this->get_post_types();

        \add_settings_section('page_for_custom_post_type', \__('Pages for post type archives', 'pfpt'), '__return_false', 'reading');

        foreach ($post_types as $post_type) {
            if (!$post_type->has_archive) {
                // continue;
            }

            $id = $this->get_option_name($post_type);
            $value = \get_option($id);

            // flush rewrite rules when the option is changed
            \register_setting('reading', $id, [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default_value'     => false,
            ]);

            \add_settings_field(
                $id,
                $post_type->labels->name,
                [$this, 'custom_post_type_field'],
                'reading',
                'page_for_custom_post_type',
                [
                    'name'      => $id,
                    'post_type' => $post_type,
                    'value'     => $value,
                    'label_for' => $id.'_dropdown',
                ]
            );
        }
    }

    public function custom_post_type_field($args)
    {
        $value = \intval($args['value']);

        $default = $args['post_type']->name;

        if (isset($this->original_slugs[$args['post_type']->name])) {
            $default = $this->original_slugs[$args['post_type']->name];
        }

        \wp_dropdown_pages([
            'name'             => \esc_attr($args['name']),
            'id'               => \esc_attr($args['name'].'_dropdown'),
            'selected'         => $value,
            'show_option_none' => \sprintf(\__('Default (/%s/)'), $default),
        ]); ?>
        <p class="description"><?php \printf(\esc_html__('Be extremely carefull, setting or changing the page for the %s custom post type will change all your %s URLs and may hurt SEO.'), \mb_strtolower($args['post_type']->labels->singular_name), \mb_strtolower($args['post_type']->labels->name)); ?></p>
        <?php
    }

    /**
     * Add an indicator to show if a page is set as a post type archive.
     *
     * @param array   $post_states an array of post states to display after the post title
     * @param WP_Post $post        the current post object
     *
     * @return array
     */
    public function display_post_states($post_states, $post)
    {
        if ('page' !== $post->post_type) {
            return $post_states;
        }

        $post_types = $this->get_post_types();
        $language = false;
        if (\function_exists('pll_get_post_language')) {
            $language = \pll_get_post_language($post->ID);
        }

        $page_ids = $this->get_page_ids($language);

        if (\in_array($post->ID, $page_ids, true)) {
            $post_type = \array_search($post->ID, $page_ids);
            $name = $this->get_option_name($post_type);
            if (isset($post_types[$post_type])) {
                $post_states[$name] = \esc_html($post_types[$post_type]->labels->archives);
            }
        }

        return $post_states;
    }

    /**
     * Delete the setting for the corresponding post type if the page status
     * is transitioned to anything other than published.
     *
     * @param $new_status
     * @param $old_status
     */
    public function action_transition_post_status($new_status, $old_status, WP_Post $post)
    {
        if ('publish' !== $new_status) {
            $post_type = \array_search($post->ID, $this->get_page_ids());
            if ($post_type) {
                \delete_option("page_for_{$post_type}");
                \flush_rewrite_rules();
            }
        }
    }

    /**
     * Delete relevant option if a page for the archive is deleted.
     *
     * @param int $post_id
     */
    public function action_deleted_post($post_id)
    {
        $post_type = \array_search($post_id, $this->get_page_ids());
        if ($post_type) {
            \delete_option("page_for_{$post_type}");
            \flush_rewrite_rules();
        }
    }

    /**
     * Get page ID from query.
     *
     * @param WP_Query $query
     *
     * @return int
     */
    protected function get_page_id_from_query($query)
    {
        if (!empty($query->query_vars['pagename']) && isset($query->queried_object_id)) {
            return $query->queried_object_id;
        }

        if (isset($query->query_vars['page_id'])) {
            return $query->query_vars['page_id'];
        }

        return 0; // No page queried
    }

    /**
     * Get page ids.
     *
     * @param mixed $language
     *
     * @return array|bool
     */
    public function get_page_ids($language = false)
    {
        $page_ids = \get_transient($this::CACHE_KEY);
        if (false === $page_ids) {
            $page_ids = [];
        }

        return \array_map(function ($id) use ($language) {
            if (\function_exists('pll_get_post')) {
                $default_lang = \pll_default_language();
                $current_language = !$language ? \pll_current_language() : $language;
                if ($default_lang !== $current_language) {
                    $id = \pll_get_post($id, $current_language);
                }
            }

            return \is_numeric($id) && $id > 0 ? (int) $id : (bool) $id;
        }, $page_ids);
    }

    /**
     * Updates mapping cache.
     *
     * @param int    $page_id
     * @param string $post_type_name
     */
    private function update_cache($page_id, $post_type_name)
    {
        $mapping = \get_transient($this::CACHE_KEY);
        if (empty($mapping)) {
            $mapping = [];
        }
        $mapping[$post_type_name] = $page_id;
        \set_transient($this::CACHE_KEY, $mapping);

        \flush_rewrite_rules();

        // Clean languages cache
        if (\function_exists('PLL')) {
            \PLL()->clean_languages_cache();
        }
    }

    /**
     * Get post types.
     *
     * @return array
     */
    private function get_post_types()
    {
        return \get_post_types(
            [
                'publicly_queryable' => true,
                '_builtin'           => false,
            ],
            'objects'
        );
    }

    /**
     * Get option name.
     *
     * @param string|WP_Post_Type $post_type
     */
    private function get_option_name($post_type)
    {
        if (\is_string($post_type)) {
            $name = $post_type;
        }
        if (\is_a($post_type, 'WP_Post_Type')) {
            $name = $post_type->name;
        }

        return $this::PREFIX.$name;
    }

    /**
     * Get option name.
     *
     * @param string|WP_Post_Type $post_type
     */
    private function get_conditional_name($post_type)
    {
        if (\is_string($post_type)) {
            $name = $post_type;
        }
        if (\is_a($post_type, 'WP_Post_Type')) {
            $name = $post_type->name;
        }

        return 'is_'.$name.'_page';
    }

    public function is_page_for_custom_post_type($post_type = null)
    {
        $post_type_page = $this->is_query_page_for_custom_post_type();
        if (\is_null($post_type)) {
            return !!$post_type_page;
        }

        if (!\is_array($post_type)) {
            $post_type = [$post_type];
        }

        return \in_array($post_type_page, $post_type, true);
    }
}
