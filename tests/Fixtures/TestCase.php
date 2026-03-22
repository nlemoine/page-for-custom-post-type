<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Fixtures;

use Mantle\Testkit\Test_Case;
use n5s\PageForCustomPostType\Plugin;

/**
 * Abstract base test case for integration tests.
 *
 * All integration tests should extend this class to ensure consistent
 * setup and access to common test utilities.
 */
abstract class TestCase extends Test_Case
{
    protected const BOOK_POST_TYPE = 'book';

    protected const BIKE_POST_TYPE = 'bike';

    protected const GENRE_TAXONOMY = 'genre';

    protected int $homeForBookId;

    protected int $homeForBikeId;

    protected int $staticFrontPageId;

    protected int $staticPageForPostsId;

    /**
     * @var int[]
     */
    protected array $bookIds = [];

    /**
     * @var int[]
     */
    protected array $bikeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->set_permalink_structure('/%postname%/');

        // When Polylang is active, ensure the default language is set.
        // This prevents TypeError in Polylang's post creation hooks
        // (Capabilities\Create\Post::get_language expects PLL_Language).
        $this->setPolylangDefaultLanguage();
    }

    /**
     * Create the standard test fixture data.
     *
     * Call this in your test's setUp() when you need the full fixture data.
     */
    protected function createFixtures(): void
    {
        $this->createBooks();
        $this->createBikes();
        $this->createHomePages();
        $this->createStaticPages();
        $this->updatePluginOptions();

        // Regenerate rewrite rules. Tests that call unregister_post_type()
        // or RewriteManager::flushRewriteRules() may leave $wp_rewrite in
        // a state where post type/taxonomy permastructs are missing.
        // Re-registering the taxonomy ensures its permastruct is present.
        if (taxonomy_exists(self::GENRE_TAXONOMY)) {
            unregister_taxonomy(self::GENRE_TAXONOMY);
        }
        register_taxonomy(self::GENRE_TAXONOMY, self::BOOK_POST_TYPE, [
            'public' => true,
            'label' => 'Genres',
            'rewrite' => ['slug' => 'genres'],
        ]);
        flush_rewrite_rules();
    }

    /**
     * Create book posts with a genre taxonomy term.
     *
     * @param int $count Number of books to create
     */
    protected function createBooks(int $count = 30): void
    {
        $this->bookIds = $this->getOrCreatePosts(self::BOOK_POST_TYPE, $count);

        if (empty($this->bookIds)) {
            return;
        }

        $genreId = $this->getOrCreateTerm(self::GENRE_TAXONOMY, 'Fantasy');

        foreach ($this->bookIds as $bookId) {
            wp_set_object_terms($bookId, $genreId, self::GENRE_TAXONOMY);
        }
    }

    /**
     * Create bike posts.
     *
     * @param int $count Number of bikes to create
     */
    protected function createBikes(int $count = 5): void
    {
        $this->bikeIds = $this->getOrCreatePosts(self::BIKE_POST_TYPE, $count);
    }

    /**
     * Create the home pages for custom post types.
     */
    protected function createHomePages(): void
    {
        $this->homeForBookId = $this->getOrCreatePage('home-for-books', 'Home for Books');
        update_option('page_for_' . self::BOOK_POST_TYPE, $this->homeForBookId);

        $this->homeForBikeId = $this->getOrCreatePage('home-for-bikes', 'Home for Bikes');
        update_option('page_for_' . self::BIKE_POST_TYPE, $this->homeForBikeId);

        // When Polylang is active, assign the default language to pages
        // so they appear in language-filtered queries (e.g., wp_dropdown_pages).
        if (\function_exists('pll_set_post_language')) {
            pll_set_post_language($this->homeForBookId, 'en');
            pll_set_post_language($this->homeForBikeId, 'en');
        }
    }

    /**
     * Create static front page and page for posts.
     */
    protected function createStaticPages(): void
    {
        $this->staticFrontPageId = $this->getOrCreatePage('welcome-home', 'Welcome Home!');
        $this->staticPageForPostsId = $this->getOrCreatePage('posts-page', 'Posts Page');

        if (\function_exists('pll_set_post_language')) {
            pll_set_post_language($this->staticFrontPageId, 'en');
            pll_set_post_language($this->staticPageForPostsId, 'en');
        }
    }

    /**
     * Update the plugin's page IDs option.
     */
    protected function updatePluginOptions(): void
    {
        update_option(Plugin::OPTION_PAGE_IDS, [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
        ]);
    }

    /**
     * Get existing posts or create new ones.
     *
     * @param string $postType Post type to create
     * @param int    $count    Number of posts
     * @return int[] Post IDs
     */
    protected function getOrCreatePosts(string $postType, int $count): array
    {
        $existingIds = get_posts([
            'post_type' => $postType,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        if (\count($existingIds) >= $count) {
            return $existingIds;
        }

        return self::factory()->post->create_many($count, [
            'post_type' => $postType,
        ]);
    }

    /**
     * Get existing page or create a new one.
     *
     * @param string $slug  Page slug
     * @param string $title Page title
     * @return int Page ID
     */
    protected function getOrCreatePage(string $slug, string $title): int
    {
        $page = get_page_by_path($slug);

        if ($page instanceof \WP_Post) {
            return $page->ID;
        }

        return self::factory()->post->create([
            'post_type' => 'page',
            'post_title' => $title,
            'post_name' => $slug,
        ]);
    }

    /**
     * Get existing term or create a new one.
     *
     * @param string $taxonomy Taxonomy name
     * @param string $name     Term name
     * @return int Term ID
     */
    protected function getOrCreateTerm(string $taxonomy, string $name): int
    {
        $term = get_term_by('name', $name, $taxonomy);

        if ($term instanceof \WP_Term) {
            return $term->term_id;
        }

        return self::factory()->term->create([
            'taxonomy' => $taxonomy,
            'name' => $name,
        ]);
    }

    /**
     * Configure static front page settings.
     *
     * @param bool $enable Whether to enable static front page
     */
    protected function configureStaticFrontPage(bool $enable = true): void
    {
        if ($enable) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $this->staticFrontPageId);
            update_option('page_for_posts', $this->staticPageForPostsId);
        } else {
            update_option('show_on_front', 'posts');
            delete_option('page_on_front');
            delete_option('page_for_posts');
        }
    }

    /**
     * Get the URL for the book home page.
     */
    protected function getBookHomeUrl(): string
    {
        return get_permalink($this->homeForBookId);
    }

    /**
     * Get the URL for the bike home page.
     */
    protected function getBikeHomeUrl(): string
    {
        return get_permalink($this->homeForBikeId);
    }

    /**
     * Assert that specific query conditionals are true.
     *
     * @param string ...$conditionals Conditional names (without 'is_' prefix)
     */
    protected function assertPfcptQueryConditionals(string ...$conditionals): void
    {
        global $wp_query;

        foreach ($conditionals as $conditional) {
            $property = 'is_' . $conditional;

            if (property_exists($wp_query, $property)) {
                $this->assertTrue(
                    (bool) $wp_query->$property,
                    "Expected {$property} to be true"
                );
            } elseif (function_exists($property)) {
                $this->assertTrue(
                    $property(),
                    "Expected {$property}() to return true"
                );
            } else {
                $this->fail("Unknown conditional: {$conditional}");
            }
        }
    }

    /**
     * Assert the posts in the query match expected IDs.
     *
     * @param int[] $expectedIds Expected post IDs in order
     */
    protected function assertQueriedPostIds(array $expectedIds): void
    {
        global $wp_query;

        $actualIds = array_column($wp_query->posts, 'ID');

        $this->assertEquals(
            $expectedIds,
            $actualIds,
            'Queried post IDs do not match expected values'
        );
    }

    /**
     * Set up Polylang languages when Polylang is active.
     *
     * Polylang's Capabilities\Create\Post::get_language() requires a valid
     * PLL_Language return type. Refresh_Database's transaction rollback removes
     * language terms created during bootstrap, so we must recreate them in
     * every test's setUp and set curlang to prevent TypeError.
     */
    private function setPolylangDefaultLanguage(): void
    {
        if (!\function_exists('PLL') || !PLL() instanceof \PLL_Base) {
            return;
        }

        // Ensure languages exist within this test's transaction.
        $defaultLang = PLL()->model->get_default_language();

        if (!$defaultLang instanceof \PLL_Language) {
            // Languages were rolled back by Refresh_Database — recreate them.
            // Mark the expected _doing_it_wrong calls from Polylang's model.
            $this->setExpectedIncorrectUsage('WP_Syntex\Polylang\Model\Languages::get_list()');

            $options = new \WP_Syntex\Polylang\Options\Options();
            $model = new \PLL_Admin_Model($options);

            $languages = [
                ['name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => false, 'term_group' => 0, 'flag' => 'us'],
                ['name' => 'Français', 'slug' => 'fr', 'locale' => 'fr_FR', 'rtl' => false, 'term_group' => 1, 'flag' => 'fr'],
            ];

            foreach ($languages as $language) {
                if (!$model->get_language($language['slug'])) {
                    $model->add_language($language);
                }
            }

            $model->update_default_lang('en');

            PLL()->model->clean_languages_cache();
            $defaultLang = PLL()->model->get_default_language();
        }

        if ($defaultLang instanceof \PLL_Language) {
            PLL()->curlang = $defaultLang;
        }
    }
}
