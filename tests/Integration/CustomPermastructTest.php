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

    public function testArchivePaginationRuleIsRegisteredWhenUseSlugEnabled(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        global $wp_rewrite;

        $this->assertSame(
            'index.php?pagename=home-for-books&paged=$matches[1]',
            $wp_rewrite->extra_rules_top['home-for-books/page/?([0-9]{1,})/?$'] ?? null
        );
    }

    public function testArchivePaginationRuleIsNotRegisteredWhenUseSlugDisabled(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', false);

        $this->reRegisterPostType(self::BOOK_POST_TYPE);

        global $wp_rewrite;

        // The post type keeps its own slug, core's generic page rule already
        // resolves /home-for-books/page/2/.
        $this->assertArrayNotHasKey(
            'home-for-books/page/?([0-9]{1,})/?$',
            $wp_rewrite->extra_rules_top
        );
    }
}
