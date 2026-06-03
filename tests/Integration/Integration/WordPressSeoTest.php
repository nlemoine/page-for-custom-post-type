<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Integration\WordPressSeo\Breadcrumbs;
use n5s\PageForCustomPostType\Integration\WordPressSeo\Indexables;
use n5s\PageForCustomPostType\Integration\WordPressSeo\Schema;
use n5s\PageForCustomPostType\Integration\WordPressSeo\WordPressSeo;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;
use PHPUnit\Framework\Attributes\RequiresFunction;
use Yoast\WP\SEO\Memoizers\Meta_Tags_Context_Memoizer;
use Yoast\WP\SEO\Repositories\Indexable_Repository;

/**
 * Integration tests for Yoast SEO integration.
 *
 * Tests breadcrumbs and schema markup when Yoast SEO is active.
 */
#[RequiresFunction('YoastSEO')]
class WordPressSeoTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('YoastSEO')) {
            $this->markTestSkipped('Yoast SEO is not installed.');
        }

        parent::setUp();
        $this->createFixtures();

        // Configure Yoast SEO main taxonomy for books
        \YoastSEO()->helpers->options->set('post_types-' . self::BOOK_POST_TYPE . '-maintax', self::GENRE_TAXONOMY);

        // Ensure Yoast indexables exist for our PFCPT pages
        $this->ensureIndexable($this->homeForBookId);
        $this->ensureIndexable($this->homeForBikeId);

        // Clear Yoast SEO cache
        $memoizer = \YoastSEO()->classes->get(Meta_Tags_Context_Memoizer::class);
        $memoizer->clear();
    }

    private function ensureIndexable(int $postId): void
    {
        /** @var Indexable_Repository $repository */
        $repository = \YoastSEO()->classes->get(Indexable_Repository::class);
        $repository->find_by_id_and_type($postId, 'post');
    }

    public function testCanonicalUrlOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        $meta = \YoastSEO()->meta->for_current_page();

        $this->assertEquals($this->getBookHomeUrl(), $meta->canonical);
    }

    public function testPageTitleOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        $meta = \YoastSEO()->meta->for_current_page();

        $this->assertStringContainsString('Home for Books', $meta->title);
    }

    public function testBreadcrumbsOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        $meta = \YoastSEO()->meta->for_current_page();

        $expected = [
            [
                'url' => home_url('/'),
                'text' => 'Home',
            ],
            [
                'url' => $this->getBookHomeUrl(),
                'text' => 'Home for Books',
                'id' => $this->homeForBookId,
            ],
        ];

        $this->assertEquals($expected, $meta->breadcrumbs);
    }

    public function testBreadcrumbsOnSinglePostIncludesPfcptPage(): void
    {
        // Get a random book for testing
        $books = get_posts([
            'post_type' => self::BOOK_POST_TYPE,
            'posts_per_page' => 1,
            'orderby' => 'rand',
        ]);
        $book = $books[0];
        $this->get(get_permalink($book));

        $meta = \YoastSEO()->meta->for_current_page();
        $breadcrumbs = $meta->breadcrumbs;

        // Check that breadcrumbs include the PFCPT page
        $pfcptBreadcrumb = null;
        foreach ($breadcrumbs as $crumb) {
            if (\is_array($crumb) && ($crumb['id'] ?? null) === $this->homeForBookId) {
                $pfcptBreadcrumb = $crumb;
                break;
            }
        }

        $this->assertNotNull($pfcptBreadcrumb, 'PFCPT page should be in breadcrumbs');
        $this->assertEquals($this->getBookHomeUrl(), $pfcptBreadcrumb['url']);
        $this->assertEquals('Home for Books', $pfcptBreadcrumb['text']);

        // Check that the current post is in breadcrumbs (last item)
        $lastCrumb = end($breadcrumbs);
        $this->assertIsArray($lastCrumb);
        $this->assertArrayHasKey('text', $lastCrumb);
        $this->assertEquals($book->post_title, $lastCrumb['text']);
    }

    public function testBreadcrumbsOnTaxonomyArchiveIncludesPfcptPage(): void
    {
        // Ensure books are assigned to the genre term
        $genreId = $this->getOrCreateTerm(self::GENRE_TAXONOMY, 'Fantasy');
        foreach ($this->bookIds as $bookId) {
            wp_set_object_terms($bookId, $genreId, self::GENRE_TAXONOMY);
        }

        // Clear Yoast cache to avoid stale state from previous tests
        $memoizer = \YoastSEO()->classes->get(Meta_Tags_Context_Memoizer::class);
        $memoizer->clear();

        $genre = get_term($genreId, self::GENRE_TAXONOMY);
        $this->get(get_term_link($genre));

        $meta = \YoastSEO()->meta->for_current_page();

        // Check that breadcrumbs include home, PFCPT page, and taxonomy
        $this->assertCount(3, $meta->breadcrumbs);

        // First crumb is home
        $this->assertEquals(home_url('/'), $meta->breadcrumbs[0]['url']);

        // Second crumb is PFCPT page
        $this->assertEquals($this->getBookHomeUrl(), $meta->breadcrumbs[1]['url']);
        $this->assertEquals('Home for Books', $meta->breadcrumbs[1]['text']);

        // Third crumb is the taxonomy term
        $this->assertStringContainsString('fantasy', $meta->breadcrumbs[2]['url']);
        $this->assertEquals($genre->name, $meta->breadcrumbs[2]['text']);
    }

    public function testSchemaWebpageTypeIncludesCollectionPage(): void
    {
        $this->get($this->getBookHomeUrl());

        // The schema filter should add CollectionPage
        $type = apply_filters('wpseo_schema_webpage_type', 'WebPage');

        $this->assertIsArray($type);
        $this->assertContains('CollectionPage', $type);
    }

    public function testSchemaWebpageTypeUnchangedOnRegularPage(): void
    {
        $this->get(get_permalink($this->staticFrontPageId));

        $type = apply_filters('wpseo_schema_webpage_type', 'WebPage');

        $this->assertEquals('WebPage', $type);
    }

    public function testSchemaWebpageTypeDoesNotDuplicateCollectionPage(): void
    {
        $this->get($this->getBookHomeUrl());

        // CollectionPage already present: the filter must return it unchanged.
        $type = apply_filters('wpseo_schema_webpage_type', ['WebPage', 'CollectionPage']);

        $this->assertSame(['WebPage', 'CollectionPage'], $type);
    }

    public function testIsSupported(): void
    {
        $seo = new WordPressSeo(new Schema(new Api()), new Breadcrumbs(new Api()), new Indexables(new Api()));

        $this->assertTrue($seo->isSupported());
    }

    public function testRegisterHooksRegistersAllSubIntegrations(): void
    {
        $schema = new Schema(new Api());
        $breadcrumbs = new Breadcrumbs(new Api());
        $indexables = new Indexables(new Api());

        (new WordPressSeo($schema, $breadcrumbs, $indexables))->registerHooks();

        $this->assertNotFalse(has_filter('wpseo_schema_webpage_type', [$schema, 'addCollectionPageType']));
        $this->assertNotFalse(has_filter('wpseo_breadcrumb_indexables', [$breadcrumbs, 'fixPostBreadcrumbs']));
        $this->assertNotFalse(has_filter('wpseo_breadcrumb_indexables', [$breadcrumbs, 'fixTaxonomyBreadcrumbs']));
        $this->assertNotFalse(has_action('wp', [$indexables, 'configurePageDetection']));
    }

    public function testPageDetectionResolvesPfcptPageWithStaticFrontPage(): void
    {
        // With a static front page, Yoast's Current_Page_Helper would resolve the
        // posts page instead of the PFCPT page. Indexables::configurePageDetection
        // intercepts show_on_front (only for that helper) so detection falls through
        // to the PFCPT page id.
        $this->configureStaticFrontPage();

        $memoizer = \YoastSEO()->classes->get(Meta_Tags_Context_Memoizer::class);
        $memoizer->clear();

        $this->get($this->getBookHomeUrl());

        $meta = \YoastSEO()->meta->for_current_page();

        $this->assertSame($this->getBookHomeUrl(), $meta->canonical);
    }

    public function testTaxonomyBreadcrumbsSkippedForNonMainTaxonomy(): void
    {
        // The breadcrumb fix only applies when the current taxonomy is the post
        // type's configured main taxonomy. Point books' main taxonomy elsewhere
        // and confirm the genre archive is left untouched (no PFCPT crumb).
        \YoastSEO()->helpers->options->set('post_types-' . self::BOOK_POST_TYPE . '-maintax', 'category');

        $genreId = $this->getOrCreateTerm(self::GENRE_TAXONOMY, 'Fantasy');
        foreach ($this->bookIds as $bookId) {
            wp_set_object_terms($bookId, $genreId, self::GENRE_TAXONOMY);
        }

        $memoizer = \YoastSEO()->classes->get(Meta_Tags_Context_Memoizer::class);
        $memoizer->clear();

        $genre = get_term($genreId, self::GENRE_TAXONOMY);
        $this->get(get_term_link($genre));

        $meta = \YoastSEO()->meta->for_current_page();

        foreach ($meta->breadcrumbs as $crumb) {
            $this->assertNotSame($this->homeForBookId, \is_array($crumb) ? ($crumb['id'] ?? null) : null);
        }
    }
}
