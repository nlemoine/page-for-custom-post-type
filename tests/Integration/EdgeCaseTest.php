<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class EdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testPfcptPageWithStaticFrontPage(): void
    {
        $this->configureStaticFrontPage(true);

        $this->get($this->getBookHomeUrl());

        $this->assertTrue(\is_home());
        $this->assertFalse(\is_front_page());
        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
    }

    public function testFrontPageIsNotPfcptPage(): void
    {
        $this->configureStaticFrontPage(true);

        $this->get(\home_url('/'));

        $this->assertFalse(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
    }

    public function testPfcptWithShowOnFrontPosts(): void
    {
        \update_option('show_on_front', 'posts');
        \delete_option('page_on_front');
        \delete_option('page_for_posts');

        $this->get($this->getBookHomeUrl());

        $this->assertTrue(\is_home());
        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
    }

    public function testNestedPageSlug(): void
    {
        $parentPageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_title'  => 'Parent Page',
            'post_name'   => 'parent-page',
            'post_status' => 'publish',
        ]);

        $childPageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_title'  => 'Nested Book Home',
            'post_name'   => 'nested-book-home',
            'post_status' => 'publish',
            'post_parent' => $parentPageId,
        ]);

        \update_option('page_for_' . self::BOOK_POST_TYPE, $childPageId);

        \flush_rewrite_rules();

        $url = \get_permalink($childPageId);
        $this->assertStringContainsString('parent-page', $url);

        $this->get($url);

        global $wp_query;

        $this->assertTrue($wp_query->is_home);
        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
    }

    public function testPfcptPageContentIsNotInQuery(): void
    {
        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $postIds = \wp_list_pluck($wp_query->posts, 'ID');

        $this->assertNotContains($this->homeForBookId, $postIds);
    }

    public function testMultiplePfcptPagesWorkIndependently(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(\is_home());
        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type(self::BOOK_POST_TYPE));
        $this->assertFalse(\n5s\PageForCustomPostType\is_page_for_custom_post_type(self::BIKE_POST_TYPE));

        $this->get($this->getBikeHomeUrl());

        $this->assertTrue(\is_home());
        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type(self::BIKE_POST_TYPE));
        $this->assertFalse(\n5s\PageForCustomPostType\is_page_for_custom_post_type(self::BOOK_POST_TYPE));
    }

    public function testPfcptPageWithNoPostsPerPage(): void
    {
        \update_option('posts_per_page', 999);

        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $this->assertCount(\count($this->bookIds), $wp_query->posts);
        $this->assertSame(1, $wp_query->max_num_pages);
    }

    public function testRemovingPageAssignmentRestoresNormalBehavior(): void
    {
        $bookHomeUrl = $this->getBookHomeUrl();

        \delete_option('page_for_' . self::BOOK_POST_TYPE);

        \flush_rewrite_rules();

        $this->get($bookHomeUrl);

        global $wp_query;

        $this->assertTrue($wp_query->is_page);
        $this->assertFalse($wp_query->is_home);
        $this->assertFalse(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
    }

    public function testQueriedObjectIsPageNotPosts(): void
    {
        $this->get($this->getBookHomeUrl());

        $queriedObject = \get_queried_object();

        $this->assertInstanceOf(\WP_Post::class, $queriedObject);
        $this->assertSame('page', $queriedObject->post_type);
        $this->assertEquals($this->homeForBookId, $queriedObject->ID);
    }
}
