<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Integration\Polylang\Admin;
use n5s\PageForCustomPostType\Integration\Polylang\Lifecycle;
use n5s\PageForCustomPostType\Integration\Polylang\Polylang;
use n5s\PageForCustomPostType\Integration\Polylang\SlugTranslation;
use n5s\PageForCustomPostType\Integration\Polylang\Translation;
use n5s\PageForCustomPostType\Integration\Polylang\UrlTranslation;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for Polylang integration.
 */
class PolylangTest extends TestCase
{
    private int $homeForBookFrId;

    private int $homeForBikeFrId;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('PLL')) {
            $this->markTestSkipped('Polylang is not installed.');
        }

        $this->setUpLanguages();
        $this->createFixtures();
        $this->createTranslatedPages();
    }

    /**
     * Set up Polylang languages.
     */
    private function setUpLanguages(): void
    {
        // Only add languages if they don't exist yet
        $existingLanguages = pll_languages_list();

        if (!\in_array('en', $existingLanguages, true)) {
            PLL()->model->add_language([
                'name' => 'English',
                'slug' => 'en',
                'locale' => 'en_US',
                'rtl' => false,
                'term_group' => 0,
            ]);
        }

        if (!\in_array('fr', $existingLanguages, true)) {
            PLL()->model->add_language([
                'name' => 'Français',
                'slug' => 'fr',
                'locale' => 'fr_FR',
                'rtl' => false,
                'term_group' => 1,
            ]);
        }

        PLL()->model->update_default_lang('en');
    }

    /**
     * Create translated PFCPT pages and link them.
     */
    private function createTranslatedPages(): void
    {
        // Set language for English home pages
        pll_set_post_language($this->homeForBookId, 'en');
        pll_set_post_language($this->homeForBikeId, 'en');

        // Create French translations
        $this->homeForBookFrId = $this->getOrCreatePage('accueil-livres', 'Accueil Livres');
        pll_set_post_language($this->homeForBookFrId, 'fr');

        $this->homeForBikeFrId = $this->getOrCreatePage('accueil-velos', 'Accueil Vélos');
        pll_set_post_language($this->homeForBikeFrId, 'fr');

        // Link translations
        pll_save_post_translations([
            'en' => $this->homeForBookId,
            'fr' => $this->homeForBookFrId,
        ]);

        pll_save_post_translations([
            'en' => $this->homeForBikeId,
            'fr' => $this->homeForBikeFrId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Polylang composite
    // -------------------------------------------------------------------------

    public function testIsSupported(): void
    {
        $polylang = new Polylang(
            new UrlTranslation(),
            new Translation(),
            new SlugTranslation(new Api(), new RewriteManager(new Api())),
            new Admin(),
            new Lifecycle(new Api(), new RewriteManager(new Api()))
        );

        $this->assertTrue($polylang->isSupported());
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function testDropdownArgsFilteredToDefaultLanguage(): void
    {
        $admin = new Admin();
        $admin->registerHooks();

        $args = apply_filters('pfcpt/dropdown_page_args', ['post_type' => 'page']);

        $this->assertArrayHasKey('lang', $args);
        $this->assertEquals('en', $args['lang']);
    }

    // -------------------------------------------------------------------------
    // Translation
    // -------------------------------------------------------------------------

    public function testPageIdsTranslatedForCurrentLanguage(): void
    {
        $translation = new Translation();
        $translation->registerHooks();

        // Simulate French as current language
        PLL()->curlang = PLL()->model->get_language('fr');

        $pageIds = apply_filters('pfcpt/page_ids', [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
        ]);

        $this->assertEquals($this->homeForBookFrId, $pageIds[self::BOOK_POST_TYPE]);
        $this->assertEquals($this->homeForBikeFrId, $pageIds[self::BIKE_POST_TYPE]);
    }

    public function testPageIdsNotTranslatedWhenNoCurrentLanguage(): void
    {
        $translation = new Translation();
        $translation->registerHooks();

        // No current language set
        PLL()->curlang = null;

        $originalIds = [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
        ];

        $pageIds = apply_filters('pfcpt/page_ids', $originalIds);

        $this->assertEquals($originalIds, $pageIds);
    }

    public function testTranslatedPageIdsCached(): void
    {
        $translation = new Translation();
        $translation->registerHooks();

        PLL()->curlang = PLL()->model->get_language('fr');

        // First call should set transient
        apply_filters('pfcpt/page_ids', [
            self::BOOK_POST_TYPE => $this->homeForBookId,
        ]);

        $cacheKey = $translation->getCacheKey('fr');
        $cached = get_transient($cacheKey);

        $this->assertIsArray($cached);
        $this->assertEquals($this->homeForBookFrId, $cached[self::BOOK_POST_TYPE]);
    }

    public function testResolveDefaultLanguagePageId(): void
    {
        $translation = new Translation();
        $translation->registerHooks();

        // No current language — should resolve to default language page
        PLL()->curlang = null;

        $resolved = apply_filters('pfcpt/post_type_from_id/page_id', $this->homeForBookFrId);

        $this->assertEquals($this->homeForBookId, $resolved);
    }

    public function testResolvePageIdUnchangedWhenCurrentLanguageSet(): void
    {
        $translation = new Translation();
        $translation->registerHooks();

        PLL()->curlang = PLL()->model->get_language('fr');

        $resolved = apply_filters('pfcpt/post_type_from_id/page_id', $this->homeForBookFrId);

        $this->assertEquals($this->homeForBookFrId, $resolved);
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function testFlushCacheDeletesLanguageTransients(): void
    {
        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $lifecycle = new Lifecycle($api, $rewriteManager);
        $lifecycle->registerHooks();

        // Set transients for each language
        set_transient(Api::OPTION_PAGE_IDS . '_en', ['book' => 1]);
        set_transient(Api::OPTION_PAGE_IDS . '_fr', ['book' => 2]);
        set_transient('pll_translated_slugs', ['data']);

        $lifecycle->flushCache();

        $this->assertFalse(get_transient(Api::OPTION_PAGE_IDS . '_en'));
        $this->assertFalse(get_transient(Api::OPTION_PAGE_IDS . '_fr'));
        $this->assertFalse(get_transient('pll_translated_slugs'));
    }

    public function testOnPostSaveFlushesRewriteRulesForPfcptPage(): void
    {
        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $lifecycle = new Lifecycle($api, $rewriteManager);
        $lifecycle->registerHooks();

        // Set a transient that should be cleared after flush
        $cacheKey = Api::OPTION_PAGE_IDS . '_en';
        set_transient($cacheKey, ['book' => $this->homeForBookId]);

        $post = get_post($this->homeForBookFrId);

        // Simulate pll_save_post with translation array
        do_action('pll_save_post', $this->homeForBookFrId, $post, [
            'en' => $this->homeForBookId,
            'fr' => $this->homeForBookFrId,
        ]);

        // The transient should be cleared because the default language post is a PFCPT page
        $this->assertFalse(get_transient($cacheKey));
    }

    public function testOnPostSaveIgnoresNonPfcptPages(): void
    {
        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $lifecycle = new Lifecycle($api, $rewriteManager);
        $lifecycle->registerHooks();

        $cacheKey = Api::OPTION_PAGE_IDS . '_en';
        set_transient($cacheKey, ['book' => $this->homeForBookId]);

        // Create a regular page (not a PFCPT page)
        $regularPageId = self::factory()->post->create(['post_type' => 'page']);
        pll_set_post_language($regularPageId, 'en');
        $regularPost = get_post($regularPageId);

        do_action('pll_save_post', $regularPageId, $regularPost, [
            'en' => $regularPageId,
        ]);

        // Transient should still exist
        $this->assertNotFalse(get_transient($cacheKey));
    }

    // -------------------------------------------------------------------------
    // SlugTranslation
    // -------------------------------------------------------------------------

    public function testTranslateSlugForNonDefaultLanguage(): void
    {
        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $slugTranslation = new SlugTranslation($api, $rewriteManager);
        $slugTranslation->registerHooks();

        $frLanguage = PLL()->model->get_language('fr');

        $slugs = [
            self::BOOK_POST_TYPE => [
                'translations' => [
                    'en' => 'home-for-books',
                    'fr' => 'home-for-books',
                ],
            ],
        ];

        $result = apply_filters('pll_translated_slugs', $slugs, $frLanguage);

        // The French slug should be derived from the French page's permalink
        $pageSlug = $rewriteManager->getPageSlug($this->homeForBookFrId);

        if ($pageSlug !== null) {
            $translatedSlug = $result[self::BOOK_POST_TYPE]['translations']['fr'];
            // The slug is computed by stripping the language prefix (e.g. "fr/") from the page slug
            $expectedSlug = substr($pageSlug, \strlen('fr/'));
            $this->assertEquals($expectedSlug, $translatedSlug);
            $this->assertNotEquals('home-for-books', $translatedSlug);
        }
    }

    public function testSlugTranslationSkippedForDefaultLanguage(): void
    {
        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $slugTranslation = new SlugTranslation($api, $rewriteManager);
        $slugTranslation->registerHooks();

        $enLanguage = PLL()->model->get_language('en');

        $slugs = [
            self::BOOK_POST_TYPE => [
                'translations' => [
                    'en' => 'home-for-books',
                    'fr' => 'home-for-books',
                ],
            ],
        ];

        $result = apply_filters('pll_translated_slugs', $slugs, $enLanguage);

        // Should be unchanged for default language
        $this->assertEquals($slugs, $result);
    }

    public function testSlugTranslationSkipsNonPfcptPostTypes(): void
    {
        $api = new Api();
        $rewriteManager = new RewriteManager($api);
        $slugTranslation = new SlugTranslation($api, $rewriteManager);
        $slugTranslation->registerHooks();

        $frLanguage = PLL()->model->get_language('fr');

        $slugs = [
            'product' => [
                'translations' => [
                    'en' => 'products',
                    'fr' => 'produits',
                ],
            ],
        ];

        $result = apply_filters('pll_translated_slugs', $slugs, $frLanguage);

        // Non-PFCPT post types should be unchanged
        $this->assertEquals($slugs, $result);
    }

    // -------------------------------------------------------------------------
    // UrlTranslation
    // -------------------------------------------------------------------------

    public function testBeforeTranslationUrlSetsIsPostsPage(): void
    {
        $urlTranslation = new UrlTranslation();

        $frLanguage = PLL()->model->get_language('fr');

        // Simulate being on a home (PFCPT) page
        $GLOBALS['wp_query']->is_home = true;
        $GLOBALS['wp_query']->is_posts_page = false;

        $urlTranslation->beforeTranslationUrl('', $frLanguage, $this->homeForBookId);

        $this->assertTrue($GLOBALS['wp_query']->is_posts_page);
        $this->assertFalse($GLOBALS['pfcpt_is_posts_page']);
    }

    public function testAfterTranslationUrlRestoresIsPostsPage(): void
    {
        $urlTranslation = new UrlTranslation();

        $frLanguage = PLL()->model->get_language('fr');

        // Simulate state after beforeTranslationUrl
        $GLOBALS['wp_query']->is_home = true;
        $GLOBALS['wp_query']->is_posts_page = true;
        $GLOBALS['pfcpt_is_posts_page'] = false;

        $urlTranslation->afterTranslationUrl('', $frLanguage, $this->homeForBookId);

        $this->assertFalse($GLOBALS['wp_query']->is_posts_page);
        $this->assertArrayNotHasKey('pfcpt_is_posts_page', $GLOBALS);
    }

    public function testTranslationUrlHooksNoOpWhenNotHome(): void
    {
        $urlTranslation = new UrlTranslation();

        $frLanguage = PLL()->model->get_language('fr');

        // Clean up any global state from prior tests
        unset($GLOBALS['pfcpt_is_posts_page']);

        // Not on a home page
        $GLOBALS['wp_query']->is_home = false;
        $GLOBALS['wp_query']->is_posts_page = false;

        $urlTranslation->beforeTranslationUrl('', $frLanguage, $this->homeForBookId);

        // Should remain unchanged
        $this->assertFalse($GLOBALS['wp_query']->is_posts_page);
        $this->assertArrayNotHasKey('pfcpt_is_posts_page', $GLOBALS);
    }
}
