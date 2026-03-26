<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

/**
 * Integration tests for template hierarchy modification.
 *
 * Tests that the template hierarchy is properly modified for PFCPT pages.
 */
class TemplateHierarchyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    public function testHomeTemplateHierarchyIncludesPostTypeSpecificTemplate(): void
    {
        $this->get($this->getBookHomeUrl());

        // Apply the home_template_hierarchy filter with default templates
        $templates = \apply_filters('home_template_hierarchy', ['home.php', 'index.php']);

        $this->assertContains('home-' . self::BOOK_POST_TYPE . '.php', $templates);
    }

    public function testPostTypeSpecificTemplateIsFirstInHierarchy(): void
    {
        $this->get($this->getBookHomeUrl());

        $templates = \apply_filters('home_template_hierarchy', ['home.php', 'index.php']);

        $this->assertEquals('home-' . self::BOOK_POST_TYPE . '.php', $templates[0]);
    }

    public function testTemplateHierarchyStillIncludesDefaultHomeTemplate(): void
    {
        $this->get($this->getBookHomeUrl());

        $templates = \apply_filters('home_template_hierarchy', ['home.php', 'index.php']);

        $this->assertContains('home.php', $templates);
    }

    public function testTemplateHierarchyStillIncludesIndexTemplate(): void
    {
        $this->get($this->getBookHomeUrl());

        $templates = \apply_filters('home_template_hierarchy', ['home.php', 'index.php']);

        $this->assertContains('index.php', $templates);
    }

    public function testDifferentPostTypeHasDifferentTemplate(): void
    {
        $this->get($this->getBikeHomeUrl());

        $templates = \apply_filters('home_template_hierarchy', ['home.php', 'index.php']);

        $this->assertEquals('home-' . self::BIKE_POST_TYPE . '.php', $templates[0]);
        $this->assertNotContains('home-' . self::BOOK_POST_TYPE . '.php', $templates);
    }

    public function testFullHierarchyStructure(): void
    {
        $this->get($this->getBookHomeUrl());

        $templates = \apply_filters('home_template_hierarchy', ['home.php', 'index.php']);

        $expected = [
            'home-' . self::BOOK_POST_TYPE . '.php',
            'home.php',
            'index.php',
        ];

        $this->assertEquals($expected, $templates);
    }

    public function testRegularPageDoesNotModifyHomeHierarchy(): void
    {
        $this->get(\get_permalink($this->staticFrontPageId));

        $templates = \apply_filters('home_template_hierarchy', ['home.php', 'index.php']);

        // Should not include any post type specific template
        $this->assertNotContains('home-' . self::BOOK_POST_TYPE . '.php', $templates);
        $this->assertNotContains('home-' . self::BIKE_POST_TYPE . '.php', $templates);
        $this->assertEquals(['home.php', 'index.php'], $templates);
    }

    public function testFrontpageHierarchyIsEmptyOnPfcptPage(): void
    {
        $this->get($this->getBookHomeUrl());

        // The plugin returns an empty array for frontpage_template_hierarchy
        // to prevent WordPress from using front-page.php
        $templates = \apply_filters('frontpage_template_hierarchy', ['front-page.php']);

        $this->assertEmpty($templates);
    }

    public function testBlockThemeHierarchyWithoutExtension(): void
    {
        $this->get($this->getBookHomeUrl());

        // Block themes pass templates without .php extension
        $templates = \apply_filters('home_template_hierarchy', ['home', 'index']);

        $expected = [
            'home-' . self::BOOK_POST_TYPE,
            'home',
            'index',
        ];

        $this->assertEquals($expected, $templates);
    }
}
