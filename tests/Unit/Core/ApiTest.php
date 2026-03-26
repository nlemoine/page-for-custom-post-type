<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Unit\Core;

use n5s\PageForCustomPostType\Core\Api;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WP_Post_Type;

/**
 * Unit tests for the Api class.
 *
 * These tests focus on pure logic methods that don't require WordPress.
 */
class ApiTest extends TestCase
{
    private Api $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new Api();
    }

    #[DataProvider('optionNameProvider')]
    public function testGetOptionNameReturnsPrefixedString(string $postType, string $expected): void
    {
        $this->assertEquals($expected, $this->api->getOptionName($postType));
    }

    public static function optionNameProvider(): iterable
    {
        yield 'simple' => ['book', 'page_for_book'];
        yield 'simple 2' => ['movie', 'page_for_movie'];
        yield 'with underscore' => ['custom_type', 'page_for_custom_type'];
    }

    public function testGetOptionNameWithWpPostTypeObject(): void
    {
        $postType = new WP_Post_Type('book', []);

        $this->assertEquals('page_for_book', $this->api->getOptionName($postType));
    }

    #[DataProvider('conditionalNameProvider')]
    public function testGetConditionalNameReturnsIsPrefixWithSuffix(string $postType, string $expected): void
    {
        $this->assertEquals($expected, $this->api->getConditionalName($postType));
    }

    public static function conditionalNameProvider(): iterable
    {
        yield 'simple' => ['book', 'is_book_page'];
        yield 'simple 2' => ['movie', 'is_movie_page'];
        yield 'with underscore' => ['custom_type', 'is_custom_type_page'];
    }

    public function testGetConditionalNameWithWpPostTypeObject(): void
    {
        $postType = new WP_Post_Type('book', []);

        $this->assertEquals('is_book_page', $this->api->getConditionalName($postType));
    }

    #[DataProvider('shouldConsiderPostTypeProvider')]
    public function testShouldConsiderPostType(bool $expected, bool $builtin, bool $publiclyQueryable): void
    {
        $postType = new WP_Post_Type('test', [
            '_builtin' => $builtin,
            'publicly_queryable' => $publiclyQueryable,
        ]);

        $this->assertSame($expected, $this->api->shouldConsiderPostType($postType));
    }

    public static function shouldConsiderPostTypeProvider(): iterable
    {
        yield 'eligible' => [true, false, true];
        yield 'builtin' => [false, true, true];
        yield 'not publicly queryable' => [false, false, false];
        yield 'builtin and not queryable' => [false, true, false];
    }

    public function testConstantsAreDefined(): void
    {
        $this->assertEquals('is_page_for_custom_post_type', Api::QUERY_VAR_IS_PFCPT);
        $this->assertEquals('page_for_', Api::OPTION_PREFIX);
        $this->assertEquals('_use_slug', Api::OPTION_SUFFIX_USE_SLUG);
        $this->assertEquals('pages_for_custom_post_type', Api::OPTION_PAGE_IDS);
    }

    #[DataProvider('useSlugOptionNameProvider')]
    public function testGetUseSlugOptionNameReturnsSuffixedString(string $postType, string $expected): void
    {
        $this->assertEquals($expected, $this->api->getUseSlugOptionName($postType));
    }

    public static function useSlugOptionNameProvider(): iterable
    {
        yield 'simple' => ['book', 'page_for_book_use_slug'];
        yield 'simple 2' => ['movie', 'page_for_movie_use_slug'];
        yield 'with underscore' => ['custom_type', 'page_for_custom_type_use_slug'];
    }

    public function testGetUseSlugOptionNameWithWpPostTypeObject(): void
    {
        $postType = new WP_Post_Type('book', []);

        $this->assertEquals('page_for_book_use_slug', $this->api->getUseSlugOptionName($postType));
    }
}
