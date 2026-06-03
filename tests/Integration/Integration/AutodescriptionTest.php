<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Integration\Autodescription\Autodescription;
use n5s\PageForCustomPostType\Integration\Autodescription\Breadcrumbs;
use n5s\PageForCustomPostType\Integration\Autodescription\QueryType;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use The_SEO_Framework\Helper\Query;
use The_SEO_Framework\Helper\Query\Cache as QueryCache;
use The_SEO_Framework\Meta\Breadcrumbs as TsfBreadcrumbs;

/**
 * Integration tests for The SEO Framework integration.
 *
 * Tests query type detection, breadcrumbs, and schema markup when TSF is active.
 */
#[RequiresFunction('the_seo_framework')]
class AutodescriptionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('the_seo_framework')) {
            $this->markTestSkipped('The SEO Framework is not installed.');
        }

        parent::setUp();
        $this->createFixtures();
        $this->clearTsfCache();
    }

    public function testPfcptPageIsDetectedAsSingularArchive(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(Query::is_singular_archive());
    }

    public function testPfcptPageIsDetectedAsSingular(): void
    {
        $this->get($this->getBookHomeUrl());

        $this->assertTrue(Query::is_singular());
    }

    public function testRegularPageIsNotSingularArchive(): void
    {
        $this->get(get_permalink($this->staticFrontPageId));

        $this->assertFalse(Query::is_singular_archive());
    }

    public function testCanonicalUrlOnPfcptPage(): void
    {
        // Use explicit args to avoid TSF's query-level memoization
        $canonical = tsf()->get_canonical_url(['id' => $this->homeForBookId]);

        $this->assertEquals($this->getBookHomeUrl(), $canonical);
    }

    public function testPageTitleOnPfcptPage(): void
    {
        // Use explicit args to avoid TSF's query-level memoization
        $title = tsf()->get_title(['id' => $this->homeForBookId]);

        $this->assertStringContainsString('Home for Books', $title);
    }

    public function testBreadcrumbsOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        // Use explicit args to avoid TSF's function-level memoization
        $breadcrumbs = TsfBreadcrumbs::get_breadcrumb_list(['id' => $this->homeForBookId]);

        // Should have Home + PFCPT page
        $this->assertCount(2, $breadcrumbs);

        // First is home
        $this->assertEquals(home_url('/'), $breadcrumbs[0]['url']);

        // Second is the PFCPT page itself
        $this->assertEquals($this->getBookHomeUrl(), $breadcrumbs[1]['url']);
        $this->assertStringContainsString('Home for Books', $breadcrumbs[1]['name']);
    }

    #[WithoutErrorHandler]
    public function testBreadcrumbsOnSinglePostIncludesPfcptPage(): void
    {
        $books = get_posts([
            'post_type' => self::BOOK_POST_TYPE,
            'posts_per_page' => 1,
            'orderby' => 'rand',
        ]);
        $book = $books[0];
        $this->get(get_permalink($book));

        // Use explicit args to avoid TSF's function-level memoization
        $breadcrumbs = TsfBreadcrumbs::get_breadcrumb_list(['id' => $book->ID]);

        // Should have at least: Home, PFCPT page, current post
        $this->assertGreaterThanOrEqual(3, \count($breadcrumbs));

        // First is home
        $this->assertEquals(home_url('/'), $breadcrumbs[0]['url']);

        // Second is the PFCPT page
        $this->assertEquals($this->getBookHomeUrl(), $breadcrumbs[1]['url']);
        $this->assertStringContainsString('Home for Books', $breadcrumbs[1]['name']);

        // Last is the current post
        $lastCrumb = end($breadcrumbs);
        $this->assertEquals($book->post_title, $lastCrumb['name']);
    }

    public function testBreadcrumbsOnTaxonomyArchiveIncludesPfcptPage(): void
    {
        $genreId = $this->getOrCreateTerm(self::GENRE_TAXONOMY, 'Fantasy');
        foreach ($this->bookIds as $bookId) {
            wp_set_object_terms($bookId, $genreId, self::GENRE_TAXONOMY);
        }

        $genre = get_term($genreId, self::GENRE_TAXONOMY);
        $this->clearTsfCache();

        $this->get(get_term_link($genre));

        // Use explicit args to avoid TSF's function-level memoization
        $breadcrumbs = TsfBreadcrumbs::get_breadcrumb_list([
            'id' => $genreId,
            'tax' => self::GENRE_TAXONOMY,
        ]);

        // Should have: Home, PFCPT page, taxonomy term
        $this->assertNotFalse(
            has_filter('the_seo_framework_breadcrumb_list'),
            'Breadcrumb filter should be registered'
        );

        $this->assertCount(3, $breadcrumbs);

        // First is home
        $this->assertEquals(home_url('/'), $breadcrumbs[0]['url']);

        // Second is the PFCPT page
        $this->assertEquals($this->getBookHomeUrl(), $breadcrumbs[1]['url']);
        $this->assertStringContainsString('Home for Books', $breadcrumbs[1]['name']);

        // Third is the taxonomy term
        $this->assertEquals(get_term_link($genre), $breadcrumbs[2]['url']);
        $this->assertStringContainsString($genre->name, $breadcrumbs[2]['name']);
    }

    public function testSchemaIncludesCollectionPageOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        // TSF automatically sets CollectionPage for singular archives
        $this->assertTrue(Query::is_singular_archive(), 'PFCPT page should be detected as singular archive for CollectionPage schema');
    }

    public function testNoBreadcrumbDuplicationOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        // Use explicit args to avoid TSF's function-level memoization
        $breadcrumbs = TsfBreadcrumbs::get_breadcrumb_list(['id' => $this->homeForBookId]);

        // The PFCPT page should NOT appear twice
        $pfcptCount = 0;
        foreach ($breadcrumbs as $crumb) {
            if (($crumb['url'] ?? '') === $this->getBookHomeUrl()) {
                $pfcptCount++;
            }
        }

        $this->assertEquals(1, $pfcptCount, 'PFCPT page should appear exactly once in breadcrumbs');
    }

    public function testPostTypeWithoutPfcptPageHasNoBreadcrumbChange(): void
    {
        $postId = self::factory()->post->create([
            'post_type' => 'post',
            'post_title' => 'Regular Post',
        ]);
        $this->get(get_permalink($postId));

        // Use explicit args to avoid TSF's function-level memoization
        $breadcrumbs = TsfBreadcrumbs::get_breadcrumb_list(['id' => $postId]);

        // No PFCPT page crumb should be injected
        foreach ($breadcrumbs as $crumb) {
            $this->assertNotEquals($this->getBookHomeUrl(), $crumb['url'] ?? '');
        }
    }

    public function testIsSupported(): void
    {
        $autodescription = new Autodescription(new QueryType(new Api()), new Breadcrumbs(new Api()));

        $this->assertTrue($autodescription->isSupported());
    }

    public function testRegisterHooksRegistersSubIntegrations(): void
    {
        $queryType = new QueryType(new Api());
        $breadcrumbs = new Breadcrumbs(new Api());

        (new Autodescription($queryType, $breadcrumbs))->registerHooks();

        $this->assertNotFalse(has_filter('the_seo_framework_is_singular_archive', [$queryType, 'markPfcptAsSingularArchive']));
        $this->assertNotFalse(has_filter('the_seo_framework_breadcrumb_list', [$breadcrumbs, 'addPfcptPageCrumb']));
    }

    public function testSingularArchiveShortCircuitsWhenAlreadyTrue(): void
    {
        $queryType = new QueryType(new Api());

        $this->assertTrue($queryType->markPfcptAsSingularArchive(true, null));
    }

    public function testSingularArchiveResolvedFromExplicitPageId(): void
    {
        $queryType = new QueryType(new Api());

        $this->assertTrue($queryType->markPfcptAsSingularArchive(false, $this->homeForBookId));

        $regularId = self::factory()->post->create(['post_type' => 'post']);
        $this->assertFalse($queryType->markPfcptAsSingularArchive(false, $regularId));
    }

    public function testBreadcrumbCrumbSkippedForUnresolvableArgs(): void
    {
        $breadcrumbs = new Breadcrumbs(new Api());
        $list = [['url' => home_url('/'), 'name' => 'Home']];

        // id is neither int nor WP_Post
        $this->assertSame($list, $breadcrumbs->addPfcptPageCrumb($list, ['id' => 'not-an-id']));
        // id points to a non-existent post
        $this->assertSame($list, $breadcrumbs->addPfcptPageCrumb($list, ['id' => 999999]));
        // taxonomy is not a string
        $this->assertSame($list, $breadcrumbs->addPfcptPageCrumb($list, ['id' => 1, 'tax' => 123]));
        // neither a single post nor a taxonomy archive
        $this->assertSame($list, $breadcrumbs->addPfcptPageCrumb($list, ['uid' => 7]));
        // unknown taxonomy
        $this->assertSame($list, $breadcrumbs->addPfcptPageCrumb($list, ['id' => 1, 'tax' => 'does_not_exist']));
        // real taxonomy with no PFCPT-bound post type
        $this->assertSame($list, $breadcrumbs->addPfcptPageCrumb($list, ['id' => 1, 'tax' => 'category']));
    }

    /**
     * Clear TSF's internal memoization cache between tests.
     *
     * TSF uses static properties for memoization, which persist across
     * test methods. We use Reflection to reset them so each test starts fresh.
     */
    private function clearTsfCache(): void
    {
        $cacheRef = new \ReflectionClass(QueryCache::class);

        $memoProp = $cacheRef->getProperty('memo');
        $memoProp->setValue(null, []);

        $canCacheProp = $cacheRef->getProperty('can_cache_query');
        $canCacheProp->setValue(null, null);

        // Clear Breadcrumbs static options cache
        $breadcrumbsRef = new \ReflectionClass(TsfBreadcrumbs::class);
        if ($breadcrumbsRef->hasProperty('options')) {
            $optionsProp = $breadcrumbsRef->getProperty('options');
            $optionsProp->setValue(null, []);
        }
    }
}
