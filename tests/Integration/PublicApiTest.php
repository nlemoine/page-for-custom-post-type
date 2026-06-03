<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for public API functions.
 *
 * Tests the global functions exposed by the plugin.
 */
class PublicApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testIsPageForCustomPostTypeReturnsTrueOnPfcptPage(): void
    {
        $this->setExpectedDeprecated('is_page_for_custom_post_type');
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(is_page_for_custom_post_type());
    }

    public function testIsPageForCustomPostTypeReturnsTrueForSpecificPostType(): void
    {
        $this->setExpectedDeprecated('is_page_for_custom_post_type');
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(is_page_for_custom_post_type(self::BOOK_POST_TYPE));
    }

    public function testIsPageForCustomPostTypeReturnsFalseForWrongPostType(): void
    {
        $this->setExpectedDeprecated('is_page_for_custom_post_type');
        $this->get($this->getBookHomeUrl());

        $this->assertFalse(is_page_for_custom_post_type('post'));
        $this->assertFalse(is_page_for_custom_post_type(self::BIKE_POST_TYPE));
    }

    public function testIsPageForCustomPostTypeReturnsFalseOnRegularPage(): void
    {
        $this->setExpectedDeprecated('is_page_for_custom_post_type');
        $this->get(get_permalink($this->staticFrontPageId));

        $this->assertFalse(is_page_for_custom_post_type());
    }

    public function testGetCustomPostTypeForPageReturnsPostType(): void
    {
        $this->setExpectedDeprecated('get_custom_post_type_for_page');

        $this->assertEquals(
            self::BOOK_POST_TYPE,
            get_custom_post_type_for_page($this->homeForBookId)
        );
    }

    public function testGetCustomPostTypeForPageReturnsNullForNonPfcptPage(): void
    {
        $this->setExpectedDeprecated('get_custom_post_type_for_page');

        $this->assertNull(get_custom_post_type_for_page($this->staticFrontPageId));
    }

    public function testGetPageIdForCustomPostTypeReturnsPageId(): void
    {
        $this->setExpectedDeprecated('get_page_id_for_custom_post_type');

        $this->assertEquals(
            $this->homeForBookId,
            get_page_id_for_custom_post_type(self::BOOK_POST_TYPE)
        );
    }

    public function testGetPageIdForCustomPostTypeReturnsNullForUnknownPostType(): void
    {
        $this->setExpectedDeprecated('get_page_id_for_custom_post_type');

        $this->assertNull(get_page_id_for_custom_post_type('nonexistent'));
    }

    public function testGetPageIdForCustomPostTypeUsesCurrentQueryWhenNull(): void
    {
        $this->setExpectedDeprecated('get_page_id_for_custom_post_type');
        $this->get($this->getBookHomeUrl());

        $this->assertEquals(
            $this->homeForBookId,
            get_page_id_for_custom_post_type()
        );
    }

    public function testGetPageIdForCustomPostTypeReturnsNullOnRegularPage(): void
    {
        $this->setExpectedDeprecated('get_page_id_for_custom_post_type');
        $this->get(get_permalink($this->staticFrontPageId));

        $this->assertNull(get_page_id_for_custom_post_type());
    }

    public function testGetPageUrlForCustomPostTypeReturnsUrl(): void
    {
        $this->setExpectedDeprecated('get_page_url_for_custom_post_type');
        $expectedUrl = get_permalink($this->homeForBookId);

        $this->assertEquals(
            $expectedUrl,
            get_page_url_for_custom_post_type(self::BOOK_POST_TYPE)
        );
    }

    public function testGetPageUrlForCustomPostTypeReturnsNullForUnknownPostType(): void
    {
        $this->setExpectedDeprecated('get_page_url_for_custom_post_type');

        $this->assertNull(get_page_url_for_custom_post_type('nonexistent'));
    }

    public function testGetPageUrlForCustomPostTypeUsesCurrentQueryWhenNull(): void
    {
        $this->setExpectedDeprecated('get_page_url_for_custom_post_type');
        $this->get($this->getBookHomeUrl());

        $expectedUrl = get_permalink($this->homeForBookId);

        $this->assertEquals($expectedUrl, get_page_url_for_custom_post_type());
    }

    public function testNamespacedFunctionsWork(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
        $this->assertEquals(
            self::BOOK_POST_TYPE,
            \n5s\PageForCustomPostType\get_custom_post_type_for_page($this->homeForBookId)
        );
        $this->assertEquals(
            $this->homeForBookId,
            \n5s\PageForCustomPostType\get_page_id_for_custom_post_type(self::BOOK_POST_TYPE)
        );
        $this->assertEquals(
            get_permalink($this->homeForBookId),
            \n5s\PageForCustomPostType\get_page_url_for_custom_post_type(self::BOOK_POST_TYPE)
        );
    }
}
