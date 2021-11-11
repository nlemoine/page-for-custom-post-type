<?php
/**
 * Plugin Name: Page for custom post type
 * Plugin URI: https://github.com/nlemoine/page-for-custom-post-type
 * Description: Allows you to set pages for any custom post type archive
 * Version: 0.3.0
 * Author: Nicolas Lemoine
 * Author URI: https://niconico.fr/
 */

namespace HelloNico\PageForCustomPostType;

use WP_Admin_Bar;
use WP_Post;
use WP_Post_Type;
use WP_Query;

class Plugin
{
    public const OPTION_PREFIX = 'page_for_';
    public const OPTION_PAGE_IDS = 'pages_for_custom_post_type';
    private $original_post_types_args = [];

    protected static $instance;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        if (\is_admin()) {
            \add_action('admin_menu', [$this, 'add_post_type_submenus']);
            \add_action('admin_init', [$this, 'add_reading_settings']);
            \add_action('admin_bar_menu', [$this, 'add_admin_bar_archive_link'], 80);
            \add_filter('display_post_states', [$this, 'display_post_states'], 100, 2);
            \add_action('registered_post_type', [$this, 'watch_options'], 10, 2);
        } else {
            \add_action('parse_query', [$this, 'set_page_for_custom_post_type_query'], 1);
            \add_filter('posts_where', [$this, 'posts_where'], 10, 2);
        }

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

    /**
     * Enable pagination rules on page for CPT
     *
     * @param string $post_type
     * @param WP_Post_Type $post_type_object
     * @return void
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
     * Add rewrite tags
     *
     * @param string $post_type
     * @return void
     */
    protected function add_rewrite_tags(string $post_type)
    {
        \remove_rewrite_tag("%{$post_type}%");
        // Exclude page from regex so pagination works
        // add_rewrite_tag("%{$post_type_object->name}%", '(\b(?!page\b)[^/]+)', "{$post_type_object->name}=");
        \add_rewrite_tag("%{$post_type}%", '(?!page)([^/]+)', "{$post_type}=");
    }

    /**
     * Flush rewrite rules
     *
     * @param string $post_type
     * @return void
     */
    public function flush_rewrite_rules(string $post_type)
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
     *
     * @param array  $args
     * @param string $name
     * @param mixed  $post_type
     *
     * @return array
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
            if ('publish' !== \get_post_status($page_id_for_post_type)) {
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
     * @return string|null
     */
    public function get_page_slug(int $page_id): ?string
    {
        $page_url = \get_permalink($page_id);
        return $page_url ? \trim(\parse_url($page_url, PHP_URL_PATH), '/') : null;
    }

    /**
     * Get cache key for page slug
     *
     * @param string $post_type
     * @return string
     */
    protected function get_page_slug_cache_key(string $post_type): string
    {
        return self::OPTION_PREFIX . $post_type . '_slug';
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
     *
     * @return void
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
     * Valie option
     *
     * @param mixed $value
     * @param string $name
     * @param mixed $original_value
     * @return void
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
            return
                \strpos($k, self::OPTION_PREFIX) === 0
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
     *
     * @param array $args
     * @return void
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
                $archive_slug = true === $post_type_object->has_archive ? $post_type_object->rewrite['slug'] : $post_type_object->has_archive;
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
     * @param array   $post_states an array of post states to display after the post title
     * @param WP_Post $post        the current post object
     *
     * @return array
     */
    public function display_post_states($post_states, $post): array
    {
        if ('page' !== $post->post_type) {
            return $post_states;
        }

        $post_type = $this->get_post_type_from_page_id($post->ID);
        if (!$post_type) {
            return $post_states;
        }

        $post_type_object = \get_post_type_object($post_type);
        $name = $this->get_option_name($post_type);
        $post_states[$name] = \esc_html($post_type_object->labels->archives);

        return $post_states;
    }

    /**
     * Delete the setting for the corresponding post type if the page status
     * is transitioned to anything other than published.
     *
     * @param $new_status
     * @param $old_status
     */
    public function on_transition_post_status($new_status, $old_status, WP_Post $post): void
    {
        if ($post->post_type !== 'page') {
            return;
        }

        if ('publish' !== $new_status) {
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
        if (\is_null($post)) {
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
     * Undocumented function
     *
     * @param WP_Post_Type $post_type
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
     * Watch options for post types
     *
     * @param string $post_type
     * @param WP_Post_Type $post_type_object
     * @return void
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
        \add_action("delete_option_{$this->get_option_name($post_type)}", [$this, 'on_option_delete'], 10, 2);
    }

    /**
     * On individual option update
     *
     * @param mixed $old_value
     * @param mixed $new_value
     * @param string $name
     * @return void
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
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function on_option_add(string $name, $value): void
    {
        $this->on_option_change($name, $value);
    }

    /**
     * On individual option delete
     *
     * @param string $name
     * @return void
     */
    public function on_option_delete(string $name): void
    {
        $this->on_option_change($name, null);
    }

    /**
     * On individual option change
     *
     * @param string $name
     * @return void
     */
    protected function on_option_change(string $name, $value): void
    {
        $post_type = $this->get_post_type_from_option_name($name);

        $page_ids = \get_option($this::OPTION_PAGE_IDS, []);
        $page_ids[$post_type] = $value;

        // Update main options
        \update_option($this::OPTION_PAGE_IDS, \array_filter($page_ids));

        $this->flush_rewrite_rules($post_type);
    }

    /**
     * Get post type from option name
     *
     * @param string $name
     * @return string
     */
    private function get_post_type_from_option_name(string $name): string
    {
        return \substr($name, \strlen(self::OPTION_PREFIX));
    }

    /**
     * Clear transients when a page slug changes
     *
     * @param integer $post_ID
     * @param WP_Post $post_after
     * @param WP_Post $post_before
     * @return void
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
     * Clear page for post type cache
     *
     * @param string $post_type
     * @return void
     */
    private function delete_option(string $post_type): void
    {
        \delete_option($this->get_option_name($post_type));
    }

    /**
     * Undocumented function
     *
     * @param WP_Query|null $query
     * @return bool
     */
    public function is_query_page_for_custom_post_type(?WP_Query $query = null)
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
    public function set_template_hierarchy($templates): array
    {
        $temps = \array_merge(["home-{$GLOBALS['wp_query']->is_page_for_custom_post_type}"], $templates);
        return $temps;
    }

    /**
     * Change query.
     *
     * @param WP_Query $query
     */
    public function set_page_for_custom_post_type_query($query): void
    {
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

        \add_filter('home_template_hierarchy', [$this, 'set_template_hierarchy']);
        \add_filter('frontpage_template_hierarchy', '__return_empty_array');
    }

    /**
     * On template redirect
     *
     * Set template hierarchy
     */
    public function on_template_redirect(): void
    {
        if (!$this->is_page_for_custom_post_type()) {
            return;
        }

        \do_action('pfcpt/template_redirect');
    }

    /**
     * Get page ID from query.
     *
     * @param WP_Query $query
     *
     * @return int
     */
    public function get_page_id_from_query($query): int
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
     * Caches the result with transient.
     *
     * @return array
     */
    public function get_page_ids($apply_filters = true): array
    {
        $page_ids = \get_option(self::OPTION_PAGE_IDS, []);
        return \array_map(fn ($id) => (int) $id, $apply_filters ? \apply_filters('pfcpt/page_ids', $page_ids) : $page_ids);
    }

    /**
     * Get post types.
     *
     * @return array
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
    public function get_option_name($post_type): string
    {
        if (\is_string($post_type)) {
            $name = $post_type;
        }
        if (\is_a($post_type, 'WP_Post_Type')) {
            $name = $post_type->name;
        }

        return self::OPTION_PREFIX . $name;
    }

    /**
     * Get option name.
     *
     * @param string|WP_Post_Type $post_type
     */
    private function get_conditional_name($post_type): string
    {
        if (\is_string($post_type)) {
            $name = $post_type;
        }
        if (\is_a($post_type, 'WP_Post_Type')) {
            $name = $post_type->name;
        }

        return "is_{$name}_page";
    }

    /**
     * Is page for post type.
     *
     * @param ?string $post_type
     * @return boolean
     */
    public function is_page_for_custom_post_type(?string $post_type = null): bool
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

    /**
     * Add archive link to admin bar
     *
     * @param [type] $admin_bar
     * @return void
     */
    public function add_admin_bar_archive_link(WP_Admin_Bar $admin_bar): void
    {
        $current_screen = \get_current_screen();
        $post_type_object = null;
        if ('edit' !== $current_screen->base) {
            return;
        }
        $post_type_object = \get_post_type_object($current_screen->post_type);
        if (
            ($post_type_object)
            && ($post_type_object->public)
            && ($post_type_object->show_in_admin_bar)
            && (\get_page_for_custom_post_type_link($post_type_object->name))
        ) {
            $admin_bar->add_menu([
                'id'    => 'archive',
                'title' => $post_type_object->labels->view_items,
                'href'  => \get_page_for_custom_post_type_link($post_type_object->name),
                'meta'  => [
                    'target' => '_blank',
                ]
            ]);
        }
    }

    /**
     * Add submenu link to archive under each post type
     *
     * @return void
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
     *
     * @param string $post_type
     * @return integer|null
     */
    public function get_page_id_from_post_type(string $post_type, $apply_filters = true): ?int
    {
        $page_ids = $this->get_page_ids($apply_filters);
        return $page_ids[$post_type] ?? null;
    }

    /**
     * Get page id for post type
     *
     * @param string $page_id
     * @return integer|null
     */
    public function get_post_type_from_page_id(int $page_id): ?string
    {
        $page_ids = $this->get_page_ids();
        $page_id = \apply_filters('pfcpt/post_type_from_id/page_id', $page_id);
        return \array_search($page_id, $page_ids, true) ?: null;
    }
}
