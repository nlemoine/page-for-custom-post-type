<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for the archive pagination rule.
 *
 * The plugin disables has_archive and serves the archive from the page, so
 * core generates no pagination rule for that base. When the post type is
 * rebased on the page slug, /{page}/page/2/ collides with the single rule.
 * The plugin registers an explicit rule on top to resolve it to the page.
 *
 * These tests exercise the resulting URLs: archive pagination, singles,
 * single pagination and attachments, across permastructs, hierarchies,
 * with_front and index permalinks.
 */
class ArchivePaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

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
     * Enable "use page slug" on the book post type and rebuild the rules.
     *
     * @param array<string, mixed> $args Extra register_post_type() args
     */
    private function enableUseSlug(array $args = []): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $this->reRegisterPostType(self::BOOK_POST_TYPE, $args);
        flush_rewrite_rules();
    }

    private function createAttachment(int $parentId, string $name = 'an-image'): int
    {
        return (int) static::factory()->post->create([
            'post_type' => 'attachment',
            'post_parent' => $parentId,
            'post_name' => $name,
            'post_status' => 'inherit',
            'post_mime_type' => 'image/jpeg',
        ]);
    }

    private function assertMatchedRule(string $expected): void
    {
        global $wp;

        $this->assertSame($expected, $wp->matched_rule);
    }

    private function assertIsBookArchivePageTwo(): void
    {
        global $wp_query;

        $this->assertFalse($wp_query->is_404, 'Expected the archive, got a 404');
        $this->assertTrue($wp_query->is_home);
        $this->assertTrue(is_paged());
        $this->assertSame($this->homeForBookId, $wp_query->queried_object_id);
        $this->assertSame(self::BOOK_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});
        $this->assertNotEmpty($wp_query->posts);
        $this->assertSame(
            [self::BOOK_POST_TYPE],
            array_values(array_unique(array_column($wp_query->posts, 'post_type')))
        );
    }

    // -----------------------------------------------------------------
    // Standard permastruct, use page slug enabled
    // -----------------------------------------------------------------

    public function testArchivePaginationWithPageSlug(): void
    {
        $this->enableUseSlug();

        $this->get(home_url('/home-for-books/page/2/'));

        // The post type rules step aside, core's page rule resolves it.
        $this->assertMatchedRule('(.?.+?)/page/?([0-9]{1,})/?$');
        $this->assertIsBookArchivePageTwo();
    }

    public function testSingleWithPageSlug(): void
    {
        $this->enableUseSlug();

        $this->get(get_permalink($this->bookIds[0]));

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    public function testSingleNextPagePaginationWithPageSlug(): void
    {
        $this->enableUseSlug();

        wp_update_post([
            'ID' => $this->bookIds[0],
            'post_content' => 'first<!--nextpage-->second',
        ]);

        $this->get(get_permalink($this->bookIds[0]) . '2/');

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
        $this->assertSame(2, (int) get_query_var('page'));
    }

    public function testAttachmentUnderSingleWithPageSlug(): void
    {
        $this->enableUseSlug();

        $attachmentId = $this->createAttachment($this->bookIds[0]);

        $permalink = get_attachment_link($attachmentId);
        $this->assertStringContainsString('/home-for-books/', $permalink);

        $this->get($permalink);

        $this->assertMatchedRule('home-for-books/(?!page)[^/]+/([^/]+)/?$');
        $this->assertTrue(is_attachment());
        $this->assertSame($attachmentId, get_queried_object_id());
    }

    public function testAttachmentUnderSingleWithExplicitSegmentWithPageSlug(): void
    {
        $this->enableUseSlug();

        $attachmentId = $this->createAttachment($this->bookIds[0]);

        $this->get(home_url(\sprintf(
            '/home-for-books/%s/attachment/an-image/',
            get_post_field('post_name', $this->bookIds[0])
        )));

        $this->assertMatchedRule('home-for-books/(?!page)[^/]+/attachment/([^/]+)/?$');
        $this->assertTrue(is_attachment());
        $this->assertSame($attachmentId, get_queried_object_id());
    }

    public function testAttachmentUnderHierarchicalSingleWithPageSlug(): void
    {
        $this->enableUseSlug(['hierarchical' => true]);

        $parentId = (int) static::factory()->post->create([
            'post_type' => self::BOOK_POST_TYPE,
            'post_name' => 'trilogy',
        ]);
        $attachmentId = $this->createAttachment($parentId, 'cover');

        $this->get(get_attachment_link($attachmentId));

        $this->assertMatchedRule('home-for-books/(?!page)(.+?)(?:/([0-9]+))?/?$');
        $this->assertTrue(is_attachment());
        $this->assertSame($attachmentId, get_queried_object_id());
    }

    public function testBookNamedLikeThePaginationBaseDoesNotStealTheArchive(): void
    {
        $this->enableUseSlug();

        static::factory()->post->create([
            'post_type' => self::BOOK_POST_TYPE,
            'post_name' => 'page',
        ]);

        $this->get(home_url('/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    // -----------------------------------------------------------------
    // Use page slug disabled: core's generic page rule does the work
    // -----------------------------------------------------------------

    public function testArchivePaginationWithoutPageSlug(): void
    {
        $this->get(home_url('/home-for-books/page/2/'));

        // No rule registered for that base: core's generic page rule resolves it.
        $this->assertMatchedRule('(.?.+?)/page/?([0-9]{1,})/?$');
        $this->assertIsBookArchivePageTwo();
    }

    public function testAttachmentUnderSingleWithoutPageSlug(): void
    {
        $attachmentId = $this->createAttachment($this->bookIds[0]);

        $permalink = get_attachment_link($attachmentId);
        $this->assertStringContainsString('/books/', $permalink);

        $this->get($permalink);

        // No option, no lookahead on the tag, so nothing to repair either.
        $this->assertMatchedRule('books/[^/]+/([^/]+)/?$');
        $this->assertTrue(is_attachment());
        $this->assertSame($attachmentId, get_queried_object_id());
    }

    // -----------------------------------------------------------------
    // Hierarchical post type
    // -----------------------------------------------------------------

    public function testArchivePaginationWithHierarchicalPostType(): void
    {
        $this->enableUseSlug(['hierarchical' => true]);

        $this->assertTrue(is_post_type_hierarchical(self::BOOK_POST_TYPE));

        $this->get(home_url('/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    public function testNestedSingleWithHierarchicalPostType(): void
    {
        $this->enableUseSlug(['hierarchical' => true]);

        $parentId = (int) static::factory()->post->create([
            'post_type' => self::BOOK_POST_TYPE,
            'post_name' => 'trilogy',
        ]);
        $childId = (int) static::factory()->post->create([
            'post_type' => self::BOOK_POST_TYPE,
            'post_name' => 'volume-one',
            'post_parent' => $parentId,
        ]);

        $permalink = get_permalink($childId);
        $this->assertSame(home_url('/home-for-books/trilogy/volume-one/'), $permalink);

        $this->get($permalink);

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($childId, get_queried_object_id());
    }

    // -----------------------------------------------------------------
    // Custom permastruct (extended-cpts style)
    // -----------------------------------------------------------------

    private function enableUseSlugWithPermastruct(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        // extended-cpts registers its own permastruct after the post type.
        add_permastruct(
            self::BOOK_POST_TYPE,
            'home-for-books/%' . self::GENRE_TAXONOMY . '%/%' . self::BOOK_POST_TYPE . '%',
            ['with_front' => false]
        );

        // Flush once, with the final permastruct in place: WP_Rewrite appends
        // permastruct rules to extra_rules_top on every generation without
        // ever resetting it, so a second flush would leave the rules of the
        // previous permastruct in front.
        flush_rewrite_rules();
    }

    public function testArchivePaginationWithCustomPermastruct(): void
    {
        $this->enableUseSlugWithPermastruct();

        $this->get(home_url('/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    public function testArchivePaginationWithACategoryPermastruct(): void
    {
        // The tag right after the page slug is greedy enough to match the
        // pagination base, and it is not the post type's own, so the lookahead
        // cannot reach it. The child page fallback resolves it instead.
        $this->enableUseSlug();

        add_permastruct(
            self::BOOK_POST_TYPE,
            'home-for-books/%category%/%' . self::BOOK_POST_TYPE . '%',
            ['with_front' => false]
        );
        flush_rewrite_rules();

        $this->get(home_url('/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    public function testSingleWithCustomPermastruct(): void
    {
        $this->enableUseSlugWithPermastruct();

        $this->get(home_url('/home-for-books/fantasy/' . get_post_field('post_name', $this->bookIds[0]) . '/'));

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    // -----------------------------------------------------------------
    // with_front
    // -----------------------------------------------------------------

    public function testArchivePaginationWithFrontDisabled(): void
    {
        $this->set_permalink_structure('/blog/%postname%/');
        $this->enableUseSlug(['rewrite' => ['slug' => 'books', 'with_front' => false]]);

        // The page is not behind the front, and neither are the singles.
        $this->assertSame(home_url('/home-for-books/'), get_permalink($this->homeForBookId));
        $this->assertStringStartsWith(home_url('/home-for-books/'), get_permalink($this->bookIds[0]));

        $this->get(home_url('/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    public function testArchivePaginationWithFrontEnabled(): void
    {
        $this->set_permalink_structure('/blog/%postname%/');
        $this->enableUseSlug(['rewrite' => ['slug' => 'books', 'with_front' => true]]);

        // Singles sit behind the front, the page does not: no collision, but
        // the archive must still paginate.
        $this->assertSame(home_url('/home-for-books/'), get_permalink($this->homeForBookId));
        $this->assertStringStartsWith(home_url('/blog/home-for-books/'), get_permalink($this->bookIds[0]));

        $this->get(home_url('/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    public function testSingleWithFrontEnabled(): void
    {
        $this->set_permalink_structure('/blog/%postname%/');
        $this->enableUseSlug(['rewrite' => ['slug' => 'books', 'with_front' => true]]);

        $this->get(get_permalink($this->bookIds[0]));

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    // -----------------------------------------------------------------
    // Index permalinks
    // -----------------------------------------------------------------

    public function testArchivePaginationWithIndexPermalinks(): void
    {
        $this->set_permalink_structure('/index.php/%postname%/');
        $this->enableUseSlug();

        $this->assertSame(home_url('/index.php/home-for-books/'), get_permalink($this->homeForBookId));

        $this->get(home_url('/index.php/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    public function testSingleWithIndexPermalinks(): void
    {
        $this->set_permalink_structure('/index.php/%postname%/');
        $this->enableUseSlug();

        $this->get(get_permalink($this->bookIds[0]));

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    // -----------------------------------------------------------------
    // Nested page and localized pagination base
    // -----------------------------------------------------------------

    public function testArchivePaginationWithNestedPage(): void
    {
        $parentId = (int) static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'library',
            'post_status' => 'publish',
        ]);
        wp_update_post([
            'ID' => $this->homeForBookId,
            'post_parent' => $parentId,
        ]);

        $this->enableUseSlug();

        $this->assertSame(home_url('/library/home-for-books/'), get_permalink($this->homeForBookId));
        $this->assertStringStartsWith(home_url('/library/home-for-books/'), get_permalink($this->bookIds[0]));

        $this->get(home_url('/library/home-for-books/page/2/'));

        $this->assertIsBookArchivePageTwo();
    }

    public function testArchivePaginationWithLocalizedPaginationBase(): void
    {
        global $wp_rewrite;

        $wp_rewrite->pagination_base = 'pagina';

        $this->enableUseSlug();

        $this->get(home_url('/home-for-books/pagina/2/'));

        $this->assertMatchedRule('(.?.+?)/pagina/?([0-9]{1,})/?$');
        $this->assertIsBookArchivePageTwo();
    }

    // -----------------------------------------------------------------
    // Other post types are not affected
    // -----------------------------------------------------------------

    public function testOtherPostTypeArchivePaginationIsUnaffected(): void
    {
        $this->enableUseSlug();

        $this->bikeIds = array_merge(
            $this->bikeIds,
            static::factory()->post->create_many(20, ['post_type' => self::BIKE_POST_TYPE])
        );

        $this->get($this->getBikeHomeUrl() . 'page/2/');

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_paged());
        $this->assertSame($this->homeForBikeId, $wp_query->queried_object_id);
        $this->assertSame(self::BIKE_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});
    }
}
