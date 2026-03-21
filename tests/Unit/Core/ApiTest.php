<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Unit\Core;

use n5s\PageForCustomPostType\Core\Api;
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

    public function testGetOptionNameReturnsPrefixedString(): void
    {
        $this->assertEquals('page_for_book', $this->api->getOptionName('book'));
        $this->assertEquals('page_for_movie', $this->api->getOptionName('movie'));
        $this->assertEquals('page_for_custom_type', $this->api->getOptionName('custom_type'));
    }

    public function testGetOptionNameWithWpPostTypeObject(): void
    {
        $postType = new WP_Post_Type('book', []);

        $this->assertEquals('page_for_book', $this->api->getOptionName($postType));
    }

    public function testGetConditionalNameReturnsIsPrefixWithSuffix(): void
    {
        $this->assertEquals('is_book_page', $this->api->getConditionalName('book'));
        $this->assertEquals('is_movie_page', $this->api->getConditionalName('movie'));
        $this->assertEquals('is_custom_type_page', $this->api->getConditionalName('custom_type'));
    }

    public function testGetConditionalNameWithWpPostTypeObject(): void
    {
        $postType = new WP_Post_Type('book', []);

        $this->assertEquals('is_book_page', $this->api->getConditionalName($postType));
    }

    public function testShouldConsiderPostTypeReturnsTrueForEligiblePostType(): void
    {
        $postType = new WP_Post_Type('book', [
            '_builtin' => false,
            'publicly_queryable' => true,
        ]);

        $this->assertTrue($this->api->shouldConsiderPostType($postType));
    }

    public function testShouldConsiderPostTypeReturnsFalseForBuiltinPostType(): void
    {
        $postType = new WP_Post_Type('post', [
            '_builtin' => true,
            'publicly_queryable' => true,
        ]);

        $this->assertFalse($this->api->shouldConsiderPostType($postType));
    }

    public function testShouldConsiderPostTypeReturnsFalseForNonPubliclyQueryable(): void
    {
        $postType = new WP_Post_Type('private_type', [
            '_builtin' => false,
            'publicly_queryable' => false,
        ]);

        $this->assertFalse($this->api->shouldConsiderPostType($postType));
    }

    public function testConstantsAreDefined(): void
    {
        $this->assertEquals('is_page_for_custom_post_type', Api::QUERY_VAR_IS_PFCPT);
        $this->assertEquals('page_for_', Api::OPTION_PREFIX);
        $this->assertEquals('_use_slug', Api::OPTION_SUFFIX_USE_SLUG);
        $this->assertEquals('pages_for_custom_post_type', Api::OPTION_PAGE_IDS);
    }

    public function testGetUseSlugOptionNameReturnsSuffixedString(): void
    {
        $this->assertEquals('page_for_book_use_slug', $this->api->getUseSlugOptionName('book'));
        $this->assertEquals('page_for_movie_use_slug', $this->api->getUseSlugOptionName('movie'));
        $this->assertEquals('page_for_custom_type_use_slug', $this->api->getUseSlugOptionName('custom_type'));
    }

    public function testGetUseSlugOptionNameWithWpPostTypeObject(): void
    {
        $postType = new WP_Post_Type('book', []);

        $this->assertEquals('page_for_book_use_slug', $this->api->getUseSlugOptionName($postType));
    }
}
