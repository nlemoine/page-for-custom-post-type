<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Admin\Admin;
use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Plugin;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for admin functionality.
 *
 * Tests admin UI features like post states and settings.
 */
class AdminTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();

        $container = Plugin::getInstance()->getContainer();
        $this->admin = $container->get(Admin::class);

        // get_current_screen() and set_current_screen() live in admin includes,
        // not loaded by default in integration tests.
        if (!\function_exists('set_current_screen')) {
            require_once \ABSPATH . 'wp-admin/includes/class-wp-screen.php';
            require_once \ABSPATH . 'wp-admin/includes/screen.php';
        }
    }

    public function testPostStateIsDisplayedForPfcptPage(): void
    {
        $page = get_post($this->homeForBookId);

        $postStates = $this->admin->displayPostStates([], $page);

        $this->assertArrayHasKey('page_for_' . self::BOOK_POST_TYPE, $postStates);
    }

    public function testPostStateIncludesPostTypeName(): void
    {
        $page = get_post($this->homeForBookId);

        $postStates = $this->admin->displayPostStates([], $page);

        $this->assertStringContainsString('Books', $postStates['page_for_' . self::BOOK_POST_TYPE]);
    }

    public function testPostStateNotAddedForRegularPage(): void
    {
        $page = get_post($this->staticFrontPageId);

        $postStates = $this->admin->displayPostStates([], $page);

        $this->assertEmpty($postStates);
    }

    public function testPostStateNotAddedForNonPagePostType(): void
    {
        $book = get_post($this->bookIds[0]);

        $postStates = $this->admin->displayPostStates([], $book);

        $this->assertEmpty($postStates);
    }

    public function testExistingPostStatesArePreserved(): void
    {
        $page = get_post($this->homeForBookId);

        $existingStates = ['existing_state' => 'Existing State'];
        $postStates = $this->admin->displayPostStates($existingStates, $page);

        $this->assertArrayHasKey('existing_state', $postStates);
        $this->assertArrayHasKey('page_for_' . self::BOOK_POST_TYPE, $postStates);
    }

    public function testDifferentPostTypeHasDifferentPostState(): void
    {
        $bikePage = get_post($this->homeForBikeId);

        $postStates = $this->admin->displayPostStates([], $bikePage);

        $this->assertArrayHasKey('page_for_' . self::BIKE_POST_TYPE, $postStates);
        $this->assertArrayNotHasKey('page_for_' . self::BOOK_POST_TYPE, $postStates);
    }

    public function testOptionNameFormat(): void
    {
        $api = Plugin::getInstance()->getApi();

        $this->assertEquals('page_for_' . self::BOOK_POST_TYPE, $api->getOptionName(self::BOOK_POST_TYPE));
        $this->assertEquals('page_for_' . self::BIKE_POST_TYPE, $api->getOptionName(self::BIKE_POST_TYPE));
    }

    public function testPageIdsOptionContainsCorrectMapping(): void
    {
        $pageIds = get_option(Api::OPTION_PAGE_IDS);

        $this->assertIsArray($pageIds);
        $this->assertEquals($this->homeForBookId, $pageIds[self::BOOK_POST_TYPE]);
        $this->assertEquals($this->homeForBikeId, $pageIds[self::BIKE_POST_TYPE]);
    }

    public function testSlugWarningEnqueuedOnAssignedPageWithUseSlug(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        $GLOBALS['post'] = get_post($this->homeForBookId);
        wp_deregister_script('pfcpt-slug-warning');

        $this->admin->enqueueBlockEditorAssets();

        $this->assertTrue(wp_script_is('pfcpt-slug-warning', 'enqueued'));
    }

    public function testSlugWarningNotEnqueuedWhenUseSlugDisabled(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '0');
        $GLOBALS['post'] = get_post($this->homeForBookId);
        wp_deregister_script('pfcpt-slug-warning');

        $this->admin->enqueueBlockEditorAssets();

        $this->assertFalse(wp_script_is('pfcpt-slug-warning', 'enqueued'));
    }

    public function testSlugWarningNotEnqueuedOnUnrelatedPage(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        $GLOBALS['post'] = get_post($this->staticFrontPageId);
        wp_deregister_script('pfcpt-slug-warning');

        $this->admin->enqueueBlockEditorAssets();

        $this->assertFalse(wp_script_is('pfcpt-slug-warning', 'enqueued'));
    }

    public function testSlugWarningNotEnqueuedOnEmptyOriginalSlug(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        $page = clone get_post($this->homeForBookId);
        $page->post_name = '';
        $GLOBALS['post'] = $page;
        wp_deregister_script('pfcpt-slug-warning');

        $this->admin->enqueueBlockEditorAssets();

        $this->assertFalse(wp_script_is('pfcpt-slug-warning', 'enqueued'));
    }

    public function testSlugWarningNotEnqueuedOnNonPagePostType(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        $GLOBALS['post'] = get_post($this->bookIds[0]);
        wp_deregister_script('pfcpt-slug-warning');

        $this->admin->enqueueBlockEditorAssets();

        $this->assertFalse(wp_script_is('pfcpt-slug-warning', 'enqueued'));
    }

    public function testSlugWarningLocalizesEditorData(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        $GLOBALS['post'] = get_post($this->homeForBookId);
        wp_deregister_script('pfcpt-slug-warning');

        $this->admin->enqueueBlockEditorAssets();

        $before = wp_scripts()->get_data('pfcpt-slug-warning', 'before');
        $inline = \is_array($before) ? implode("\n", $before) : '';

        $this->assertStringContainsString('postTypeLabel', $inline);
        $this->assertStringContainsString(self::BOOK_POST_TYPE, $inline);
    }

    public function testQuickEditWarningEnqueuedOnPageListWithProtectedPage(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        set_current_screen('edit-page');
        wp_deregister_script('pfcpt-quick-edit-warning');

        $this->admin->enqueueQuickEditAssets('edit.php');

        $this->assertTrue(wp_script_is('pfcpt-quick-edit-warning', 'enqueued'));
    }

    public function testQuickEditWarningNotEnqueuedWhenNoUseSlugEnabled(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '0');
        update_option('page_for_' . self::BIKE_POST_TYPE . '_use_slug', '0');
        set_current_screen('edit-page');
        wp_deregister_script('pfcpt-quick-edit-warning');

        $this->admin->enqueueQuickEditAssets('edit.php');

        $this->assertFalse(wp_script_is('pfcpt-quick-edit-warning', 'enqueued'));
    }

    public function testQuickEditWarningNotEnqueuedOnNonPageList(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        set_current_screen('edit-post');
        wp_deregister_script('pfcpt-quick-edit-warning');

        $this->admin->enqueueQuickEditAssets('edit.php');

        $this->assertFalse(wp_script_is('pfcpt-quick-edit-warning', 'enqueued'));
    }

    public function testQuickEditWarningNotEnqueuedOnOtherHooks(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        set_current_screen('edit-page');
        wp_deregister_script('pfcpt-quick-edit-warning');

        $this->admin->enqueueQuickEditAssets('post.php');

        $this->assertFalse(wp_script_is('pfcpt-quick-edit-warning', 'enqueued'));
    }

    public function testQuickEditWarningLocalizesProtectedPages(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');
        set_current_screen('edit-page');
        wp_deregister_script('pfcpt-quick-edit-warning');

        $this->admin->enqueueQuickEditAssets('edit.php');

        $before = wp_scripts()->get_data('pfcpt-quick-edit-warning', 'before');
        $inline = \is_array($before) ? implode("\n", $before) : '';

        $this->assertStringContainsString((string) $this->homeForBookId, $inline);
        $this->assertStringContainsString('Books', $inline);
    }
}
