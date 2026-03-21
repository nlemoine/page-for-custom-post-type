<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use Mantle\Testing\Concerns\Admin_Screen;
use n5s\PageForCustomPostType\Admin\Admin;
use n5s\PageForCustomPostType\Plugin;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class AdminScreenTest extends TestCase
{
    use Admin_Screen;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();

        // Load admin includes needed for settings API
        if (!\function_exists('add_settings_section')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
        }
        if (!\function_exists('register_setting')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $container = Plugin::getInstance()->getContainer();
        $this->admin = $container->get(Admin::class);
    }

    public function testAddReadingSettingsRegistersSettingsSection(): void
    {
        $this->admin->addReadingSettings();

        $this->assertArrayHasKey(
            'page_for_custom_post_type',
            $GLOBALS['wp_settings_sections']['reading'],
        );
    }

    public function testAddReadingSettingsRegistersFieldsForEachPostType(): void
    {
        $this->admin->addReadingSettings();

        $fields = $GLOBALS['wp_settings_fields']['reading']['page_for_custom_post_type'] ?? [];

        $this->assertArrayHasKey('page_for_' . self::BOOK_POST_TYPE, $fields);
        $this->assertArrayHasKey('page_for_' . self::BIKE_POST_TYPE, $fields);
    }

    public function testAddReadingSettingsRegistersSettingsForEachPostType(): void
    {
        $this->admin->addReadingSettings();

        $registeredSettings = get_registered_settings();

        $this->assertArrayHasKey('page_for_' . self::BOOK_POST_TYPE, $registeredSettings);
        $this->assertArrayHasKey('page_for_' . self::BIKE_POST_TYPE, $registeredSettings);
    }

    public function testRenderPageDropdownOutputsSelectElement(): void
    {
        $this->acting_as('administrator');
        $this->admin->addReadingSettings();

        ob_start();
        do_settings_fields('reading', 'page_for_custom_post_type');
        $output = ob_get_clean();

        $this->assertStringContainsString('<select', $output);
        $this->assertStringContainsString('page_for_' . self::BOOK_POST_TYPE, $output);
        $this->assertStringContainsString('_use_slug', $output);
    }

    public function testAddPostTypeSubmenusAddsSubmenus(): void
    {
        $this->acting_as('administrator');

        $this->admin->addPostTypeSubmenus();

        // Check submenus were added for at least one post type
        $bookMenuKey = 'edit.php?post_type=' . self::BOOK_POST_TYPE;
        $this->assertArrayHasKey($bookMenuKey, $GLOBALS['submenu'] ?? []);
    }

    public function testGetExcludedPageIdsExcludesFrontPageAndPostsPage(): void
    {
        $frontPageId = static::factory()->post->create(['post_type' => 'page', 'post_status' => 'publish']);
        $postsPageId = static::factory()->post->create(['post_type' => 'page', 'post_status' => 'publish']);

        update_option('page_on_front', $frontPageId);
        update_option('page_for_posts', $postsPageId);

        $method = new \ReflectionMethod(Admin::class, 'getExcludedPageIds');
        $result = $method->invoke($this->admin);

        $this->assertContains($frontPageId, $result);
        $this->assertContains($postsPageId, $result);
    }

    public function testGetExcludedPageIdsReturnsEmptyWhenNoPagesSet(): void
    {
        delete_option('page_on_front');
        delete_option('page_for_posts');

        $method = new \ReflectionMethod(Admin::class, 'getExcludedPageIds');
        $result = $method->invoke($this->admin);

        $this->assertEmpty($result);
    }

    public function testGetDefaultLabelWithArchive(): void
    {
        // Need to ensure the PostType service has stored original args.
        // Re-register to trigger the filter which stores them.
        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        $method = new \ReflectionMethod(Admin::class, 'getDefaultLabel');
        $result = $method->invoke($this->admin, self::BOOK_POST_TYPE);

        $this->assertIsString($result);
        $this->assertStringContainsString('Default', $result);
    }

    public function testGetDefaultLabelWithoutArchive(): void
    {
        register_post_type('noarchive_cpt', [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => false,
            'label' => 'No Archive CPT',
        ]);

        $method = new \ReflectionMethod(Admin::class, 'getDefaultLabel');
        $result = $method->invoke($this->admin, 'noarchive_cpt');

        $this->assertIsString($result);
        $this->assertStringContainsString('No archive', $result);

        unregister_post_type('noarchive_cpt');
    }

    public function testGetDefaultLabelReturnsNullWhenNoOriginalArgs(): void
    {
        $method = new \ReflectionMethod(Admin::class, 'getDefaultLabel');
        $result = $method->invoke($this->admin, 'nonexistent_cpt');

        $this->assertNull($result);
    }

    public function testAddAdminBarArchiveLinkReturnsEarlyWithNoScreen(): void
    {
        $this->acting_as('administrator');

        // WP_Admin_Bar requires admin includes
        if (!\class_exists('WP_Admin_Bar')) {
            require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
        }

        $adminBar = new \WP_Admin_Bar();

        // No screen set → should return early
        $this->admin->addAdminBarArchiveLink($adminBar);

        $this->assertNull($adminBar->get_node('archive'));
    }

    public function testAddAdminBarArchiveLinkAddsMenuOnEditScreen(): void
    {
        $this->acting_as('administrator');

        if (!\class_exists('WP_Admin_Bar')) {
            require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
        }
        if (!\class_exists('WP_Screen')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        }
        if (!\function_exists('set_current_screen')) {
            require_once ABSPATH . 'wp-admin/includes/screen.php';
        }

        set_current_screen('edit-' . self::BOOK_POST_TYPE);

        $adminBar = new \WP_Admin_Bar();
        $adminBar->initialize();

        $this->admin->addAdminBarArchiveLink($adminBar);

        $node = $adminBar->get_node('archive');
        $this->assertNotNull($node, 'Archive link should be added to admin bar');
        $this->assertStringContainsString('home-for-books', $node->href);
    }

    public function testAddAdminBarArchiveLinkSkipsNonEditScreen(): void
    {
        $this->acting_as('administrator');

        if (!\class_exists('WP_Admin_Bar')) {
            require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
        }
        if (!\function_exists('set_current_screen')) {
            require_once ABSPATH . 'wp-admin/includes/screen.php';
        }

        // Set to a non-edit screen (dashboard)
        set_current_screen('dashboard');

        $adminBar = new \WP_Admin_Bar();
        $this->admin->addAdminBarArchiveLink($adminBar);

        $this->assertNull($adminBar->get_node('archive'));
    }

    public function testPluginAdminHooksAreRegisteredInAdminContext(): void
    {
        $plugin = \n5s\PageForCustomPostType\Plugin::getInstance();

        // Reset the initialized flag so init() re-runs in admin context
        $ref = new \ReflectionProperty(\n5s\PageForCustomPostType\Plugin::class, 'initialized');
        $ref->setValue($plugin, false);

        $plugin->init();

        // In admin context, admin hooks should be registered
        $this->assertNotFalse(has_action('admin_menu'));
        $this->assertNotFalse(has_action('admin_init'));
        $this->assertNotFalse(has_action('admin_bar_menu'));
        $this->assertNotFalse(has_filter('display_post_states'));
    }

    private function reRegisterPostType(string $postType): void
    {
        $postTypeObject = get_post_type_object($postType);
        if (!$postTypeObject instanceof \WP_Post_Type) {
            return;
        }
        $args = get_object_vars($postTypeObject);
        unregister_post_type($postType);
        register_post_type($postType, $args);
    }
}
