<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class RewriteManagerTest extends TestCase
{
    private RewriteManager $rewriteManager;

    private Api $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();

        // SettingsValidator::validate() may be triggered via sanitize_option hooks
        if (!\function_exists('add_settings_error')) {
            require_once \ABSPATH . 'wp-admin/includes/template.php';
        }

        $this->api = new Api();
        $this->rewriteManager = new RewriteManager($this->api);
    }

    public function testGetPageSlugReturnsSlug(): void
    {
        $slug = $this->rewriteManager->getPageSlug($this->homeForBookId);

        $this->assertIsString($slug);
        $this->assertStringContainsString('home-for-books', $slug);
    }

    public function testGetPageSlugReturnsNullForInvalidPost(): void
    {
        $slug = $this->rewriteManager->getPageSlug(999999);

        $this->assertNull($slug);
    }

    public function testGetCachedPageSlugCachesResult(): void
    {
        // First call computes and caches
        $slug = $this->rewriteManager->getCachedPageSlug(self::BOOK_POST_TYPE);

        $this->assertIsString($slug);

        // Verify transient was set
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        $cached = \get_transient($cacheKey);

        $this->assertNotFalse($cached);
        $this->assertEquals($slug, $cached);
    }

    public function testGetCachedPageSlugReturnsCachedValue(): void
    {
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        \set_transient($cacheKey, 'cached-slug', 0);

        $slug = $this->rewriteManager->getCachedPageSlug(self::BOOK_POST_TYPE);

        $this->assertEquals('cached-slug', $slug);
    }

    public function testGetCachedPageSlugReturnsNullForEmptyCache(): void
    {
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        \set_transient($cacheKey, '', 0);

        $slug = $this->rewriteManager->getCachedPageSlug(self::BOOK_POST_TYPE);

        $this->assertNull($slug);
    }

    public function testGetCachedPageSlugReturnsNullForUnassignedPostType(): void
    {
        $slug = $this->rewriteManager->getCachedPageSlug('nonexistent');

        $this->assertNull($slug);
    }

    public function testGetCachedPageSlugReturnsNullForUnpublishedPage(): void
    {
        // Create a draft page and assign it
        $draftPageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'draft',
            'post_name'   => 'draft-page',
        ]);

        // Register a new post type with the draft page
        \register_post_type('drafttest', [
            'public'             => true,
            'publicly_queryable' => true,
        ]);
        \update_option('page_for_drafttest', $draftPageId);
        \update_option(Api::OPTION_PAGE_IDS, [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
            'drafttest'          => $draftPageId,
        ]);

        $slug = $this->rewriteManager->getCachedPageSlug('drafttest');

        $this->assertNull($slug);

        \unregister_post_type('drafttest');
    }

    public function testClearPageSlugCacheDeletesTransient(): void
    {
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        \set_transient($cacheKey, 'some-slug', 0);

        $this->rewriteManager->clearPageSlugCache(self::BOOK_POST_TYPE);

        $this->assertFalse(\get_transient($cacheKey));
    }

    public function testFlushRewriteRulesDeletesRewriteOption(): void
    {
        // Set some rewrite rules
        \update_option('rewrite_rules', [
            'some' => 'rules',
        ]);

        $this->rewriteManager->flushRewriteRules(self::BOOK_POST_TYPE);

        $this->assertFalse(\get_option('rewrite_rules'));
    }

    public function testFlushRewriteRulesFiresAction(): void
    {
        $firedPostType = null;
        \add_action('pfcpt/flush_rewrite_rules', static function (string $pt) use (&$firedPostType) {
            $firedPostType = $pt;
        });

        $this->rewriteManager->flushRewriteRules(self::BOOK_POST_TYPE);

        $this->assertSame(self::BOOK_POST_TYPE, $firedPostType);
    }

    public function testAddRewriteTagsForNonHierarchicalPostType(): void
    {
        $postTypeObject = \get_post_type_object(self::BOOK_POST_TYPE);
        $this->assertNotNull($postTypeObject);

        $this->rewriteManager->addRewriteTags($postTypeObject);

        global $wp_rewrite;

        $bookTag = '%' . self::BOOK_POST_TYPE . '%';
        $tagIndex = \array_search($bookTag, $wp_rewrite->rewritecode, true);

        $this->assertNotFalse($tagIndex);
        $this->assertStringContainsString('(?!page)', $wp_rewrite->rewritereplace[$tagIndex]);
        $this->assertStringContainsString('[^/]+', $wp_rewrite->rewritereplace[$tagIndex]);
    }

    public function testAddRewriteTagsForHierarchicalPostType(): void
    {
        \register_post_type('hierarchical_cpt', [
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'query_var'          => 'hierarchical_cpt',
        ]);

        $postTypeObject = \get_post_type_object('hierarchical_cpt');
        $this->assertNotNull($postTypeObject);

        $this->rewriteManager->addRewriteTags($postTypeObject);

        global $wp_rewrite;

        $tag = '%hierarchical_cpt%';
        $tagIndex = \array_search($tag, $wp_rewrite->rewritecode, true);

        $this->assertNotFalse($tagIndex);
        $this->assertStringContainsString('(?!page)', $wp_rewrite->rewritereplace[$tagIndex]);
        $this->assertStringContainsString('.+?', $wp_rewrite->rewritereplace[$tagIndex]);

        \unregister_post_type('hierarchical_cpt');
    }

    public function testAddRewriteTagsWithCustomPermastruct(): void
    {
        \register_post_type('perma_cpt', [
            'public'             => true,
            'publicly_queryable' => true,
            'rewrite'            => [
                'slug'        => 'perma',
                'permastruct' => '/perma/%category%/%postname%/',
            ],
        ]);

        $postTypeObject = \get_post_type_object('perma_cpt');
        $this->assertNotNull($postTypeObject);

        $this->rewriteManager->addRewriteTags($postTypeObject);

        global $wp_rewrite;

        // The %category% tag should now have the (?!page) exclusion
        $categoryTag = '%category%';
        $tagIndex = \array_search($categoryTag, $wp_rewrite->rewritecode, true);

        $this->assertNotFalse($tagIndex);
        $this->assertStringContainsString('(?!page)', $wp_rewrite->rewritereplace[$tagIndex]);

        \unregister_post_type('perma_cpt');
    }

    public function testAddRewriteTagsWithPermastructSkipsNonMatchingTags(): void
    {
        \register_post_type('perma_cpt2', [
            'public'             => true,
            'publicly_queryable' => true,
            'rewrite'            => [
                'slug' => 'perma2',
                // No %category% or %author% before %postname% — just a direct slug
                'permastruct' => '/perma2/%postname%/',
            ],
        ]);

        $postTypeObject = \get_post_type_object('perma_cpt2');
        $this->assertNotNull($postTypeObject);

        // Store original rewrite state
        global $wp_rewrite;
        $originalRewriteReplace = $wp_rewrite->rewritereplace;

        $this->rewriteManager->addRewriteTags($postTypeObject);

        // No tags should have been modified
        $this->assertEquals($originalRewriteReplace, $wp_rewrite->rewritereplace);

        \unregister_post_type('perma_cpt2');
    }

    public function testGetPageSlugCacheKeyFormat(): void
    {
        $key = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);

        $this->assertEquals('page_for_book_slug', $key);
    }
}
