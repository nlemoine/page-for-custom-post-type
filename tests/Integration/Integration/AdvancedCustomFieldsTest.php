<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration\Integration;

use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;
use PHPUnit\Framework\Attributes\RequiresFunction;

/**
 * Integration tests for the Advanced Custom Fields integration.
 *
 * Exercises the `acf/location/rule_values/type=page_type` and
 * `acf/location/match_rule/type=page_type` filters that expose
 * `<cpt>_page` values on the Page Type location rule.
 */
#[RequiresFunction('acf_get_location_type')]
class AdvancedCustomFieldsTest extends TestCase
{
    private const RULE_TYPE = 'page_type';

    protected function setUp(): void
    {
        if (!\function_exists('acf_get_location_type')) {
            $this->markTestSkipped('Advanced Custom Fields is not installed.');
        }

        parent::setUp();
        $this->createFixtures();
        $this->configureStaticFrontPage();
    }

    public function testPageTypeValuesIncludeCustomPostTypePages(): void
    {
        $values = $this->applyValuesFilter();

        $this->assertArrayHasKey('book_page', $values);
        $this->assertArrayHasKey('bike_page', $values);
    }

    public function testPageTypeValuesPreserveCoreOptions(): void
    {
        $values = $this->applyValuesFilter();

        foreach (['front_page', 'posts_page', 'top_level', 'parent', 'child'] as $key) {
            $this->assertArrayHasKey($key, $values);
        }
    }

    public function testMatchRuleReturnsTrueForBoundPage(): void
    {
        $matched = $this->applyMatchFilter('==', 'book_page', $this->homeForBookId);

        $this->assertTrue($matched);
    }

    public function testMatchRuleReturnsFalseForUnrelatedPage(): void
    {
        $matched = $this->applyMatchFilter('==', 'book_page', $this->staticFrontPageId);

        $this->assertFalse($matched);
    }

    public function testMatchRuleInvertsForNotEqualsOperator(): void
    {
        $matched = $this->applyMatchFilter('!=', 'book_page', $this->homeForBookId);

        $this->assertFalse($matched);
    }

    public function testMatchRuleShortCircuitsWhenCoreAlreadyMatched(): void
    {
        $matched = acf_match_location_rule(
            ['param' => self::RULE_TYPE, 'operator' => '==', 'value' => 'front_page'],
            ['post_id' => $this->staticFrontPageId],
            []
        );

        $this->assertTrue($matched);
    }

    public function testMatchRuleReturnsFalseWhenPostIdMissing(): void
    {
        $matched = acf_match_location_rule(
            ['param' => self::RULE_TYPE, 'operator' => '==', 'value' => 'book_page'],
            [],
            []
        );

        $this->assertFalse($matched);
    }

    /**
     * @return array<string, string>
     */
    private function applyValuesFilter(): array
    {
        return acf_get_location_rule_values([
            'param' => self::RULE_TYPE,
            'operator' => '==',
            'value' => '',
        ]);
    }

    private function applyMatchFilter(string $operator, string $value, int $postId): bool
    {
        return acf_match_location_rule(
            ['param' => self::RULE_TYPE, 'operator' => $operator, 'value' => $value],
            ['post_id' => $postId],
            []
        );
    }
}
