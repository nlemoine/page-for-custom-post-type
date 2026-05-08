<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Lifecycle\Migrator;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class MigratorTest extends TestCase
{
    private Migrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrator = new Migrator();
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
}
