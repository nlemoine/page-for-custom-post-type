<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class CustomPermastructTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    private function reRegisterPostType(string $postType): void
    {
        $postTypeObject = get_post_type_object($postType);
        if (!$postTypeObject) {
            return;
        }
        $args = get_object_vars($postTypeObject);
        unregister_post_type($postType);
        register_post_type($postType, $args);
    }

    public function testPaginationWorksWithStandardPermastruct(): void
    {
        $this->get($this->getBookHomeUrl() . 'page/2/');

        global $wp_query;

        $this->assertTrue($wp_query->is_home);
        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
        $this->assertGreaterThan(1, $wp_query->query_vars['paged']);
        $this->assertNotEmpty($wp_query->posts);
    }

    public function testSinglePostAccessibleWithPageSlugPermastruct(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);
        flush_rewrite_rules();

        $permalink = get_permalink($this->bookIds[0]);
        $this->assertStringContainsString('/home-for-books/', $permalink);

        $this->get($permalink);

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertEquals($this->bookIds[0], get_queried_object_id());
    }

    public function testPaginationWorksWhenUseSlugEnabled(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);
        flush_rewrite_rules();

        $this->get($this->getBookHomeUrl() . 'page/2/');

        global $wp_query;

        $this->assertTrue($wp_query->is_home);
        $this->assertTrue(\n5s\PageForCustomPostType\is_page_for_custom_post_type());
        $this->assertTrue(is_paged());
        $this->assertNotEmpty($wp_query->posts);
    }

    public function testPageIdDetectedWithNameQueryVar(): void
    {
        $api = new Api();

        $query = new \WP_Query();
        $query->query_vars = ['name' => 'test-slug'];
        $query->queried_object_id = $this->homeForBookId;

        $result = $api->getPageIdFromQuery($query);

        $this->assertSame($this->homeForBookId, $result);
    }

    public function testPageIdDetectedWithPagenameQueryVar(): void
    {
        $api = new Api();

        $query = new \WP_Query();
        $query->query_vars = ['pagename' => 'test-slug'];
        $query->queried_object_id = $this->homeForBookId;

        $result = $api->getPageIdFromQuery($query);

        $this->assertSame($this->homeForBookId, $result);
    }

    public function testPageIdNullWhenNoRelevantQueryVars(): void
    {
        $api = new Api();

        $query = new \WP_Query();
        $query->query_vars = [];
        $query->queried_object_id = $this->homeForBookId;

        $result = $api->getPageIdFromQuery($query);

        $this->assertNull($result);
    }

    public function testPaginationBaseIsExcludedWhenUseSlugEnabled(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        global $wp_rewrite;

        $tagIndex = array_search('%' . self::BOOK_POST_TYPE . '%', $wp_rewrite->rewritecode, true);

        $this->assertNotFalse($tagIndex);
        $this->assertStringContainsString('(?!page)', $wp_rewrite->rewritereplace[$tagIndex]);
    }

    public function testPaginationBaseIsNotExcludedWhenUseSlugDisabled(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', false);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        global $wp_rewrite;

        // The post type keeps its own slug, there is no collision to avoid.
        $tagIndex = array_search('%' . self::BOOK_POST_TYPE . '%', $wp_rewrite->rewritecode, true);

        $this->assertNotFalse($tagIndex);
        $this->assertStringNotContainsString('(?!page)', $wp_rewrite->rewritereplace[$tagIndex]);
    }
}
