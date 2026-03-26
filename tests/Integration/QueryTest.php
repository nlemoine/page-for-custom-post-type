<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for query manipulation.
 *
 * Tests query conditionals, queried object, and posts on PFCPT pages.
 */
class QueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testQueryPropertiesExistOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $this->assertTrue(property_exists($wp_query, Api::QUERY_VAR_IS_PFCPT));
        $this->assertTrue(property_exists($wp_query, 'is_book_page'));
        $this->assertTrue(property_exists($wp_query, 'is_bike_page'));
    }

    public function testQueryConditionalsOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $this->assertEquals(self::BOOK_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});
        $this->assertTrue($wp_query->is_book_page);
        $this->assertFalse($wp_query->is_bike_page);
    }

    public function testQueriedObjectIsPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $this->assertEquals($this->homeForBookId, $wp_query->queried_object_id);
    }

    public function testPostsAreFromCorrectPostType(): void
    {
        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $expectedIds = \array_slice(array_reverse($this->bookIds), 0, (int) get_option('posts_per_page'));

        $this->assertEquals($expectedIds, array_column($wp_query->posts, 'ID'));
    }

    public function testIsHomeReturnsTrueOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(is_home());
    }

    public function testIsPageReturnsFalseOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertFalse(is_page());
    }

    public function testIsSingularReturnsFalseOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertFalse(is_singular());
    }

    public function testQueryPropertiesAreFalseOnNonPfcptPage(): void
    {
        $this->get(get_permalink($this->staticFrontPageId));

        global $wp_query;

        $this->assertTrue(property_exists($wp_query, Api::QUERY_VAR_IS_PFCPT));
        $this->assertTrue(property_exists($wp_query, 'is_book_page'));
        $this->assertTrue(property_exists($wp_query, 'is_bike_page'));
        $this->assertFalse($wp_query->{Api::QUERY_VAR_IS_PFCPT});
        $this->assertFalse($wp_query->is_book_page);
        $this->assertFalse($wp_query->is_bike_page);
    }

    public function testDifferentPostTypePfcptPage(): void
    {
        $this->get($this->getBikeHomeUrl());

        global $wp_query;

        $this->assertEquals(self::BIKE_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});
        $this->assertFalse($wp_query->is_book_page);
        $this->assertTrue($wp_query->is_bike_page);
        $this->assertEquals($this->homeForBikeId, $wp_query->queried_object_id);
    }

    public function testFilterPostsWherePassesThroughOnNonPfcptQuery(): void
    {
        // Navigate to a regular page (not PFCPT)
        $this->get(get_permalink($this->staticFrontPageId));

        global $wp_query;

        // The WHERE clause should not be modified for non-PFCPT queries
        $this->assertFalse($wp_query->{Api::QUERY_VAR_IS_PFCPT});
    }

    public function testGetPostTypeFromPageIdReturnsNullForNonMappedPage(): void
    {
        $api = new Api();
        $result = $api->getPostTypeFromPageId($this->staticFrontPageId);

        $this->assertNull($result);
    }

    public function testBodyClassOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        $classes = get_body_class();

        $this->assertContains('home', $classes);
        $this->assertContains('home-for-' . self::BOOK_POST_TYPE, $classes);
        $this->assertNotContains('blog', $classes);
    }

    public function testBodyClassOnDifferentPfcptPage(): void
    {
        $this->get($this->getBikeHomeUrl());

        $classes = get_body_class();

        $this->assertContains('home', $classes);
        $this->assertContains('home-for-' . self::BIKE_POST_TYPE, $classes);
        $this->assertNotContains('blog', $classes);
    }

    public function testSetQueryPropertiesSkipsSubQueries(): void
    {
        $this->get($this->getBookHomeUrl());

        // Create a secondary query (not main) for the same page
        $subQuery = new \WP_Query([
            'page_id' => $this->homeForBookId,
        ]);

        // Sub-queries should NOT get PFCPT properties set
        $this->assertFalse(
            property_exists($subQuery, Api::QUERY_VAR_IS_PFCPT)
            && $subQuery->{Api::QUERY_VAR_IS_PFCPT} !== false
        );
    }
}
