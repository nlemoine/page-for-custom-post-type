<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for pagination on PFCPT pages.
 *
 * Tests that pagination works correctly on pages for custom post types.
 */
class PaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testPaginatedUrlWorks(): void
    {
        $this->get($this->getBookHomeUrl() . 'page/2/');

        global $wp_query;

        $this->assertEquals($this->homeForBookId, $wp_query->queried_object_id);
        $this->assertEquals(self::BOOK_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});
    }

    public function testPageTwoReturnsCorrectPosts(): void
    {
        $this->get($this->getBookHomeUrl() . 'page/2/');

        global $wp_query;

        $postsPerPage = (int) \get_option('posts_per_page');
        $expectedIds = \array_slice(
            \array_reverse($this->bookIds),
            $postsPerPage,
            $postsPerPage
        );

        $this->assertEquals($expectedIds, \array_column($wp_query->posts, 'ID'));
    }

    public function testPaginationConditionalsOnPageTwo(): void
    {
        $this->get($this->getBookHomeUrl() . 'page/2/');

        global $wp_query;

        $this->assertTrue($wp_query->is_book_page);
        $this->assertTrue(\is_home());
        $this->assertTrue(\is_paged());
    }

    public function testPageOneIsNotPaged(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertFalse(\is_paged());
    }

    public function testDifferentPostTypePagination(): void
    {
        // Create more bikes for pagination
        $this->bikeIds = \array_merge(
            $this->bikeIds,
            self::factory()->post->create_many(20, [
                'post_type' => self::BIKE_POST_TYPE,
            ])
        );

        $this->get($this->getBikeHomeUrl() . 'page/2/');

        global $wp_query;

        $this->assertEquals($this->homeForBikeId, $wp_query->queried_object_id);
        $this->assertTrue($wp_query->is_bike_page);
        $this->assertFalse($wp_query->is_book_page);
    }

    public function testMaxNumPagesIsCalculated(): void
    {
        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $postsPerPage = (int) \get_option('posts_per_page');
        $expectedMaxPages = (int) \ceil(\count($this->bookIds) / $postsPerPage);

        $this->assertEquals($expectedMaxPages, $wp_query->max_num_pages);
    }
}
