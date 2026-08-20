<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Lifecycle\Migrator;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class MigratorTest extends TestCase
{
    private Migrator $migrator;

    private RewriteManager $rewriteManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rewriteManager = new RewriteManager(new Api());
        $this->migrator = new Migrator($this->rewriteManager);
    }

    public function testFreshInstallSetsDbVersionWithoutTouchingUseSlug(): void
    {
        delete_option('pfcpt_db_version');
        delete_option('pages_for_custom_post_type');
        delete_option('page_for_book_use_slug');

        $this->migrator->migrate();

        $this->assertNotEmpty(get_option('pfcpt_db_version'));
        $this->assertFalse(get_option('page_for_book_use_slug'));
    }

    public function testUpgradeFromPreUseSlugEnablesUseSlugForAssignedCPTs(): void
    {
        $this->createFixtures();
        delete_option('pfcpt_db_version');
        delete_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug');
        delete_option('page_for_' . self::BIKE_POST_TYPE . '_use_slug');

        $this->migrator->migrate();

        $this->assertEquals('1', get_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug'));
        $this->assertEquals('1', get_option('page_for_' . self::BIKE_POST_TYPE . '_use_slug'));
        $this->assertNotEmpty(get_option('pfcpt_db_version'));
    }

    public function testMigrationDoesNotOverwriteExplicitlySetUseSlug(): void
    {
        $this->createFixtures();
        delete_option('pfcpt_db_version');
        update_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug', '0');

        $this->migrator->migrate();

        $this->assertEquals('0', get_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->createFixtures();
        update_option('pfcpt_db_version', '0.6.0');
        delete_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug');

        $this->migrator->migrate();

        $this->assertFalse(get_option('page_for_' . self::BOOK_POST_TYPE . '_use_slug'));
    }

    public function testUpgradeFlushesRewriteRulesAndPageSlugCaches(): void
    {
        $this->createFixtures();
        update_option('pfcpt_db_version', '0.6.0');
        update_option('rewrite_rules', ['stale' => 'rules']);
        set_transient($this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE), 'stale-slug', 0);

        $this->migrator->migrate();

        // Assert on our own marker rather than on the whole array: plugins
        // filter option_rewrite_rules to add theirs (Yoast and its sitemap
        // rules, for one).
        $this->assertArrayNotHasKey('stale', $this->storedRewriteRules());
        $this->assertFalse(get_transient($this->rewriteManager->getPageSlugCacheKey(self::BOOK_POST_TYPE)));
    }

    public function testUpgradeFiresTheFlushActionForEveryAssignedPostType(): void
    {
        $this->createFixtures();
        update_option('pfcpt_db_version', '0.6.0');

        $fired = [];
        add_action('pfcpt/flush_rewrite_rules', static function (string $postType) use (&$fired): void {
            $fired[] = $postType;
        });

        $this->migrator->migrate();

        sort($fired);

        $this->assertSame([self::BIKE_POST_TYPE, self::BOOK_POST_TYPE], $fired);
    }

    public function testUpToDateInstallDoesNotFlush(): void
    {
        $this->createFixtures();

        // Bring the option to the current version, whatever it is.
        $this->migrator->migrate();

        update_option('rewrite_rules', ['fresh' => 'rules']);

        $this->migrator->migrate();

        $this->assertArrayHasKey('fresh', $this->storedRewriteRules());
    }

    public function testFreshInstallDoesNotFlush(): void
    {
        delete_option('pfcpt_db_version');
        update_option('rewrite_rules', ['fresh' => 'rules']);

        $this->migrator->migrate();

        $this->assertArrayHasKey('fresh', $this->storedRewriteRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRewriteRules(): array
    {
        $stored = get_option('rewrite_rules');

        return \is_array($stored) ? $stored : [];
    }
}
