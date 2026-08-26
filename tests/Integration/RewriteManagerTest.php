<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testGetPageSlugStripsIndexPermalinkRoot(): void
    {
        $this->set_permalink_structure('/index.php/%postname%/');

        $this->assertStringContainsString(
            '/index.php/home-for-books/',
            (string) get_permalink($this->homeForBookId)
        );

        $this->assertSame('home-for-books', $this->rewriteManager->getPageSlug($this->homeForBookId));
    }

    public function testGetPageSlugReturnsNullWithPlainPermalinks(): void
    {
        $this->set_permalink_structure('');

        $this->assertNull($this->rewriteManager->getPageSlug($this->homeForBookId));
    }

    public function testGetPageSlugReturnsFullPathForNestedPage(): void
    {
        $parentId = static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'library',
            'post_status' => 'publish',
        ]);
        wp_update_post([
            'ID' => $this->homeForBookId,
            'post_parent' => $parentId,
        ]);

        $this->assertSame('library/home-for-books', $this->rewriteManager->getPageSlug($this->homeForBookId));
    }

    public function testGetCachedPageSlugCachesResult(): void
    {
        // First call computes and caches
        $slug = $this->rewriteManager->getCachedPageSlug(self::BOOK_POST_TYPE);

        $this->assertIsString($slug);

        // Verify transient was set
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        $cached = get_transient($cacheKey);

        $this->assertNotFalse($cached);
        $this->assertEquals($slug, $cached);
    }

    public function testGetCachedPageSlugReturnsCachedValue(): void
    {
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        set_transient($cacheKey, 'cached-slug', 0);

        $slug = $this->rewriteManager->getCachedPageSlug(self::BOOK_POST_TYPE);

        $this->assertEquals('cached-slug', $slug);
    }

    public function testGetCachedPageSlugReturnsNullForEmptyCache(): void
    {
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        set_transient($cacheKey, '', 0);

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
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_name' => 'draft-page',
        ]);

        // Register a new post type with the draft page
        register_post_type('drafttest', [
            'public' => true,
            'publicly_queryable' => true,
        ]);
        update_option('page_for_drafttest', $draftPageId);
        update_option(Api::OPTION_PAGE_IDS, [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
            'drafttest' => $draftPageId,
        ]);

        $slug = $this->rewriteManager->getCachedPageSlug('drafttest');

        $this->assertNull($slug);

        unregister_post_type('drafttest');
    }

    public function testClearPageSlugCacheDeletesTransient(): void
    {
        $cacheKey = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);
        set_transient($cacheKey, 'some-slug', 0);

        $this->rewriteManager->clearPageSlugCache(self::BOOK_POST_TYPE);

        $this->assertFalse(get_transient($cacheKey));
    }

    public function testFlushRewriteRulesDeletesRewriteOption(): void
    {
        // Set some rewrite rules
        update_option('rewrite_rules', ['some' => 'rules']);

        $this->rewriteManager->flushRewriteRules(self::BOOK_POST_TYPE);

        $this->assertFalse(get_option('rewrite_rules'));
    }

    public function testFlushRewriteRulesFiresAction(): void
    {
        $firedPostType = null;
        add_action('pfcpt/flush_rewrite_rules', static function (string $pt) use (&$firedPostType) {
            $firedPostType = $pt;
        });

        $this->rewriteManager->flushRewriteRules(self::BOOK_POST_TYPE);

        $this->assertSame(self::BOOK_POST_TYPE, $firedPostType);
    }

    public function testAddRewriteTagsExcludesThePaginationBase(): void
    {
        global $wp_rewrite;

        $postTypeObject = get_post_type_object(self::BOOK_POST_TYPE);
        $this->assertNotNull($postTypeObject);

        $this->rewriteManager->addRewriteTags($postTypeObject);

        $tagIndex = array_search('%' . self::BOOK_POST_TYPE . '%', $wp_rewrite->rewritecode, true);

        $this->assertNotFalse($tagIndex);
        $this->assertSame('(?!page)([^/]+)', $wp_rewrite->rewritereplace[$tagIndex]);
    }

    public function testAddRewriteTagsFollowsThePaginationBase(): void
    {
        global $wp_rewrite;

        // The base is translatable, the lookahead must follow it.
        $wp_rewrite->pagination_base = 'pagina';

        $postTypeObject = get_post_type_object(self::BOOK_POST_TYPE);
        $this->assertNotNull($postTypeObject);

        $this->rewriteManager->addRewriteTags($postTypeObject);

        $tagIndex = array_search('%' . self::BOOK_POST_TYPE . '%', $wp_rewrite->rewritecode, true);

        $this->assertSame('(?!pagina)([^/]+)', $wp_rewrite->rewritereplace[$tagIndex]);
    }

    public function testAddRewriteTagsKeepsTheHierarchicalRegex(): void
    {
        register_post_type('hierarchical_cpt', [
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => true,
            'query_var' => 'hierarchical_cpt',
        ]);

        $postTypeObject = get_post_type_object('hierarchical_cpt');
        $this->assertNotNull($postTypeObject);

        $this->rewriteManager->addRewriteTags($postTypeObject);

        global $wp_rewrite;

        $tagIndex = array_search('%hierarchical_cpt%', $wp_rewrite->rewritecode, true);

        $this->assertSame('(?!page)(.+?)', $wp_rewrite->rewritereplace[$tagIndex]);

        unregister_post_type('hierarchical_cpt');
    }

    /**
     * @param array<string, string> $rules
     * @param array<string, string> $expected
     */
    #[DataProvider('mangledRulesProvider')]
    public function testRestorePaginationExclusion(array $rules, array $expected): void
    {
        $this->assertSame($expected, $this->rewriteManager->restorePaginationExclusion($rules));
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, string>}>
     */
    public static function mangledRulesProvider(): iterable
    {
        yield 'stripped parentheses' => [
            ['books/?!page[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]'],
            ['books/(?!page)[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]'],
        ];

        yield 'stripped parentheses, hierarchical' => [
            ['books/?!page.+?/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]'],
            ['books/(?!page).+?/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]'],
        ];

        yield 'an intact exclusion is left alone' => [
            ['books/(?!page)([^/]+)/?$' => 'index.php?book=$matches[1]'],
            ['books/(?!page)([^/]+)/?$' => 'index.php?book=$matches[1]'],
        ];

        yield 'an unrelated rule is left alone' => [
            ['(.?.+?)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]'],
            ['(.?.+?)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]'],
        ];
    }

    public function testRestorePaginationExclusionNeverDropsACollidingRule(): void
    {
        $rules = [
            'books/?!page[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
            'books/(?!page)[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]&custom=1',
        ];

        $this->assertSame($rules, $this->rewriteManager->restorePaginationExclusion($rules));
    }

    public function testRestorePaginationExclusionKeepsRuleOrder(): void
    {
        $rules = [
            'books/?!page[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
            'books/(?!page)([^/]+)/?$' => 'index.php?book=$matches[1]',
        ];

        $this->assertSame([
            'books/(?!page)[^/]+/([^/]+)/?$',
            'books/(?!page)([^/]+)/?$',
        ], array_keys($this->rewriteManager->restorePaginationExclusion($rules)));
    }

    public function testGeneratedRulesKeepWorkingAttachmentSubRules(): void
    {
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $postTypeObject = get_post_type_object(self::BOOK_POST_TYPE);
        $this->assertNotNull($postTypeObject);
        $args = get_object_vars($postTypeObject);
        unregister_post_type(self::BOOK_POST_TYPE);
        register_post_type(self::BOOK_POST_TYPE, $args);

        global $wp_rewrite;

        // Generate the post type rules in isolation: rules accumulate in
        // extra_rules_top on every flush within the same process, so earlier
        // (intact) copies would otherwise mask a broken regex.
        $struct = $wp_rewrite->extra_permastructs[self::BOOK_POST_TYPE];
        $rules = $wp_rewrite->generate_rewrite_rules(
            $struct['struct'],
            $struct['ep_mask'],
            $struct['paged'],
            $struct['feed'],
            $struct['forcomments'],
            $struct['walk_dirs'],
            $struct['endpoints']
        );

        // WordPress strips the parentheses of the lookahead when it derives
        // the attachment sub-rules; this is what the plugin filters back.
        $rules = $this->rewriteManager->restorePaginationExclusion($rules);

        foreach (array_keys($rules) as $regex) {
            $this->assertStringNotContainsString(
                '/?!page',
                $regex,
                'A stripped lookahead is left in the generated rules'
            );
        }

        $attachmentRules = array_keys(array_filter(
            $rules,
            static fn (string $query): bool => $query === 'index.php?attachment=$matches[1]'
        ));

        $this->assertNotEmpty($attachmentRules);

        $matching = array_filter(
            $attachmentRules,
            static fn (string $regex): bool => preg_match("#^{$regex}#", 'home-for-books/a-book/an-image') === 1
        );

        $this->assertNotEmpty(
            $matching,
            'No attachment rule matches home-for-books/a-book/an-image. Generated rules: '
            . implode(', ', $attachmentRules)
        );
    }

    public function testGetPageSlugCacheKeyFormat(): void
    {
        $key = $this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE);

        $this->assertEquals('page_for_book_slug', $key);
    }
}
