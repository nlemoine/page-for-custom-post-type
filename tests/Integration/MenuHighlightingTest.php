<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Frontend\QueryFilter;
use n5s\PageForCustomPostType\Plugin;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class MenuHighlightingTest extends TestCase
{
    private QueryFilter $queryFilter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();

        $container = Plugin::getInstance()->getContainer();
        $this->queryFilter = $container->get(QueryFilter::class);
    }

    private function createMenuItem(array $props = []): \stdClass
    {
        $item = new \stdClass();
        $item->type = $props['type'] ?? 'post_type';
        $item->object_id = $props['object_id'] ?? 0;
        $item->classes = $props['classes'] ?? [];
        $item->current_item_ancestor = $props['current_item_ancestor'] ?? false;

        return $item;
    }

    public function testAncestorSetOnSingleCptPost(): void
    {
        $this->get(get_permalink($this->bookIds[0]));

        $menuItem = $this->createMenuItem([
            'type' => 'post_type',
            'object_id' => $this->homeForBookId,
            'classes' => [],
        ]);

        $result = $this->queryFilter->setCurrentAncestor([$menuItem], new \stdClass());

        $this->assertContains('current-menu-ancestor', $result[0]->classes);
        $this->assertTrue($result[0]->current_item_ancestor);
    }

    public function testAncestorNotSetOnNonSingularQuery(): void
    {
        $this->get($this->getBookHomeUrl());

        $menuItem = $this->createMenuItem([
            'type' => 'post_type',
            'object_id' => $this->homeForBookId,
            'classes' => [],
        ]);

        $result = $this->queryFilter->setCurrentAncestor([$menuItem], new \stdClass());

        $this->assertNotContains('current-menu-ancestor', $result[0]->classes);
    }

    public function testAncestorIgnoresNonPostTypeMenuItems(): void
    {
        $this->get(get_permalink($this->bookIds[0]));

        $menuItem = $this->createMenuItem([
            'type' => 'custom',
            'object_id' => $this->homeForBookId,
            'classes' => [],
        ]);

        $result = $this->queryFilter->setCurrentAncestor([$menuItem], new \stdClass());

        $this->assertNotContains('current-menu-ancestor', $result[0]->classes);
        $this->assertFalse($result[0]->current_item_ancestor);
    }

    public function testAncestorIgnoresMenuItemsForNonPfcptPages(): void
    {
        $this->get(get_permalink($this->bookIds[0]));

        $menuItem = $this->createMenuItem([
            'type' => 'post_type',
            'object_id' => $this->staticFrontPageId,
            'classes' => [],
        ]);

        $result = $this->queryFilter->setCurrentAncestor([$menuItem], new \stdClass());

        $this->assertNotContains('current-menu-ancestor', $result[0]->classes);
        $this->assertFalse($result[0]->current_item_ancestor);
    }

    public function testAncestorIgnoresMismatchedPostType(): void
    {
        $this->get(get_permalink($this->bikeIds[0]));

        $menuItem = $this->createMenuItem([
            'type' => 'post_type',
            'object_id' => $this->homeForBookId,
            'classes' => [],
        ]);

        $result = $this->queryFilter->setCurrentAncestor([$menuItem], new \stdClass());

        $this->assertNotContains('current-menu-ancestor', $result[0]->classes);
        $this->assertFalse($result[0]->current_item_ancestor);
    }

    public function testAncestorPreservesExistingClasses(): void
    {
        $this->get(get_permalink($this->bookIds[0]));

        $menuItem = $this->createMenuItem([
            'type' => 'post_type',
            'object_id' => $this->homeForBookId,
            'classes' => ['menu-item', 'existing-class'],
        ]);

        $result = $this->queryFilter->setCurrentAncestor([$menuItem], new \stdClass());

        $this->assertContains('existing-class', $result[0]->classes);
        $this->assertContains('menu-item', $result[0]->classes);
        $this->assertContains('current-menu-ancestor', $result[0]->classes);
        $this->assertTrue($result[0]->current_item_ancestor);
    }

    public function testAncestorIgnoresMenuItemWithZeroObjectId(): void
    {
        $this->get(get_permalink($this->bookIds[0]));

        $menuItem = $this->createMenuItem([
            'type' => 'post_type',
            'object_id' => 0,
            'classes' => [],
        ]);

        $result = $this->queryFilter->setCurrentAncestor([$menuItem], new \stdClass());

        $this->assertNotContains('current-menu-ancestor', $result[0]->classes);
        $this->assertFalse($result[0]->current_item_ancestor);
    }
}
