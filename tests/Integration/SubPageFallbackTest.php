<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Frontend\SubPageFallback;
use n5s\PageForCustomPostType\Plugin;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for the child page fallback.
 *
 * When a post type is rebased on its page slug, its rules shadow every URL
 * under the page. These tests cover the pages nested under the archive page
 * resolving anyway, the post type keeping priority when both exist, and the
 * fallback staying out of the way otherwise.
 */
class SubPageFallbackTest extends TestCase
{
    private int $subPageId;

    private int $deeperPageId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    /**
     * @param array<string, mixed> $args
     */
    private function enableUseSlug(array $args = []): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $postTypeObject = get_post_type_object(self::BOOK_POST_TYPE);

        if ($postTypeObject !== null) {
            $args = array_merge(get_object_vars($postTypeObject), $args);
            unregister_post_type(self::BOOK_POST_TYPE);
        }

        register_post_type(self::BOOK_POST_TYPE, $args);
        flush_rewrite_rules();
    }

    private function createSubPages(): void
    {
        $this->subPageId = (int) static::factory()->post->create([
            'post_type' => 'page',
            'post_title' => 'Sub Page',
            'post_name' => 'sub-page',
            'post_status' => 'publish',
            'post_parent' => $this->homeForBookId,
        ]);

        $this->deeperPageId = (int) static::factory()->post->create([
            'post_type' => 'page',
            'post_title' => 'Deeper',
            'post_name' => 'deeper',
            'post_status' => 'publish',
            'post_parent' => $this->subPageId,
        ]);
    }

    private function createAttachment(string $name = 'an-image'): int
    {
        return (int) static::factory()->post->create([
            'post_type' => 'attachment',
            'post_parent' => $this->bookIds[0],
            'post_name' => $name,
            'post_status' => 'inherit',
            'post_mime_type' => 'image/jpeg',
        ]);
    }

    private function assertMatchedRule(string $expected): void
    {
        global $wp;

        $this->assertSame(
            $expected,
            $wp->matched_rule,
            'The post type rule that shadows the page is what the fallback is there to undo'
        );
    }

    private function countQueries(callable $callback): int
    {
        global $wpdb;

        $before = $wpdb->num_queries;
        $callback();

        return $wpdb->num_queries - $before;
    }

    // -----------------------------------------------------------------
    // The pages the post type rules shadow
    // -----------------------------------------------------------------

    public function testChildPageResolves(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $url = get_permalink($this->subPageId);
        $this->assertSame(home_url('/home-for-books/sub-page/'), $url);

        $this->get($url)->assertOk();

        global $wp_query;

        $this->assertMatchedRule('home-for-books/([^/]+)(?:/([0-9]+))?/?$');
        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_page());
        $this->assertSame($this->subPageId, get_queried_object_id());
    }

    public function testGrandChildPageResolves(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(get_permalink($this->deeperPageId))->assertOk();

        global $wp_query;

        // Matched as attachment=deeper without the fallback.
        $this->assertMatchedRule('home-for-books/[^/]+/([^/]+)/?$');
        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_page());
        $this->assertSame($this->deeperPageId, get_queried_object_id());
    }

    public function testChildPageOfHierarchicalPostTypePageResolves(): void
    {
        $this->enableUseSlug(['hierarchical' => true]);
        $this->createSubPages();

        $this->get(get_permalink($this->deeperPageId))->assertOk();

        // The greedy tag of a hierarchical post type swallows the whole depth.
        $this->assertMatchedRule('home-for-books/(.+?)(?:/([0-9]+))?/?$');
        $this->assertTrue(is_page());
        $this->assertSame($this->deeperPageId, get_queried_object_id());
    }

    public function testChildPagePaginationResolves(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        wp_update_post([
            'ID' => $this->subPageId,
            'post_content' => 'first<!--nextpage-->second',
        ]);

        $this->get(get_permalink($this->subPageId) . '2/');

        global $wp_query;

        $this->assertMatchedRule('home-for-books/([^/]+)(?:/([0-9]+))?/?$');
        $this->assertFalse($wp_query->is_404);
        $this->assertSame($this->subPageId, get_queried_object_id());
        $this->assertSame(2, (int) get_query_var('page'));
    }

    public function testChildPageFeedResolves(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(get_permalink($this->subPageId) . 'feed/');

        global $wp_query;

        $this->assertMatchedRule('home-for-books/([^/]+)/(feed|rdf|rss|rss2|atom)/?$');
        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_feed());
        $this->assertSame($this->subPageId, get_queried_object_id());
    }

    public function testChildPageEmbedResolves(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(get_permalink($this->subPageId) . 'embed/');

        global $wp_query;

        $this->assertMatchedRule('home-for-books/([^/]+)/embed/?$');
        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_embed());
        $this->assertSame($this->subPageId, get_queried_object_id());
    }

    public function testChildPageCommentPageResolves(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(get_permalink($this->subPageId) . 'comment-page-2/');

        global $wp_query;

        $this->assertMatchedRule('home-for-books/([^/]+)/comment-page-([0-9]{1,})/?$');
        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_page());
        $this->assertSame($this->subPageId, get_queried_object_id());
        $this->assertSame(2, (int) get_query_var('cpage'));
    }

    // -----------------------------------------------------------------
    // The post type's own rule families are left alone
    //
    // WordPress generates a family of rules per post type: embeds, comment
    // pages, attachments, and the same again below each attachment. None of
    // them must go through the fallback while they resolve on their own.
    // -----------------------------------------------------------------

    public function testSingleEmbedIsUntouched(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(get_permalink($this->bookIds[0]) . 'embed/');

        $this->assertMatchedRule('home-for-books/([^/]+)/embed/?$');
        $this->assertTrue(is_embed());
        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    public function testSingleCommentPageIsUntouched(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(get_permalink($this->bookIds[0]) . 'comment-page-2/');

        $this->assertMatchedRule('home-for-books/([^/]+)/comment-page-([0-9]{1,})/?$');
        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
        $this->assertSame(2, (int) get_query_var('cpage'));
    }

    public function testAttachmentEmbedIsUntouched(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $attachmentId = $this->createAttachment();

        $this->get(get_attachment_link($attachmentId) . 'embed/');

        $this->assertMatchedRule('home-for-books/[^/]+/([^/]+)/embed/?$');
        $this->assertTrue(is_attachment());
        $this->assertTrue(is_embed());
        $this->assertSame($attachmentId, get_queried_object_id());
    }

    public function testAttachmentFeedIsUntouched(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $attachmentId = $this->createAttachment();

        $this->get(get_attachment_link($attachmentId) . 'feed/');

        $this->assertMatchedRule('home-for-books/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom)/?$');
        $this->assertTrue(is_attachment());
        $this->assertTrue(is_feed());
        $this->assertSame($attachmentId, get_queried_object_id());
    }

    public function testAttachmentCommentPageIsUntouched(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $attachmentId = $this->createAttachment();

        $this->get(get_attachment_link($attachmentId) . 'comment-page-2/');

        $this->assertMatchedRule('home-for-books/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$');
        $this->assertTrue(is_attachment());
        $this->assertSame($attachmentId, get_queried_object_id());
        $this->assertSame(2, (int) get_query_var('cpage'));
    }

    public function testUnknownSlugEmbedStill404s(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(home_url('/home-for-books/no-such-thing/embed/'));

        global $wp_query;

        $this->assertTrue($wp_query->is_404);
    }

    // -----------------------------------------------------------------
    // The post type keeps priority
    // -----------------------------------------------------------------

    public function testSingleStillResolves(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(get_permalink($this->bookIds[0]));

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    public function testPostWinsOverChildPageWithTheSameSlug(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $bookSlug = get_post_field('post_name', $this->bookIds[0]);
        static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => $bookSlug,
            'post_status' => 'publish',
            'post_parent' => $this->homeForBookId,
        ]);

        $this->get(home_url("/home-for-books/{$bookSlug}/"));

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    public function testAttachmentWinsOverChildPageWithTheSameSlug(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $attachmentId = (int) static::factory()->post->create([
            'post_type' => 'attachment',
            'post_parent' => $this->bookIds[0],
            'post_name' => 'deeper',
            'post_status' => 'inherit',
            'post_mime_type' => 'image/jpeg',
        ]);

        // Same last segment as the grandchild page, under a real book.
        $this->get(get_attachment_link($attachmentId));

        $this->assertTrue(is_attachment());
        $this->assertSame($attachmentId, get_queried_object_id());
    }

    public function testArchiveAndItsPaginationAreUntouched(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get($this->getBookHomeUrl());

        global $wp_query;

        $this->assertSame($this->homeForBookId, $wp_query->queried_object_id);
        $this->assertSame(self::BOOK_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});

        $this->get($this->getBookHomeUrl() . 'page/2/');

        $this->assertTrue(is_paged());
        $this->assertSame($this->homeForBookId, $wp_query->queried_object_id);
    }

    public function testUnknownSlugStill404s(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $this->get(home_url('/home-for-books/no-such-thing/'))->assertNotFound();
    }

    public function testDraftChildPageIsNotServed(): void
    {
        $this->enableUseSlug();

        static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'draft-child',
            'post_status' => 'draft',
            'post_parent' => $this->homeForBookId,
        ]);

        $this->get(home_url('/home-for-books/draft-child/'))->assertNotFound();
    }

    public function testPrivateChildPageIsServedToUsersWhoCanReadIt(): void
    {
        $this->enableUseSlug();

        $privateId = (int) static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'private-child',
            'post_status' => 'private',
            'post_parent' => $this->homeForBookId,
        ]);

        // is_post_status_viewable() reports private as not viewable;
        // WP::parse_request() still matches it and lets WP_Query decide.
        $this->acting_as('administrator');
        $this->get(home_url('/home-for-books/private-child/'));

        $this->assertTrue(is_page());
        $this->assertSame($privateId, get_queried_object_id());
    }

    public function testPrivateChildPageIsNotServedToAnonymousVisitors(): void
    {
        $this->enableUseSlug();

        static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'private-child',
            'post_status' => 'private',
            'post_parent' => $this->homeForBookId,
        ]);

        $this->get(home_url('/home-for-books/private-child/'))->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Scope
    // -----------------------------------------------------------------

    public function testDoesNotApplyToPostTypesWithoutPageSlug(): void
    {
        // Page assigned, "use page slug" off: the post type keeps its own
        // slug, this is plain WordPress behaviour and none of our business.
        $this->createSubPages();

        register_post_type('plain_cpt', [
            'public' => true,
            'publicly_queryable' => true,
            'rewrite' => ['slug' => 'home-for-books'],
        ]);
        flush_rewrite_rules();

        $this->get(home_url('/home-for-books/sub-page/'))->assertNotFound();

        unregister_post_type('plain_cpt');
    }

    public function testCanBeDisabledWithFilter(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $received = [];
        add_filter(
            'pfcpt/fallback_to_sub_page',
            static function (bool $enabled, string $postType, string $request) use (&$received): bool {
                $received = [$postType, $request];

                return false;
            },
            10,
            3
        );

        $this->get(get_permalink($this->subPageId))->assertNotFound();

        $this->assertSame([self::BOOK_POST_TYPE, 'home-for-books/sub-page'], $received);
    }

    // -----------------------------------------------------------------
    // Cost
    // -----------------------------------------------------------------

    public function testExistingSingleCostsNoExtraQuery(): void
    {
        $this->enableUseSlug();
        $this->createSubPages();

        $fallback = Plugin::getInstance()->getContainer()->get(SubPageFallback::class);

        // Warm whatever a first request fills in.
        $this->get(get_permalink($this->bookIds[0]));

        remove_filter('pre_handle_404', [$fallback, 'fallbackToSubPage'], 10);
        $without = $this->countQueries(function (): void {
            $this->get(get_permalink($this->bookIds[1]));
        });

        add_filter('pre_handle_404', [$fallback, 'fallbackToSubPage'], 10, 2);
        $with = $this->countQueries(function (): void {
            $this->get(get_permalink($this->bookIds[2]));
        });

        $this->assertSame(
            $without,
            $with,
            'A single post that exists must not pay for the fallback'
        );
    }
}
