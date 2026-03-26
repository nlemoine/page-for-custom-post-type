<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;
use WP_Post_Type;

/**
 * Integration tests for PostType behavior within WordPress flow.
 *
 * Tests post type registration modifications, permalinks, and rewrite rules
 * as they work through WordPress hooks and filters.
 */
class PostTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testPostTypeHasArchiveIsDisabledWhenPageAssigned(): void
    {
        // Re-register to trigger filter with options now set
        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        $postTypeObject = \get_post_type_object(self::BOOK_POST_TYPE);

        $this->assertInstanceOf(WP_Post_Type::class, $postTypeObject);
        $this->assertFalse($postTypeObject->has_archive);
    }

    public function testPostTypeWithoutPageKeepsHasArchive(): void
    {
        // Register a new post type without a page assigned
        \register_post_type('movie', [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'movies',
            ],
        ]);

        $postTypeObject = \get_post_type_object('movie');

        $this->assertInstanceOf(WP_Post_Type::class, $postTypeObject);
        $this->assertTrue($postTypeObject->has_archive);

        \unregister_post_type('movie');
    }

    public function testSinglePostPermalinkUsesPageSlugWhenEnabled(): void
    {
        // Enable use page slug option
        \update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        // Re-register the post type to pick up the change
        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        \flush_rewrite_rules();

        $bookId = $this->bookIds[0];
        $permalink = \get_permalink($bookId);

        $this->assertStringContainsString('/home-for-books/', $permalink);
    }

    public function testSinglePostPermalinkUsesOriginalSlugWhenDisabled(): void
    {
        // Disable use page slug option
        \update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', false);

        // Re-register the post type to pick up the change
        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        \flush_rewrite_rules();

        $bookId = $this->bookIds[0];
        $permalink = \get_permalink($bookId);

        // Original slug is "books" (from bootstrap.php)
        $this->assertStringContainsString('/books/', $permalink);
        $this->assertStringNotContainsString('/home-for-books/', $permalink);
    }

    public function testSinglePostIsAccessibleWithPageSlugPermalink(): void
    {
        // Enable use page slug option
        \update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        // Re-register the post type
        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        \flush_rewrite_rules();

        $bookId = $this->bookIds[0];
        $permalink = \get_permalink($bookId);

        $this->get($permalink);

        $this->assertTrue(\is_singular(self::BOOK_POST_TYPE));
        $this->assertEquals($bookId, \get_queried_object_id());
    }

    public function testPfcptPageIsAccessibleAsArchive(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(\is_home());
        $this->assertEquals($this->homeForBookId, \get_queried_object_id());
    }

    public function testDifferentPostTypesCanHaveDifferentSlugSettings(): void
    {
        // Enable for books, disable for bikes
        \update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);
        \update_option('page_for_' . self::BIKE_POST_TYPE . '_use_slug', false);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);
        $this->reRegisterPostType(self::BIKE_POST_TYPE);

        \flush_rewrite_rules();

        $bookPermalink = \get_permalink($this->bookIds[0]);
        $bikePermalink = \get_permalink($this->bikeIds[0]);

        // Books should use page slug, bikes should use original slug "bikes"
        $this->assertStringContainsString('/home-for-books/', $bookPermalink);
        $this->assertStringContainsString('/bikes/', $bikePermalink);
        $this->assertStringNotContainsString('/home-for-bikes/', $bikePermalink);
    }

    /**
     * Re-register a post type to pick up option changes.
     *
     * This is needed because the plugin's filter runs at registration time,
     * and in tests, options are set after initial registration.
     */
    private function reRegisterPostType(string $postType): void
    {
        $postTypeObject = \get_post_type_object($postType);

        if (!$postTypeObject instanceof WP_Post_Type) {
            return;
        }

        // Get original registration args
        $args = \get_object_vars($postTypeObject);

        // Unregister and re-register to trigger the filter
        \unregister_post_type($postType);
        \register_post_type($postType, $args);
    }
}
