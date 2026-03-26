<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Admin\Admin;
use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Plugin;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for admin functionality.
 *
 * Tests admin UI features like post states and settings.
 */
class AdminTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();

        $container = Plugin::getInstance()->getContainer();
        $this->admin = $container->get(Admin::class);
    }

    public function testPostStateIsDisplayedForPfcptPage(): void
    {
        $page = get_post($this->homeForBookId);

        $postStates = $this->admin->displayPostStates([], $page);

        $this->assertArrayHasKey('page_for_' . self::BOOK_POST_TYPE, $postStates);
    }

    public function testPostStateIncludesPostTypeName(): void
    {
        $page = get_post($this->homeForBookId);

        $postStates = $this->admin->displayPostStates([], $page);

        $this->assertStringContainsString('Books', $postStates['page_for_' . self::BOOK_POST_TYPE]);
    }

    public function testPostStateNotAddedForRegularPage(): void
    {
        $page = get_post($this->staticFrontPageId);

        $postStates = $this->admin->displayPostStates([], $page);

        $this->assertEmpty($postStates);
    }

    public function testPostStateNotAddedForNonPagePostType(): void
    {
        $book = get_post($this->bookIds[0]);

        $postStates = $this->admin->displayPostStates([], $book);

        $this->assertEmpty($postStates);
    }

    public function testExistingPostStatesArePreserved(): void
    {
        $page = get_post($this->homeForBookId);

        $existingStates = ['existing_state' => 'Existing State'];
        $postStates = $this->admin->displayPostStates($existingStates, $page);

        $this->assertArrayHasKey('existing_state', $postStates);
        $this->assertArrayHasKey('page_for_' . self::BOOK_POST_TYPE, $postStates);
    }

    public function testDifferentPostTypeHasDifferentPostState(): void
    {
        $bikePage = get_post($this->homeForBikeId);

        $postStates = $this->admin->displayPostStates([], $bikePage);

        $this->assertArrayHasKey('page_for_' . self::BIKE_POST_TYPE, $postStates);
        $this->assertArrayNotHasKey('page_for_' . self::BOOK_POST_TYPE, $postStates);
    }

    public function testOptionNameFormat(): void
    {
        $api = Plugin::getInstance()->getApi();

        $this->assertEquals('page_for_' . self::BOOK_POST_TYPE, $api->getOptionName(self::BOOK_POST_TYPE));
        $this->assertEquals('page_for_' . self::BIKE_POST_TYPE, $api->getOptionName(self::BIKE_POST_TYPE));
    }

    public function testPageIdsOptionContainsCorrectMapping(): void
    {
        $pageIds = get_option(Api::OPTION_PAGE_IDS);

        $this->assertIsArray($pageIds);
        $this->assertEquals($this->homeForBookId, $pageIds[self::BOOK_POST_TYPE]);
        $this->assertEquals($this->homeForBikeId, $pageIds[self::BIKE_POST_TYPE]);
    }
}
