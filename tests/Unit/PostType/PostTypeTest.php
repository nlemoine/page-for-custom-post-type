<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Unit\PostType;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\PostType\PostType;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the PostType class.
 *
 * Tests the updatePostTypeArgs method behavior.
 */
class PostTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testUpdatePostTypeArgsUsesPageSlugWhenEnabled(): void
    {
        // Enable option
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $postType = new PostType($api, $rewriteManager);

        $args = [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'books'],
        ];

        $modifiedArgs = $postType->updatePostTypeArgs($args, self::BOOK_POST_TYPE);

        // Should use page slug
        $this->assertEquals('home-for-books', $modifiedArgs['rewrite']['slug']);
    }

    public function testUpdatePostTypeArgsKeepsOriginalSlugWhenDisabled(): void
    {
        // Disable option
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', false);

        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $postType = new PostType($api, $rewriteManager);

        $args = [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'books'],
        ];

        $modifiedArgs = $postType->updatePostTypeArgs($args, self::BOOK_POST_TYPE);

        // Should keep original slug
        $this->assertEquals('books', $modifiedArgs['rewrite']['slug']);
    }

    public function testUpdatePostTypeArgsKeepsOriginalSlugByDefault(): void
    {
        // Delete option to test default behavior
        delete_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug');

        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $postType = new PostType($api, $rewriteManager);

        $args = [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'books'],
        ];

        $modifiedArgs = $postType->updatePostTypeArgs($args, self::BOOK_POST_TYPE);

        // Should keep original slug by default (option not set = false)
        $this->assertEquals('books', $modifiedArgs['rewrite']['slug']);
    }

    public function testUpdatePostTypeArgsAlwaysDisablesHasArchive(): void
    {
        // Enable option
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $postType = new PostType($api, $rewriteManager);

        $args = [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'books'],
        ];

        $modifiedArgs = $postType->updatePostTypeArgs($args, self::BOOK_POST_TYPE);

        // has_archive should always be false when a page is assigned
        $this->assertFalse($modifiedArgs['has_archive']);
    }

    public function testUpdatePostTypeArgsDisablesHasArchiveEvenWhenSlugDisabled(): void
    {
        // Disable option
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', false);

        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $postType = new PostType($api, $rewriteManager);

        $args = [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'books'],
        ];

        $modifiedArgs = $postType->updatePostTypeArgs($args, self::BOOK_POST_TYPE);

        // has_archive should still be false (page is the archive)
        $this->assertFalse($modifiedArgs['has_archive']);
    }

    #[DataProvider('skippedPostTypeArgsProvider')]
    public function testUpdatePostTypeArgsSkipsIneligiblePostTypes(array $args, string $postType): void
    {
        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $pt = new PostType($api, $rewriteManager);

        $modifiedArgs = $pt->updatePostTypeArgs($args, $postType);

        $this->assertTrue($modifiedArgs['has_archive']);
    }

    public static function skippedPostTypeArgsProvider(): iterable
    {
        yield 'builtin' => [
            ['_builtin' => true, 'public' => true, 'has_archive' => true],
            'post',
        ];
        yield 'non-public' => [
            ['public' => false, 'has_archive' => true],
            'private_type',
        ];
        yield 'non-publicly-queryable' => [
            ['publicly_queryable' => false, 'has_archive' => true],
            'internal_type',
        ];
    }

    public function testDifferentPostTypesCanHaveDifferentSettings(): void
    {
        // Enable for books, disable for bikes
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);
        update_option('page_for_' . self::BIKE_POST_TYPE . '_use_slug', false);

        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $postType = new PostType($api, $rewriteManager);

        $bookArgs = [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'books'],
        ];

        $bikeArgs = [
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'bikes'],
        ];

        $modifiedBookArgs = $postType->updatePostTypeArgs($bookArgs, self::BOOK_POST_TYPE);
        $modifiedBikeArgs = $postType->updatePostTypeArgs($bikeArgs, self::BIKE_POST_TYPE);

        // Book should use page slug
        $this->assertEquals('home-for-books', $modifiedBookArgs['rewrite']['slug']);

        // Bike should keep original slug
        $this->assertEquals('bikes', $modifiedBikeArgs['rewrite']['slug']);
    }
}
