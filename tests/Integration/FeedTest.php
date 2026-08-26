<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for feeds on a page for custom post type.
 *
 * The plugin turns the native archive off because the page is the archive.
 * WP_Post_Type::set_props() derives rewrite['feeds'] from has_archive, so
 * that alone drops the feed rules of every single of the post type, and the
 * archive feed has no rule of its own either. Both are put back.
 */
class FeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    /**
     * @param array<string, mixed> $args
     */
    private function reRegisterPostType(string $postType, array $args = []): void
    {
        $postTypeObject = get_post_type_object($postType);

        if ($postTypeObject !== null) {
            $args = array_merge(get_object_vars($postTypeObject), $args);
            unregister_post_type($postType);
        }

        register_post_type($postType, $args);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function enableUseSlug(array $args = []): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $this->reRegisterPostType(self::BOOK_POST_TYPE, $args);
        flush_rewrite_rules();
    }

    private function assertMatchedRule(string $expected): void
    {
        global $wp;

        $this->assertSame($expected, $wp->matched_rule);
    }

    private function assertIsBookArchiveFeed(): void
    {
        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue($wp_query->is_feed);
        $this->assertTrue($wp_query->is_home);
        $this->assertFalse(
            $wp_query->is_comment_feed,
            'The archive feed lists the posts, not the comments of the page'
        );
        $this->assertSame($this->homeForBookId, $wp_query->queried_object_id);
        $this->assertSame(self::BOOK_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});
        $this->assertNotEmpty($wp_query->posts);
        $this->assertSame(
            [self::BOOK_POST_TYPE],
            array_values(array_unique(array_column($wp_query->posts, 'post_type')))
        );
    }

    // -----------------------------------------------------------------
    // The feed of a single
    // -----------------------------------------------------------------

    public function testSingleFeedResolves(): void
    {
        $this->enableUseSlug();

        $this->get(get_permalink($this->bookIds[0]) . 'feed/');

        global $wp_query;

        $this->assertMatchedRule('home-for-books/(?!page)([^/]+)/(feed|rdf|rss|rss2|atom)/?$');
        $this->assertFalse($wp_query->is_404);
        $this->assertTrue($wp_query->is_feed);
        $this->assertTrue($wp_query->is_comment_feed);
        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    public function testSingleFeedWithFormatResolves(): void
    {
        $this->enableUseSlug();

        $this->get(get_permalink($this->bookIds[0]) . 'feed/rss2/');

        $this->assertMatchedRule('home-for-books/(?!page)([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$');
        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
        $this->assertSame('rss2', get_query_var('feed'));
    }

    public function testSingleFeedResolvesWithoutPageSlug(): void
    {
        // The feed rules are lost as soon as a page is assigned, whether or
        // not the post type is rebased on the page slug.
        $this->reRegisterPostType(self::BOOK_POST_TYPE);
        flush_rewrite_rules();

        $this->get(get_permalink($this->bookIds[0]) . 'feed/');

        $this->assertMatchedRule('books/([^/]+)/(feed|rdf|rss|rss2|atom)/?$');
        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    // -----------------------------------------------------------------
    // The feed of the archive
    // -----------------------------------------------------------------

    public function testArchiveFeedListsThePostTypePosts(): void
    {
        $this->enableUseSlug();

        $this->get($this->getBookHomeUrl() . 'feed/');

        // Matched as a single named "feed", the child page fallback resolves
        // it back to the page.
        $this->assertMatchedRule('home-for-books/(?!page)([^/]+)(?:/([0-9]+))?/?$');
        $this->assertIsBookArchiveFeed();
    }

    public function testArchiveFeedWithFormatResolves(): void
    {
        $this->enableUseSlug();

        $this->get($this->getBookHomeUrl() . 'feed/rss2/');

        $this->assertMatchedRule('home-for-books/(?!page)([^/]+)/(feed|rdf|rss|rss2|atom)/?$');
        $this->assertSame('rss2', get_query_var('feed'));
        $this->assertIsBookArchiveFeed();
    }

    public function testArchiveShorthandFeedResolves(): void
    {
        $this->enableUseSlug();

        $this->get($this->getBookHomeUrl() . 'rss2/');

        $this->assertMatchedRule('home-for-books/(?!page)([^/]+)(?:/([0-9]+))?/?$');
        $this->assertIsBookArchiveFeed();
    }

    public function testArchiveFeedResolvesWithoutPageSlug(): void
    {
        // No rule of our own here: core's generic page feed rule resolves it,
        // and the posts page correction turns it into the post type feed.
        $this->get($this->getBookHomeUrl() . 'feed/');

        $this->assertMatchedRule('(.?.+?)/(feed|rdf|rss|rss2|atom)/?$');
        $this->assertIsBookArchiveFeed();
    }

    public function testArchiveCommentFeedIsStillAvailable(): void
    {
        $this->enableUseSlug();

        $this->get($this->getBookHomeUrl() . 'feed/?withcomments=1');

        global $wp_query;

        $this->assertTrue($wp_query->is_feed);
        $this->assertTrue(
            $wp_query->is_comment_feed,
            'withcomments must still opt into the comment feed of the page'
        );
    }

    // -----------------------------------------------------------------
    // Post types that asked for no feeds
    // -----------------------------------------------------------------

    /**
     * @return array{0: int, 1: int} Page ID, post ID
     */
    private function registerCarPostTypeWithoutFeeds(): array
    {
        $pageId = (int) static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'home-for-cars',
            'post_status' => 'publish',
        ]);

        update_option('page_for_car', $pageId);
        update_option(Api::OPTION_PAGE_IDS, [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
            'car' => $pageId,
        ]);
        update_option('page_for_car_use_slug', true);

        register_post_type('car', [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'cars',
                'feeds' => false,
            ],
        ]);

        $carId = (int) static::factory()->post->create([
            'post_type' => 'car',
            'post_name' => 'a-car',
        ]);

        flush_rewrite_rules();

        return [$pageId, $carId];
    }

    public function testFeedRulesStayOffWhenThePostTypeDisabledThem(): void
    {
        [, $carId] = $this->registerCarPostTypeWithoutFeeds();

        global $wp_rewrite;

        $this->assertFalse($wp_rewrite->extra_permastructs['car']['feed']);

        $this->get(home_url('/home-for-cars/a-car/feed/'));

        global $wp_query;

        $this->assertTrue($wp_query->is_404);

        // The post type itself is untouched.
        $this->get(home_url('/home-for-cars/a-car/'));

        $this->assertSame($carId, get_queried_object_id());

        unregister_post_type('car');
    }
}
