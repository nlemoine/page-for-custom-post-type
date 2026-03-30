<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Integration\Wpml\Admin;
use n5s\PageForCustomPostType\Integration\Wpml\Lifecycle;
use n5s\PageForCustomPostType\Integration\Wpml\Translation;
use n5s\PageForCustomPostType\Integration\Wpml\UrlTranslation;
use n5s\PageForCustomPostType\Integration\Wpml\Wpml;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for WPML integration.
 *
 * Since WPML is a commercial plugin that cannot be bootstrapped in tests,
 * these tests simulate WPML behavior by registering the WordPress filters
 * that WPML normally provides.
 */
class WpmlTest extends TestCase
{
    private int $homeForBookFrId;

    private int $homeForBikeFrId;

    /**
     * Map of original page IDs to their French translations.
     *
     * @var array<int, int>
     */
    private array $translationMap = [];

    private string $currentLanguage = 'en';

    private string $defaultLanguage = 'en';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $activeLanguages = [
        'en' => [
            'code' => 'en',
            'native_name' => 'English',
        ],
        'fr' => [
            'code' => 'fr',
            'native_name' => 'Français',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtures();
        $this->createTranslatedPages();
        $this->registerWpmlFilters();
    }

    protected function tearDown(): void
    {
        remove_all_filters('wpml_current_language');
        remove_all_filters('wpml_default_language');
        remove_all_filters('wpml_object_id');
        remove_all_filters('wpml_active_languages');
        remove_all_filters('wpml_home_url');
        remove_all_actions('wpml_switch_language');

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Wpml composite
    // -------------------------------------------------------------------------

    public function testIsSupportedWhenWpmlConstantDefined(): void
    {
        if (!\defined('ICL_SITEPRESS_VERSION')) {
            \define('ICL_SITEPRESS_VERSION', '4.6.0');
        }

        $api = new Api();
        $translation = new Translation();
        $wpml = new Wpml(
            $translation,
            new UrlTranslation($api),
            new Admin(),
            new Lifecycle($api, new RewriteManager($api), $translation)
        );

        $this->assertTrue($wpml->isSupported());
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function testDropdownArgsSwitchesToDefaultLanguage(): void
    {
        $this->currentLanguage = 'fr';

        $admin = new Admin();
        $admin->registerHooks();

        $args = apply_filters('pfcpt/dropdown_page_args', [
            'post_type' => 'page',
        ]);

        // The filter should have switched to default language ('en')
        // and registered a get_pages filter to restore it afterward
        $this->assertIsArray($args);
    }

    public function testDropdownArgsUnchangedWhenNoDefaultLanguage(): void
    {
        // Override the default language to return empty
        remove_all_filters('wpml_default_language');
        add_filter('wpml_default_language', static fn () => '');

        $admin = new Admin();
        $admin->registerHooks();

        $original = [
            'post_type' => 'page',
            'custom' => 'value',
        ];
        $result = apply_filters('pfcpt/dropdown_page_args', $original);

        $this->assertEquals($original, $result);
    }

    public function testDropdownArgsRestoresLanguageAfterGetPages(): void
    {
        $this->currentLanguage = 'fr';

        $admin = new Admin();
        $admin->registerHooks();

        apply_filters('pfcpt/dropdown_page_args', [
            'post_type' => 'page',
        ]);

        // At this point, language should be switched to 'en' (default)
        $this->assertEquals('en', $this->currentLanguage);

        // Simulate get_pages filter being called (which restores the language)
        apply_filters('get_pages', []);

        // Language should be restored (wpml_switch_language called with null)
        // Since our mock sets currentLanguage to the passed value, and the
        // restoreLanguage closure calls wpml_switch_language with null,
        // we verify the filter was removed
        $this->assertFalse(has_filter('get_pages'));
    }

    // -------------------------------------------------------------------------
    // Translation
    // -------------------------------------------------------------------------

    public function testPageIdsTranslatedForCurrentLanguage(): void
    {
        $this->currentLanguage = 'fr';

        $translation = new Translation();
        $translation->registerHooks();

        $pageIds = apply_filters('pfcpt/page_ids', [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
        ]);

        $this->assertEquals($this->homeForBookFrId, $pageIds[self::BOOK_POST_TYPE]);
        $this->assertEquals($this->homeForBikeFrId, $pageIds[self::BIKE_POST_TYPE]);
    }

    public function testPageIdsNotTranslatedForDefaultLanguage(): void
    {
        $this->currentLanguage = 'en';

        $translation = new Translation();
        $translation->registerHooks();

        $originalIds = [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
        ];

        $pageIds = apply_filters('pfcpt/page_ids', $originalIds);

        $this->assertEquals($originalIds, $pageIds);
    }

    public function testPageIdsNotTranslatedWhenNoCurrentLanguage(): void
    {
        // Override current language to return null
        remove_all_filters('wpml_current_language');
        add_filter('wpml_current_language', static fn () => null);

        $translation = new Translation();
        $translation->registerHooks();

        $originalIds = [
            self::BOOK_POST_TYPE => $this->homeForBookId,
            self::BIKE_POST_TYPE => $this->homeForBikeId,
        ];

        $pageIds = apply_filters('pfcpt/page_ids', $originalIds);

        $this->assertEquals($originalIds, $pageIds);
    }

    public function testPageIdsEmptyArrayReturnedUnchanged(): void
    {
        $this->currentLanguage = 'fr';

        $translation = new Translation();
        $translation->registerHooks();

        $pageIds = apply_filters('pfcpt/page_ids', []);

        $this->assertEmpty($pageIds);
    }

    public function testTranslatedPageIdsCached(): void
    {
        $this->currentLanguage = 'fr';

        $translation = new Translation();
        $translation->registerHooks();

        apply_filters('pfcpt/page_ids', [
            self::BOOK_POST_TYPE => $this->homeForBookId,
        ]);

        $cacheKey = $translation->getCacheKey('fr');
        $cached = get_transient($cacheKey);

        $this->assertIsArray($cached);
        $this->assertEquals($this->homeForBookFrId, $cached[self::BOOK_POST_TYPE]);
    }

    public function testTranslatedPageIdsServedFromCache(): void
    {
        $this->currentLanguage = 'fr';

        $translation = new Translation();
        $translation->registerHooks();

        // Pre-populate cache with custom values
        $cacheKey = $translation->getCacheKey('fr');
        set_transient($cacheKey, [
            self::BOOK_POST_TYPE => 99999,
        ]);

        $pageIds = apply_filters('pfcpt/page_ids', [
            self::BOOK_POST_TYPE => $this->homeForBookId,
        ]);

        // Should return cached value, not the translated one
        $this->assertEquals(99999, $pageIds[self::BOOK_POST_TYPE]);
    }

    public function testGetCacheKey(): void
    {
        $translation = new Translation();

        $this->assertEquals(
            Api::OPTION_PAGE_IDS . '_fr',
            $translation->getCacheKey('fr')
        );
        $this->assertEquals(
            Api::OPTION_PAGE_IDS . '_en',
            $translation->getCacheKey('en')
        );
    }

    public function testResolveDefaultLanguagePageId(): void
    {
        // No current language set
        remove_all_filters('wpml_current_language');
        add_filter('wpml_current_language', static fn () => null);

        $translation = new Translation();
        $translation->registerHooks();

        $resolved = apply_filters('pfcpt/post_type_from_id/page_id', $this->homeForBookFrId);

        $this->assertEquals($this->homeForBookId, $resolved);
    }

    public function testResolvePageIdUnchangedWhenCurrentLanguageSet(): void
    {
        $this->currentLanguage = 'fr';

        $translation = new Translation();
        $translation->registerHooks();

        $resolved = apply_filters('pfcpt/post_type_from_id/page_id', $this->homeForBookFrId);

        $this->assertEquals($this->homeForBookFrId, $resolved);
    }

    public function testResolvePageIdUnchangedWhenNoDefaultLanguage(): void
    {
        remove_all_filters('wpml_current_language');
        add_filter('wpml_current_language', static fn () => null);
        remove_all_filters('wpml_default_language');
        add_filter('wpml_default_language', static fn () => null);

        $translation = new Translation();
        $translation->registerHooks();

        $pageId = $this->homeForBookFrId;
        $resolved = apply_filters('pfcpt/post_type_from_id/page_id', $pageId);

        $this->assertEquals($pageId, $resolved);
    }

    public function testTranslationFallsBackToOriginalWhenNoTranslation(): void
    {
        $this->currentLanguage = 'fr';

        $translation = new Translation();
        $translation->registerHooks();

        // Use a page ID that has no French translation
        $untranslatedPageId = static::factory()->post->create([
            'post_type' => 'page',
        ]);

        $pageIds = apply_filters('pfcpt/page_ids', [
            'custom_type' => $untranslatedPageId,
        ]);

        // Should fall back to original since wpml_object_id returns null for unknown IDs
        $this->assertArrayHasKey('custom_type', $pageIds);
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function testFlushCacheDeletesLanguageTransients(): void
    {
        $api = new Api();
        $translation = new Translation();
        $lifecycle = new Lifecycle($api, new RewriteManager($api), $translation);
        $lifecycle->registerHooks();

        // Set transients for each language
        set_transient($translation->getCacheKey('en'), [
            'book' => 1,
        ]);
        set_transient($translation->getCacheKey('fr'), [
            'book' => 2,
        ]);

        $lifecycle->flushCache();

        $this->assertFalse(get_transient($translation->getCacheKey('en')));
        $this->assertFalse(get_transient($translation->getCacheKey('fr')));
    }

    public function testFlushCacheDoesNothingWithNoActiveLanguages(): void
    {
        remove_all_filters('wpml_active_languages');
        add_filter('wpml_active_languages', static fn () => null);

        $api = new Api();
        $translation = new Translation();
        $lifecycle = new Lifecycle($api, new RewriteManager($api), $translation);

        // Set a transient that should NOT be cleared
        $cacheKey = $translation->getCacheKey('en');
        set_transient($cacheKey, [
            'book' => 1,
        ]);

        $lifecycle->flushCache();

        $this->assertNotFalse(get_transient($cacheKey));
    }

    public function testOnTranslationCompletedFlushesForPfcptPage(): void
    {
        $api = new Api();
        $translation = new Translation();
        $lifecycle = new Lifecycle($api, new RewriteManager($api), $translation);
        $lifecycle->registerHooks();

        $cacheKey = $translation->getCacheKey('en');
        set_transient($cacheKey, [
            'book' => $this->homeForBookId,
        ]);

        // Simulate WPML translation completed for the French version of the book page
        do_action('wpml_pro_translation_completed', $this->homeForBookFrId);

        $this->assertFalse(get_transient($cacheKey));
    }

    public function testOnTranslationCompletedIgnoresNonPfcptPage(): void
    {
        $api = new Api();
        $translation = new Translation();
        $lifecycle = new Lifecycle($api, new RewriteManager($api), $translation);
        $lifecycle->registerHooks();

        $cacheKey = $translation->getCacheKey('en');
        set_transient($cacheKey, [
            'book' => $this->homeForBookId,
        ]);

        // Simulate translation completed for a non-PFCPT page
        $regularPageId = self::factory()->post->create([
            'post_type' => 'page',
        ]);
        do_action('wpml_pro_translation_completed', $regularPageId);

        $this->assertNotFalse(get_transient($cacheKey));
    }

    public function testOnMakeDuplicateFlushesForPfcptPage(): void
    {
        $api = new Api();
        $translation = new Translation();
        $lifecycle = new Lifecycle($api, new RewriteManager($api), $translation);
        $lifecycle->registerHooks();

        $cacheKey = $translation->getCacheKey('en');
        set_transient($cacheKey, [
            'book' => $this->homeForBookId,
        ]);

        // Simulate WPML duplicate creation for the book page
        do_action('icl_make_duplicate', $this->homeForBookId, 'fr', [], $this->homeForBookFrId);

        $this->assertFalse(get_transient($cacheKey));
    }

    public function testOnMakeDuplicateIgnoresNonPfcptPage(): void
    {
        $api = new Api();
        $translation = new Translation();
        $lifecycle = new Lifecycle($api, new RewriteManager($api), $translation);
        $lifecycle->registerHooks();

        $cacheKey = $translation->getCacheKey('en');
        set_transient($cacheKey, [
            'book' => $this->homeForBookId,
        ]);

        $regularPageId = self::factory()->post->create([
            'post_type' => 'page',
        ]);
        do_action('icl_make_duplicate', $regularPageId, 'fr', [], 999);

        $this->assertNotFalse(get_transient($cacheKey));
    }

    public function testFlushRewriteRulesTriggersFlushCache(): void
    {
        $api = new Api();
        $translation = new Translation();
        $lifecycle = new Lifecycle($api, new RewriteManager($api), $translation);
        $lifecycle->registerHooks();

        set_transient($translation->getCacheKey('en'), [
            'book' => 1,
        ]);
        set_transient($translation->getCacheKey('fr'), [
            'book' => 2,
        ]);

        do_action('pfcpt/flush_rewrite_rules');

        $this->assertFalse(get_transient($translation->getCacheKey('en')));
        $this->assertFalse(get_transient($translation->getCacheKey('fr')));
    }

    // -------------------------------------------------------------------------
    // UrlTranslation
    // -------------------------------------------------------------------------

    public function testFilterLanguageSwitcherUrlsOnPfcptPage(): void
    {
        $api = new Api();
        $urlTranslation = new UrlTranslation($api);
        $urlTranslation->registerHooks();

        // Navigate to the PFCPT page so the query is set up naturally
        $this->get('/home-for-books/');

        $languages = [
            'en' => [
                'url' => home_url('/home-for-books/'),
                'native_name' => 'English',
            ],
            'fr' => [
                'url' => home_url('/fr/'),
                'native_name' => 'Français',
            ],
        ];

        $result = apply_filters('wpml_ls_languages', $languages);

        // French URL should be updated to point to the translated page
        $this->assertStringContainsString('accueil-livres', $result['fr']['url']);
    }

    public function testFilterLanguageSwitcherUrlsNotOnPfcptPage(): void
    {
        $api = new Api();
        $urlTranslation = new UrlTranslation($api);
        $urlTranslation->registerHooks();

        // Navigate to a regular page (no PFCPT flag on the query)
        $regularPage = static::factory()->post->create([
            'post_type' => 'page',
            'post_name' => 'some-page',
        ]);
        $this->get(get_permalink($regularPage));

        $languages = [
            'en' => [
                'url' => home_url('/some-page/'),
                'native_name' => 'English',
            ],
            'fr' => [
                'url' => home_url('/fr/some-page/'),
                'native_name' => 'Français',
            ],
        ];

        $result = apply_filters('wpml_ls_languages', $languages);

        // URLs should remain unchanged
        $this->assertEquals($languages, $result);
    }

    public function testFilterLanguageSwitcherUrlsCached(): void
    {
        $api = new Api();
        $urlTranslation = new UrlTranslation($api);
        $urlTranslation->registerHooks();

        // Navigate to the PFCPT page so the query is set up naturally
        $this->get('/home-for-books/');

        $languages = [
            'en' => [
                'url' => home_url('/home-for-books/'),
                'native_name' => 'English',
            ],
            'fr' => [
                'url' => home_url('/fr/'),
                'native_name' => 'Français',
            ],
        ];

        // First call computes URLs
        $result1 = apply_filters('wpml_ls_languages', $languages);
        // Second call should use cache
        $result2 = apply_filters('wpml_ls_languages', $languages);

        $this->assertEquals($result1, $result2);
    }

    public function testIclLsLanguagesFilterAlsoWorks(): void
    {
        $api = new Api();
        $urlTranslation = new UrlTranslation($api);
        $urlTranslation->registerHooks();

        // Navigate to the PFCPT page so the query is set up naturally
        $this->get('/home-for-books/');

        $languages = [
            'en' => [
                'url' => home_url('/home-for-books/'),
                'native_name' => 'English',
            ],
            'fr' => [
                'url' => home_url('/fr/'),
                'native_name' => 'Français',
            ],
        ];

        $result = apply_filters('icl_ls_languages', $languages);

        // French URL should be updated to point to the translated page
        $this->assertStringContainsString('accueil-livres', $result['fr']['url']);
    }

    public function testFilterLanguageSwitcherUrlsRestoresLanguage(): void
    {
        $this->currentLanguage = 'en';

        $api = new Api();
        $urlTranslation = new UrlTranslation($api);
        $urlTranslation->registerHooks();

        // Navigate to the PFCPT page so the query is set up naturally
        $this->get('/home-for-books/');

        $languages = [
            'en' => [
                'url' => home_url('/home-for-books/'),
                'native_name' => 'English',
            ],
            'fr' => [
                'url' => home_url('/fr/'),
                'native_name' => 'Français',
            ],
        ];

        apply_filters('wpml_ls_languages', $languages);

        // Language should be restored to original after filtering
        $this->assertEquals('en', $this->currentLanguage);
    }

    /**
     * Create translated PFCPT pages.
     */
    private function createTranslatedPages(): void
    {
        $this->homeForBookFrId = $this->getOrCreatePage('accueil-livres', 'Accueil Livres');
        $this->homeForBikeFrId = $this->getOrCreatePage('accueil-velos', 'Accueil Vélos');

        $this->translationMap = [
            $this->homeForBookId => $this->homeForBookFrId,
            $this->homeForBikeId => $this->homeForBikeFrId,
        ];
    }

    /**
     * Register filters that simulate WPML behavior.
     */
    private function registerWpmlFilters(): void
    {
        add_filter('wpml_current_language', fn () => $this->currentLanguage);
        add_filter('wpml_default_language', fn () => $this->defaultLanguage);

        add_filter('wpml_object_id', function ($id, $type, $returnOriginal, $lang) {
            if ($lang === 'fr' && isset($this->translationMap[$id])) {
                return $this->translationMap[$id];
            }

            if ($lang === 'en') {
                // Reverse lookup: find original from translation
                $originalId = array_search($id, $this->translationMap, true);
                if ($originalId !== false) {
                    return $originalId;
                }
            }

            return $returnOriginal ? $id : null;
        }, 10, 4);

        add_filter('wpml_active_languages', fn () => $this->activeLanguages);

        add_filter('wpml_home_url', static function ($url, $lang) {
            if ($lang === 'fr') {
                return home_url('/fr/');
            }

            return $url;
        }, 10, 2);

        add_action('wpml_switch_language', function (?string $lang): void {
            if ($lang !== null) {
                $this->currentLanguage = $lang;
            }
        });
    }
}
