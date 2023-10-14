<?php
/**
 * Plugin Name: Page for custom post type
 * Plugin URI: https://github.com/nlemoine/page-for-custom-post-type
 * Description: Allows you to set pages for any custom post type archive
 * Version: 0.4.0
 * Author: Nicolas Lemoine
 * Author URI: https://niconico.fr/
 */

namespace n5s\PageForCustomPostType;

use WP_Admin_Bar;
use WP_Post;
use WP_Post_Type;
use WP_Query;

class Plugin
{
    public const QUERY_VAR_IS_PFCPT = 'is_page_for_custom_post_type';

    public const OPTION_PREFIX = 'page_for_';

    public const OPTION_PAGE_IDS = 'pages_for_custom_post_type';

    protected static ?Plugin $instance = null;

    private array $original_post_types_args = [];

    public function __construct()
    {
        if (\is_admin()) {
            \add_action('admin_menu', [$this, 'add_post_type_submenus']);
            \add_action('admin_init', [$this, 'add_reading_settings']);
            \add_action('admin_bar_menu', [$this, 'add_admin_bar_archive_link'], 80);
            \add_filter('display_post_states', [$this, 'display_post_states'], 100, 2);
        } else {
            \add_action('parse_query', [$this, 'set_page_for_custom_post_type_query'], 1);
            \add_filter('posts_where', [$this, 'posts_where'], 10, 2);
            \add_filter('wp_nav_menu_objects', [$this, 'set_current_ancestor'], 10, 2);
        }
        \add_action('registered_post_type', [$this, 'watch_options'], 10, 2);

        // Template hierarchy
        \add_action('template_redirect', [$this, 'on_template_redirect']);

        // Update post type args
        \add_filter('register_post_type_args', [$this, 'update_post_type_args'], 10, 2);

        // Update rewrite tags
        \add_action('registered_post_type', [$this, 'add_pagination_rewrite_tags'], 10, 2);

        // On post status changes
        \add_action('transition_post_status', [$this, 'on_transition_post_status'], 10, 3);

        // On post delete/trash
        // Do not use `deleted_post` because we can't mutualize with trashed hook
        \add_action('delete_post', [$this, 'on_deleted_post']);
        \add_action('wp_trash_post', [$this, 'on_deleted_post']);

        // On slug change
        \add_action('post_updated', [$this, 'on_slug_change'], 10, 3);
    }

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set current ancestor
     *
     * @param WP_Post[] $menu_items
     * @param array $args
     */
    public function set_current_ancestor($menu_items, $args): array
    {
        global $wp_query;
        $pages_ids = $this->get_page_ids();
        foreach ($menu_items as $key => $menu_item) {
            if (
                $wp_query->is_singular
                && isset($menu_item->type)
                && $menu_item->type === 'post_type'
                && isset($menu_item->object_id)
                && \in_array((int) $menu_item->object_id, $pages_ids, true)
                && !empty($wp_query->query['post_type'])
                && $this->get_post_type_from_page_id((int) $menu_item->object_id) === $wp_query->query['post_type']
            ) {
                $menu_items[$key]->classes[] = 'current-menu-ancestor';
                $menu_items[$key]->current_item_ancestor = true;
            }
        }
        return $menu_items;
    }

    /**
     * Enable pagination rules on page for CPT
     */
    public function add_pagination_rewrite_tags(string $post_type, WP_Post_Type $post_type_object): void
    {
        // Don't even try on those post types
        if (!$this->should_consider_post_type($post_type_object)) {
            return;
        }

        if (!$this->get_page_id_from_post_type($post_type, false)) {
            return;
        }

        $this->add_rewrite_tags($post_type_object->name);
    }

    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules(string $post_type): void
    {
        \do_action('pfcpt/flush_rewrite_rules', $post_type);

        \delete_transient($this->get_page_slug_cache_key($post_type));

        // Delete rewrite rules, will be generated on the next run
        \delete_option('rewrite_rules');

        // Remove existing rewrite rules
        // $post_type_object_current = \get_post_type_object($post_type);
        // if($post_type_object_current instanceof WP_Post_Type) {
        //     $post_type_object_current->remove_rewrite_rules();
        // }

        // if(!empty($this->original_post_types_args[$post_type])) {
        //     // Trigger post type registration hooks before flushing rewrite rules
        //     $post_type_object = new WP_Post_Type($post_type, $this->original_post_types_args[$post_type]);
        //     $post_type_object->add_rewrite_rules();
        //     do_action( 'registered_post_type', $post_type, $post_type_object );
        // }

        // // Flush rewrite rules
        // \flush_rewrite_rules();
    }

    /**
     * Modify post type object before it is registered.
     */
    public function update_post_type_args(array $args, string $post_type): array
    {
        // Don't even try on those post types
        if (
            !empty($args['_builtin'])
            || (isset($args['public']) && !$args['public'])
            || (isset($args['publicly_queryable']) && !$args['publicly_queryable'])
        ) {
            return $args;
        }

        // @todo Object cache?
        $this->original_post_types_args[$post_type] = $args;

        // Don't apply filters when getting the page ID, post type $args should the same no matter what language is used
        $page_id_for_post_type = $this->get_page_id_from_post_type($post_type, false);
        if (empty($page_id_for_post_type)) {
            return $args;
        }

        $page_id_for_post_type = \get_option($this->get_option_name($post_type));

        $post_type_slug = \get_transient($this->get_page_slug_cache_key($post_type));
        if ($post_type_slug === false) {
            // Make sure it's published
            if (\get_post_status($page_id_for_post_type) !== 'publish') {
                return $args;
            }

            // Get the page slug
            $post_type_slug = $this->get_page_slug($page_id_for_post_type);
            \set_transient($this->get_page_slug_cache_key($post_type), $post_type_slug);
        }

        // Set page slug
        $args['rewrite']['slug'] = $post_type_slug;

        // Disable archive
        $args['has_archive'] = false;

        return $args;
    }

    /**
     * Get page slug
     *
     * @param integer $page_id
     */
    public function get_page_slug(int $page_id): ?string
    {
        $page_url = \get_permalink($page_id);
        if ($page_url === false) {
            return null;
        }
        $page_path = \parse_url($page_url, PHP_URL_PATH);
        return \is_string($page_path) ? \trim($page_path, '/') : null;
    }

    /**
     * Remove page id condition.
     *
     * @param string   $where
     * @param WP_Query $query
     */
    public function posts_where($where, $query): string
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

    /**
     * Add option to Settings > Reading
     */
    public function add_reading_settings(): void
    {
        $post_types = $this->get_post_types();

        \add_settings_section('page_for_custom_post_type', \__('Pages for post type', 'pfcpt'), '__return_false', 'reading');

        foreach ($post_types as $post_type) {
            $field_id = $this->get_option_name($post_type);
            $value = \get_option($field_id);

            \register_setting('reading', $field_id, [
                'type'              => 'integer',
            ]);

            \add_settings_field(
                $field_id,
                $post_type->labels->name,
                [$this, 'page_for_post_type_field'],
                'reading',
                'page_for_custom_post_type',
                [
                    'name'      => $field_id,
                    'post_type' => $post_type,
                    'value'     => $value,
                    'label_for' => $field_id . '_dropdown',
                ]
            );
        }
    }

    /**
     * Validate option
     *
     * @param mixed $value
     * @param mixed $original_value
     */
    public function validate_setting($value, string $name, $original_value)
    {
        if (empty($value)) {
            return $value;
        }

        if (!\is_numeric($value)) {
            $error = \__('Invalid page ID', 'pfcpt');
        }

        $post_type = $this->get_post_type_from_option_name($name);
        $post_type_object = \get_post_type_object($post_type);

        // Check post status
        $page_status = \get_post_status($value);
        if ($page_status !== 'publish') {
            $error = \sprintf(
                \__('Page for %s post type (%s) is not published', 'pfcpt'),
                $post_type_object->labels->name,
                \get_the_title($value)
            );
        }

        $value = (int) $value;

        // Check for page id used twice
        $page_ids = \array_map(fn ($v) => \is_numeric($v) ? (int) $v : null, \array_filter($_POST, function ($k) use ($name) {
            return \strpos($k, self::OPTION_PREFIX) === 0
                && $name !== $k;
        }, ARRAY_FILTER_USE_KEY));

        // Only check for duplicate if the page id is not empty
        $old_value = (int) \get_option($name);
        if (\in_array($value, \array_filter($page_ids), true)
            && $value !== $old_value
        ) {
            $error = \sprintf(
                \__('Page for %s post type (%s) is already used', 'pfcpt'),
                $post_type_object->labels->name,
                \get_the_title($value)
            );
        }

        if (!empty($error)) {
            \add_settings_error($name, "invalid_{$name}", $error, 'error');
            // If we had an old value, keep it while showing the error
            if (!empty($old_value)) {
                return $old_value;
            }
        } else {
            return \absint($value);
        }
    }

    /**
     * Display the dropdown for selecting a page.
     */
    public function page_for_post_type_field(array $args): void
    {
        $value = \intval($args['value']);

        $post_type = $args['post_type']->name;
        $default_label = null;

        if (!empty($this->original_post_types_args[$post_type])) {
            \remove_filter('register_post_type_args', [$this, 'update_post_type_args'], 10);
            $post_type_object = new WP_Post_Type($post_type, $this->original_post_types_args[$post_type]);
            \add_filter('register_post_type_args', [$this, 'update_post_type_args'], 10, 2);

            global $wp_rewrite;
            if ($post_type_object->has_archive) {
                $archive_slug = $post_type_object->has_archive === true ? $post_type_object->rewrite['slug'] : $post_type_object->has_archive;
                if ($post_type_object->rewrite['with_front']) {
                    $archive_slug = \substr($wp_rewrite->front, 1) . $archive_slug;
                } else {
                    $archive_slug = $wp_rewrite->root . $archive_slug;
                }
                $default_label = \sprintf(\__('Default archive slug (/%s/)'), $archive_slug);
            } else {
                $default_label = \__('No archive', 'pfcpt');
            }
        }

        $dropdown_pages_args = \apply_filters('pfcpt/dropdown_page_args', [
            'name'             => \esc_attr($args['name']),
            'id'               => \esc_attr($args['name'] . '_dropdown'),
            'selected'         => $value,
            'show_option_none' => $default_label ?? \__('Unset'),
        ]);

        \wp_dropdown_pages($dropdown_pages_args); ?>

        <p class="description">
            <?php \printf(\esc_html__('Be extremely carefull, setting or changing the page for the "%s" custom post type will change all your "%s" URLs and may hurt SEO.'), \mb_strtolower($args['post_type']->labels->singular_name), \mb_strtolower($args['post_type']->labels->name)); ?>
        </p>
        <?php
    }

    /**
     * Add an indicator to show if a page is set as a post type archive.
     *
     * @param string[]   $post_states an array of post states to display after the post title
     * @param WP_Post $post        the current post object
     *
     * @return string[]
     */
    public function display_post_states($post_states, $post): array
    {
        if ($post->post_type !== 'page') {
            return $post_states;
        }

        $post_type = $this->get_post_type_from_page_id($post->ID);
        if (!$post_type) {
            return $post_states;
        }

        $post_type_object = \get_post_type_object($post_type);
        $name = $this->get_option_name($post_type);
        $post_states[$name] = \esc_html(\sprintf(\__('%s page'), $post_type_object->labels->name));

        return $post_states;
    }

    /**
     * Delete the setting for the corresponding post type if the page status
     * is transitioned to anything other than published.
     *
     * @param string $new_status
     * @param string $old_status
     */
    public function on_transition_post_status($new_status, $old_status, WP_Post $post): void
    {
        if ($post->post_type !== 'page') {
            return;
        }

        if ($new_status !== 'publish') {
            $post_type = $this->get_post_type_from_page_id($post->ID);
            if (!$post_type) {
                return;
            }

            $this->delete_option($post_type);
        }
    }

    /**
     * Delete relevant option if a page for post type is deleted or trashed
     *
     * @param int $post_id
     * @param ?WP_Post $post
     */
    public function on_deleted_post($post_id, ?WP_Post $post = null): void
    {
        if ($post === null) {
            $post = \get_post($post_id);
        }

        if (!\is_object($post)) {
            return;
        }

        if ($post->post_type !== 'page') {
            return;
        }

        $post_type = $this->get_post_type_from_page_id($post_id);
        if (!$post_type) {
            return;
        }

        $this->delete_option($post_type);
    }

    /**
     * Watch options for post types
     */
    public function watch_options(string $post_type, WP_Post_Type $post_type_object): void
    {
        // Don't even try on those post types
        if (!$this->should_consider_post_type($post_type_object)) {
            return;
        }

        // Sanitize hook
        \add_filter("sanitize_option_{$this->get_option_name($post_type)}", [$this, 'validate_setting'], 10, 3);

        // Watch for changes
        \add_action("update_option_{$this->get_option_name($post_type)}", [$this, 'on_option_update'], 10, 3);
        \add_action("add_option_{$this->get_option_name($post_type)}", [$this, 'on_option_add'], 10, 2);
        \add_action("delete_option_{$this->get_option_name($post_type)}", [$this, 'on_option_delete'], 10);
    }

    /**
     * On individual option update
     *
     * @param mixed $old_value
     * @param mixed $new_value
     * @param string $name
     */
    public function on_option_update($old_value, $new_value, $name): void
    {
        if ($old_value === $new_value) {
            return;
        }
        $this->on_option_change($name, $new_value);
    }

    /**
     * On individual option add
     *
     * @param mixed $value
     */
    public function on_option_add(string $name, $value): void
    {
        $this->on_option_change($name, $value);
    }

    /**
     * On individual option delete
     */
    public function on_option_delete(string $name): void
    {
        $this->on_option_change($name, null);
    }

    /**
     * Clear transients when a page slug changes
     *
     * @param integer $post_ID
     */
    public function on_slug_change(int $post_ID, WP_Post $post_after, WP_Post $post_before): void
    {
        if ($post_after->post_type !== 'page') {
            return;
        }

        if ($post_after->post_name === $post_before->post_name) {
            return;
        }

        $post_type = $this->get_post_type_from_page_id($post_ID);
        if (!$post_type) {
            return;
        }

        $this->flush_rewrite_rules($post_type);
    }

    /**
     * Checks if the current page is a page for custom post type.
     */
    public function is_query_page_for_custom_post_type(?WP_Query $query = null): bool
    {
        $q = $query === null ? $GLOBALS['wp_query'] : $query;
        return isset($q->{self::QUERY_VAR_IS_PFCPT}) && $q->{self::QUERY_VAR_IS_PFCPT};
    }

    /**
     * Change the template hierarchy on pages for custom post type
     *
     * @param string[] $templates
     *
     * @return string[]
     */
    public function set_home_template_hierarchy(array $templates): array
    {
        if (!isset($GLOBALS['wp_query']->{self::QUERY_VAR_IS_PFCPT})) {
            return $templates;
        }
        if (!\is_string($GLOBALS['wp_query']->{self::QUERY_VAR_IS_PFCPT})) {
            return $templates;
        }
        return \array_merge([
            "home-{$GLOBALS['wp_query']->{self::QUERY_VAR_IS_PFCPT}}",
        ], $templates);
    }

    /**
     * Add properties to the query object so we can use conditionals
     *
     * Two properties are added:
     * - is_page_for_custom_post_type (string|false) Either the post type or false
     * - is_{$post_type}_page (bool) Whether the current page is a page for the post type
     */
    public function set_page_for_custom_post_type_query(WP_Query $query): void
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
            $query->{self::QUERY_VAR_IS_PFCPT} = false;
        }

        if (!\in_array($current_page_id, $page_ids, true)) {
            return;
        }

        $post_type = \array_search($current_page_id, $page_ids, true);
        if (empty($post_type)) {
            return;
        }

        $query->is_singular = $query->is_page = false;
        $query->is_home = true;
        $query->{$this->get_conditional_name($post_type)} = true;
        $query->set('post_type', $post_type);
        $query->{self::QUERY_VAR_IS_PFCPT} = $post_type;
        $query->is_posts_page = true;

        // Prevent WP from mistakenly thinking this is a front page
        // When 'posts' is set as show_on_front
        // https://github.com/WordPress/wordpress-develop/blob/781953641607c4d5b0743a6924af0e820fd54871/src/wp-includes/class-wp-query.php#L4323-L4325
        if (\get_option('show_on_front') === 'posts') {
            \add_filter('pre_option_show_on_front', function ($value) {
                return null;
            });
        }

        \add_filter('home_template_hierarchy', [$this, 'set_home_template_hierarchy']);
        \add_filter('frontpage_template_hierarchy', '__return_empty_array');
    }

    /**
     * On template redirect
     *
     * Set template hierarchy
     */
    public function on_template_redirect(): void
    {
        if (!$this->{self::QUERY_VAR_IS_PFCPT}()) {
            return;
        }

        \do_action('pfcpt/template_redirect');
    }

    /**
     * Get page ID from query.
     */
    public function get_page_id_from_query(WP_Query $query): ?int
    {
        if (!empty($query->query_vars['pagename']) && $query->queried_object_id) {
            return (int) $query->queried_object_id;
        }

        if (isset($query->query_vars['page_id'])) {
            return (int) $query->query_vars['page_id'];
        }

        return 0;
    }

    /**
     * Get page ids.
     *
     * @return int[]
     */
    public function get_page_ids(bool $apply_filters = true): array
    {
        $page_ids = \get_option(self::OPTION_PAGE_IDS, []);
        return \array_map(fn ($id) => (int) $id, $apply_filters ? \apply_filters('pfcpt/page_ids', $page_ids) : $page_ids);
    }

    /**
     * Get option name.
     *
     * @param string|WP_Post_Type $post_type
     */
    public function get_option_name($post_type): string
    {
        if (\is_string($post_type)) {
            $name = $post_type;
        }
        if ($post_type instanceof WP_Post_Type) {
            $name = $post_type->name;
        }

        return self::OPTION_PREFIX . $name;
    }

    /**
     * Is page for post type.
     *
     * @param ?string $post_type
     * @return boolean
     */
    public function is_page_for_custom_post_type(?string $post_type = null): bool
    {
        $is_page_for_custom_post_type = $this->is_query_page_for_custom_post_type();
        if (!$is_page_for_custom_post_type) {
            return false;
        }

        $current_post_type = $GLOBALS['wp_query']->{self::QUERY_VAR_IS_PFCPT} ?? null;
        if ($post_type === null) {
            return (bool) $current_post_type;
        }
        return $post_type === $current_post_type;
    }

    /**
     * Add archive link to admin bar
     */
    public function add_admin_bar_archive_link(WP_Admin_Bar $admin_bar): void
    {
        $current_screen = \get_current_screen();
        $post_type_object = null;
        if ($current_screen->base !== 'edit') {
            return;
        }
        $post_type_object = \get_post_type_object($current_screen->post_type);
        if (
            ($post_type_object)
            && ($post_type_object->public)
            && ($post_type_object->show_in_admin_bar)
            && (\get_page_url_for_custom_post_type($post_type_object->name))
        ) {
            $admin_bar->add_menu([
                'id'    => 'archive',
                'title' => $post_type_object->labels->view_items,
                'href'  => \get_page_url_for_custom_post_type($post_type_object->name),
                'meta'  => [
                    'target' => '_blank',
                ],
            ]);
        }
    }

    /**
     * Add submenu link to archive under each post type
     */
    public function add_post_type_submenus(): void
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
     * Get page id for post type
     */
    public function get_page_id_from_post_type(string $post_type, bool $apply_filters = true): ?int
    {
        $page_ids = $this->get_page_ids($apply_filters);
        return $page_ids[$post_type] ?? null;
    }

    /**
     * Get page id for post type
     */
    public function get_post_type_from_page_id(int $page_id): ?string
    {
        $page_ids = $this->get_page_ids();
        $page_id = \apply_filters('pfcpt/post_type_from_id/page_id', $page_id);
        return \array_search($page_id, $page_ids, true) ?: null;
    }

    /**
     * Add rewrite tags
     */
    protected function add_rewrite_tags(string $post_type): void
    {
        \remove_rewrite_tag("%{$post_type}%");
        // Exclude page from regex so pagination works
        // add_rewrite_tag("%{$post_type_object->name}%", '(\b(?!page\b)[^/]+)', "{$post_type_object->name}=");
        \add_rewrite_tag("%{$post_type}%", '(?!page)([^/]+)', "{$post_type}=");
    }

    /**
     * Get cache key for page slug
     */
    protected function get_page_slug_cache_key(string $post_type): string
    {
        return self::OPTION_PREFIX . $post_type . '_slug';
    }

    /**
     * On individual option change
     *
     * @param mixed $value
     */
    protected function on_option_change(string $name, $value): void
    {
        $post_type = $this->get_post_type_from_option_name($name);

        $page_ids = \get_option(self::OPTION_PAGE_IDS, []);
        $page_ids[$post_type] = $value;

        // Update main options
        \update_option(self::OPTION_PAGE_IDS, \array_filter($page_ids));

        $this->flush_rewrite_rules($post_type);
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    private function should_consider_post_type(WP_Post_Type $post_type): bool
    {
        if ($post_type->_builtin || !$post_type->publicly_queryable) {
            return false;
        }
        return true;
    }

    /**
     * Get post type from option name
     */
    private function get_post_type_from_option_name(string $name): string
    {
        return \substr($name, \strlen(self::OPTION_PREFIX));
    }

    /**
     * Clear page for post type cache
     */
    private function delete_option(string $post_type): void
    {
        \delete_option($this->get_option_name($post_type));
    }

    /**
     * Get post types.
     *
     * @return WP_Post_Type[]
     */
    private function get_post_types(): array
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
    private function get_conditional_name($post_type): string
    {
        return "is_{$this->get_post_type_name($post_type)}_page";
    }

    /**
     * Get the post type name.
     *
     * @param string|WP_Post_Type $post_type
     */
    private function get_post_type_name($post_type): string
    {
        if ($post_type instanceof WP_Post_Type) {
            return $post_type->name;
        }
        return $post_type;
    }
}
