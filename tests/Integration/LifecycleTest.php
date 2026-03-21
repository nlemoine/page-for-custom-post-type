<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for lifecycle events.
 *
 * Tests option changes, page status transitions, and slug changes.
 */
class LifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testOptionUpdateUpdatesAggregatedOption(): void
    {
        $newPageId = self::factory()->post->create([
            'post_type' => 'page',
            'post_title' => 'New Book Page',
            'post_status' => 'publish',
        ]);

        update_option('page_for_' . self::BOOK_POST_TYPE, $newPageId);

        $pageIds = get_option(Api::OPTION_PAGE_IDS);

        $this->assertEquals($newPageId, $pageIds[self::BOOK_POST_TYPE]);
    }

    public function testOptionDeleteRemovesFromAggregatedOption(): void
    {
        delete_option('page_for_' . self::BOOK_POST_TYPE);

        $pageIds = get_option(Api::OPTION_PAGE_IDS);

        $this->assertArrayNotHasKey(self::BOOK_POST_TYPE, $pageIds);
    }

    public function testPageUnpublishClearsOption(): void
    {
        wp_update_post([
            'ID' => $this->homeForBookId,
            'post_status' => 'draft',
        ]);

        $optionValue = get_option('page_for_' . self::BOOK_POST_TYPE);

        $this->assertEmpty($optionValue);
    }

    public function testPageTrashClearsOption(): void
    {
        wp_trash_post($this->homeForBookId);

        $optionValue = get_option('page_for_' . self::BOOK_POST_TYPE);

        $this->assertEmpty($optionValue);
    }

    public function testPageDeleteClearsOption(): void
    {
        wp_delete_post($this->homeForBookId, true);

        $optionValue = get_option('page_for_' . self::BOOK_POST_TYPE);

        $this->assertEmpty($optionValue);
    }

    public function testNonPfcptPageStatusChangeHasNoEffect(): void
    {
        wp_update_post([
            'ID' => $this->staticFrontPageId,
            'post_status' => 'draft',
        ]);

        $optionValue = get_option('page_for_' . self::BOOK_POST_TYPE);

        $this->assertEquals($this->homeForBookId, $optionValue);
    }

    public function testPageSlugChangeFlushesRewriteRules(): void
    {
        $flushCalled = false;

        add_action('pfcpt/flush_rewrite_rules', static function () use (&$flushCalled) {
            $flushCalled = true;
        });

        wp_update_post([
            'ID' => $this->homeForBookId,
            'post_name' => 'new-book-page-slug',
        ]);

        $this->assertTrue($flushCalled);
    }

    public function testPageSlugUnchangedDoesNotFlushRewriteRules(): void
    {
        $flushCount = 0;

        add_action('pfcpt/flush_rewrite_rules', static function () use (&$flushCount) {
            $flushCount++;
        });

        // Get current slug
        $currentSlug = get_post($this->homeForBookId)->post_name;

        // Update with same slug
        wp_update_post([
            'ID' => $this->homeForBookId,
            'post_name' => $currentSlug,
        ]);

        $this->assertEquals(0, $flushCount);
    }

    public function testNonPagePostTypeStatusChangeHasNoEffect(): void
    {
        $bookId = $this->bookIds[0];

        wp_update_post([
            'ID' => $bookId,
            'post_status' => 'draft',
        ]);

        $optionValue = get_option('page_for_' . self::BOOK_POST_TYPE);

        $this->assertEquals($this->homeForBookId, $optionValue);
    }

    public function testPageRepublishAllowsReassignment(): void
    {
        // Unpublish page (clears option)
        wp_update_post([
            'ID' => $this->homeForBookId,
            'post_status' => 'draft',
        ]);

        // Republish page
        wp_update_post([
            'ID' => $this->homeForBookId,
            'post_status' => 'publish',
        ]);

        // Should be able to reassign
        update_option('page_for_' . self::BOOK_POST_TYPE, $this->homeForBookId);

        $optionValue = get_option('page_for_' . self::BOOK_POST_TYPE);

        $this->assertEquals($this->homeForBookId, $optionValue);
    }

    public function testUseSlugOptionDefaultsToFalse(): void
    {
        // Ensure option doesn't exist
        delete_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug');

        $api = new Api();

        $this->assertFalse($api->shouldUsePageSlug(self::BOOK_POST_TYPE));
    }

    public function testUseSlugOptionReturnsFalseWhenDisabled(): void
    {
        // WordPress stores unchecked checkbox as '0' or empty string
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '0');

        $api = new Api();

        $this->assertFalse($api->shouldUsePageSlug(self::BOOK_POST_TYPE));
    }

    public function testUseSlugOptionReturnsFalseWhenEmpty(): void
    {
        // WordPress stores unchecked checkbox as empty string when deleted
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '');

        $api = new Api();

        $this->assertFalse($api->shouldUsePageSlug(self::BOOK_POST_TYPE));
    }

    public function testUseSlugOptionReturnsTrueWhenEnabled(): void
    {
        // WordPress stores checked checkbox as '1'
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '1');

        $api = new Api();

        $this->assertTrue($api->shouldUsePageSlug(self::BOOK_POST_TYPE));
    }
}
