<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Unit\Core;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the RewriteManager class.
 *
 * These tests focus on pure logic methods that don't require WordPress.
 */
class RewriteManagerTest extends TestCase
{
    private RewriteManager $rewriteManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rewriteManager = new RewriteManager(new Api());
    }

    #[DataProvider('pageExclusionProvider')]
    public function testRestorePageExclusion(string $regex, string $expected): void
    {
        $restored = $this->rewriteManager->restorePageExclusion([$regex => 'index.php?attachment=$matches[1]']);

        $this->assertSame([$expected => 'index.php?attachment=$matches[1]'], $restored);
    }

    public static function pageExclusionProvider(): iterable
    {
        yield 'stripped parentheses' => [
            'books/?!page[^/]+/([^/]+)/?$',
            'books/(?!page)[^/]+/([^/]+)/?$',
        ];

        yield 'stripped parentheses, hierarchical' => [
            'books/?!page.+?/attachment/([^/]+)/?$',
            'books/(?!page).+?/attachment/([^/]+)/?$',
        ];

        yield 'intact exclusion is left alone' => [
            'books/(?!page)([^/]+)(?:/([0-9]+))?/?$',
            'books/(?!page)([^/]+)(?:/([0-9]+))?/?$',
        ];

        yield 'unrelated rule is left alone' => [
            '(.?.+?)/page/?([0-9]{1,})/?$',
            '(.?.+?)/page/?([0-9]{1,})/?$',
        ];

        yield 'escaped question mark is left alone' => [
            'wiki/(.+?)\?!page/([0-9]+)$',
            'wiki/(.+?)\?!page/([0-9]+)$',
        ];
    }

    /**
     * @param array<string, string> $rules
     */
    #[DataProvider('collidingRulesProvider')]
    public function testRestorePageExclusionNeverDropsACollidingRule(array $rules): void
    {
        $restored = $this->rewriteManager->restorePageExclusion($rules);

        $this->assertSame($rules, $restored);
    }

    public static function collidingRulesProvider(): iterable
    {
        // A site that worked around the stripped parentheses on its own ends up
        // with both forms. Restoring one onto the other would drop a rule.
        $mangled = 'books/?!page[^/]+/([^/]+)/?$';
        $intact = 'books/(?!page)[^/]+/([^/]+)/?$';

        yield 'mangled first' => [
            [
                $mangled => 'index.php?attachment=$matches[1]',
                $intact => 'index.php?attachment=$matches[1]&custom=1',
            ],
        ];

        yield 'intact first' => [
            [
                $intact => 'index.php?attachment=$matches[1]&custom=1',
                $mangled => 'index.php?attachment=$matches[1]',
            ],
        ];
    }

    public function testRestorePageExclusionKeepsRuleOrder(): void
    {
        $rules = [
            'books/?!page[^/]+/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
            'books/(?!page)([^/]+)/?$' => 'index.php?book=$matches[1]',
        ];

        $this->assertSame([
            'books/(?!page)[^/]+/([^/]+)/?$',
            'books/(?!page)([^/]+)/?$',
        ], array_keys($this->rewriteManager->restorePageExclusion($rules)));
    }
}
