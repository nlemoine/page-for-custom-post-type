<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Routing tests with Polylang in directory mode.
 *
 * Polylang duplicates the rewrite rules with a language prefix, shifting the
 * match indexes and injecting lang=$matches[1]. It does that per rule group:
 * the rules of a post type go through {$post_type}_rewrite_rules and are
 * always duplicated, while the full set goes through rewrite_rules_array
 * where only the rules carrying post_type= are. Anything the plugin adds on
 * its own has to survive that, so these tests go through real requests on
 * prefixed URLs rather than through the API.
 *
 * @see PLL_Links_Directory::rewrite_rules()
 */
class PolylangRoutingTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private const BASELINE_OPTIONS = [
        'hide_default' => false,
        'rewrite' => true,
        'default_lang' => 'fr',
        'post_types' => [self::BOOK_POST_TYPE],
    ];

    private int $subPageId;

    /**
     * Polylang is booted once per process.
     *
     * Mantle does not restore $wp_filter between tests, so building a new
     * Polylang instance in every setUp() stacks a fresh copy of all its
     * filters (the callbacks are object methods, and a new object means a new
     * unique id, so WordPress does not dedupe them). Everything that varies
     * per test is read from the options at runtime, so one boot is enough.
     */
    private static bool $booted = false;

    /**
     * Whether the hooks snapshot has been taken with Polylang's rewrite rules
     * filters attached.
     */
    private static bool $hooksSnapshotTaken = false;

    /**
     * The Polylang options are a process-wide object, so a test that changes
     * them puts them back.
     */
    private bool $reconfigured = false;

    /**
     * Boot Polylang before any test runs.
     *
     * It is a one-time, process-wide operation and it does not settle within
     * the test that triggers it, so the first test of the class would be the
     * only one running without the language rules. PLL_Model::get_links_model()
     * falls back to PLL_Links_Default on plain permalinks, hence the structure
     * being set here too.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!\function_exists('PLL') || self::$booted) {
            return;
        }

        global $wp_rewrite;

        $wp_rewrite->init();
        $wp_rewrite->set_permalink_structure('/%postname%/');

        (new \Polylang())->init();

        self::$booted = true;
    }

    protected function tearDown(): void
    {
        if ($this->reconfigured) {
            $this->configure(self::BASELINE_OPTIONS);
            $this->flushWithLanguagePrefixes();
            $this->reconfigured = false;
        }

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('PLL')) {
            $this->markTestSkipped('Polylang is not installed.');
        }

        $this->bootDirectoryMode();
        $this->createFixtures();
        $this->setUpContent();
    }

    /**
     * Boot Polylang in directory mode, with the language shown for every
     * language including the default one.
     *
     * The suite bootstrap builds Polylang before any permalink structure is
     * set, and PLL_Model::get_links_model() falls back to PLL_Links_Default
     * on plain permalinks, so it has to be rebuilt here.
     */
    private function bootDirectoryMode(): void
    {
        // The database is rolled back between tests, so the languages are
        // gone but Polylang still has them cached. Drop the cache before
        // looking, otherwise the check below thinks they are still there.
        PLL()->model->clean_languages_cache();

        $existing = pll_languages_list();

        foreach (
            [
            ['name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => false, 'term_group' => 0],
            ['name' => 'Français', 'slug' => 'fr', 'locale' => 'fr_FR', 'rtl' => false, 'term_group' => 1],
            ] as $language
        ) {
            if (!\in_array($language['slug'], $existing, true)) {
                PLL()->model->add_language($language);
            }
        }

        // force_lang defaults to 1 (directory) and is not settable here.
        $this->configure(self::BASELINE_OPTIONS);

        PLL()->curlang = PLL()->model->get_language('fr');

        // Before anything flushes: WP_Rewrite appends to extra_rules_top on
        // every generation without resetting it, so a flush without these
        // filters would leave unprefixed rules in front of the prefixed ones
        // for the rest of the test.
        $this->prepareLanguageRules();
    }

    /**
     * Attach Polylang's rewrite rules filters.
     *
     * Polylang attaches them from a listener it registers when it boots, and
     * guards them behind a static flag it raises right after wp_loaded. The
     * test case backs up and restores $wp_filter around every test, so that
     * listener is gone after the first one: the method is called directly
     * rather than through the action. The flag is static and survives.
     */
    private function prepareLanguageRules(): void
    {
        do_action('wp_loaded');

        PLL()->links_model->do_prepare_rewrite_rules();
        PLL()->links_model->prepare_rewrite_rules();

        if (!self::$hooksSnapshotTaken) {
            // Mantle snapshots the hooks on the first test of the process and
            // restores that same snapshot after every one, so the filters
            // Polylang attaches here are dropped as soon as the first test
            // ends. Take the snapshot again now that they are on, so they
            // become part of what gets restored.
            self::$hooks_saved = [];
            self::backup_hooks();

            self::$hooksSnapshotTaken = true;
        }
    }

    /**
     * Change the Polylang options and rebuild the rules.
     *
     * Only force_lang decides the links model, and it never changes here, so
     * everything else is read from the options on each pass and a flush is
     * enough.
     *
     * @param array<string, mixed> $options
     */
    private function configure(array $options): void
    {
        foreach ($options as $key => $value) {
            // Options::set() always returns a WP_Error, empty when it took.
            $result = PLL()->options->set($key, $value);

            $this->assertFalse(
                $result->has_errors(),
                \sprintf('Polylang refused the %s option: %s', $key, $result->get_error_message())
            );
            $this->assertSame(
                $value,
                PLL()->options[$key],
                "The {$key} option did not take, the configuration would be a no-op"
            );
        }

        PLL()->model->clean_languages_cache();
    }

    private function setUpContent(): void
    {
        pll_set_post_language($this->homeForBookId, 'fr');

        foreach ($this->bookIds as $bookId) {
            pll_set_post_language($bookId, 'fr');
        }

        $this->subPageId = (int) static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'coupes-et-cuissons',
            'post_status' => 'publish',
            'post_parent' => $this->homeForBookId,
        ]);
        pll_set_post_language($this->subPageId, 'fr');

        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', true);

        $postTypeObject = get_post_type_object(self::BOOK_POST_TYPE);
        $args = get_object_vars($postTypeObject);
        unregister_post_type(self::BOOK_POST_TYPE);
        register_post_type(self::BOOK_POST_TYPE, $args);

        $this->flushWithLanguagePrefixes();
    }

    private function flushWithLanguagePrefixes(): void
    {
        $this->prepareLanguageRules();

        flush_rewrite_rules();

        // Polylang leaves the rules alone when it does not see the languages,
        // and what it sees depends on a cache the rollback between tests
        // invalidates. Make the precondition hold rather than hope for it.
        if (!$this->hasLanguagePrefixedRules()) {
            PLL()->model->clean_languages_cache();

            $this->prepareLanguageRules();

            flush_rewrite_rules();
        }

        $this->assertTrue(
            $this->hasLanguagePrefixedRules(),
            'Polylang did not prefix the rewrite rules, the test would not be testing anything'
        );
    }

    private function hasLanguagePrefixedRules(): bool
    {
        global $wp_rewrite;

        foreach (array_keys($wp_rewrite->wp_rewrite_rules()) as $regex) {
            if (str_contains($regex, '(en|fr)/')) {
                return true;
            }
        }

        return false;
    }

    public function testLanguageIsInTheUrl(): void
    {
        $this->assertInstanceOf(\PLL_Links_Directory::class, PLL()->links_model);
        $this->assertTrue($this->hasLanguagePrefixedRules());
    }

    public function testArchiveResolves(): void
    {
        $this->get(home_url('/fr/home-for-books/'));

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue($wp_query->is_home);
        $this->assertSame($this->homeForBookId, $wp_query->queried_object_id);
        $this->assertSame(self::BOOK_POST_TYPE, $wp_query->{Api::QUERY_VAR_IS_PFCPT});
    }

    public function testSingleResolves(): void
    {
        $slug = get_post_field('post_name', $this->bookIds[0]);

        $this->get(home_url("/fr/home-for-books/{$slug}/"));

        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
        $this->assertSame($this->bookIds[0], get_queried_object_id());
    }

    public function testArchivePaginationResolves(): void
    {
        $this->get(home_url('/fr/home-for-books/page/2/'));

        global $wp_query;

        $this->assertFalse($wp_query->is_404, 'The pagination base must not be matched as a post name');
        $this->assertTrue($wp_query->is_home);
        $this->assertTrue(is_paged());
        $this->assertSame($this->homeForBookId, $wp_query->queried_object_id);
        $this->assertNotEmpty($wp_query->posts);
    }

    public function testChildPageResolves(): void
    {
        $this->get(home_url('/fr/home-for-books/coupes-et-cuissons/'));

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_page());
        $this->assertSame($this->subPageId, get_queried_object_id());
    }

    public function testArchiveFeedResolves(): void
    {
        $this->get(home_url('/fr/home-for-books/feed/'));

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue($wp_query->is_feed);
        $this->assertFalse($wp_query->is_comment_feed);
        $this->assertNotEmpty($wp_query->posts);
    }

    public function testSingleFeedResolves(): void
    {
        $slug = get_post_field('post_name', $this->bookIds[0]);

        $this->get(home_url("/fr/home-for-books/{$slug}/feed/"));

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue($wp_query->is_feed);
        $this->assertTrue(is_singular(self::BOOK_POST_TYPE));
    }

    // -----------------------------------------------------------------
    // Other language URL settings
    //
    // force_lang decides the links model and is fixed for the process, the
    // rest is read from the options on every rule generation.
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $options
     */
    private function reconfigure(array $options): void
    {
        $this->reconfigured = true;

        $this->configure($options);
        $this->flushWithLanguagePrefixes();
    }

    public function testArchivePaginationRoutesWithTheDefaultLanguageHidden(): void
    {
        $this->reconfigure(['hide_default' => true]);

        // French is the default one, so its URLs carry no prefix.
        $this->get(home_url('/home-for-books/page/2/'));

        global $wp;

        // Only the routing is asserted here: the archive query comes back
        // empty in this configuration for reasons that have nothing to do
        // with the rules (same on a build without any of this).
        $this->assertSame('(.?.+?)/page/?([0-9]{1,})/?$', $wp->matched_rule);
        $this->assertSame('home-for-books', $wp->query_vars['pagename'] ?? null);
        $this->assertSame('2', $wp->query_vars['paged'] ?? null);
    }

    public function testChildPageResolvesWithTheDefaultLanguageHidden(): void
    {
        $this->reconfigure(['hide_default' => true]);

        $this->get(home_url('/home-for-books/coupes-et-cuissons/'));

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_page());
        $this->assertSame($this->subPageId, get_queried_object_id());
    }

    public function testArchivePaginationResolvesWithTheLanguageDirectory(): void
    {
        // rewrite off puts the language behind /language/.
        $this->reconfigure(['rewrite' => false]);

        $this->get(home_url('/language/fr/home-for-books/page/2/'));

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue($wp_query->is_home);
        $this->assertTrue(is_paged());
        $this->assertSame($this->homeForBookId, $wp_query->queried_object_id);
    }

    public function testChildPageResolvesWithTheLanguageDirectory(): void
    {
        $this->reconfigure(['rewrite' => false]);

        $this->get(home_url('/language/fr/home-for-books/coupes-et-cuissons/'));

        global $wp_query;

        $this->assertFalse($wp_query->is_404);
        $this->assertTrue(is_page());
        $this->assertSame($this->subPageId, get_queried_object_id());
    }

    public function testAttachmentResolves(): void
    {
        $attachmentId = (int) static::factory()->post->create([
            'post_type' => 'attachment',
            'post_parent' => $this->bookIds[0],
            'post_name' => 'an-image',
            'post_status' => 'inherit',
            'post_mime_type' => 'image/jpeg',
        ]);
        pll_set_post_language($attachmentId, 'fr');

        $slug = get_post_field('post_name', $this->bookIds[0]);

        $this->get(home_url("/fr/home-for-books/{$slug}/an-image/"));

        $this->assertTrue(is_attachment());
        $this->assertSame($attachmentId, get_queried_object_id());
    }
}
