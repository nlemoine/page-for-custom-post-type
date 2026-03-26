<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Integration;

use n5s\PageForCustomPostType\Admin\SettingsValidator;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SettingsValidatorTest extends TestCase
{
    private SettingsValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();

        // add_settings_error() is admin-only
        if (!\function_exists('add_settings_error')) {
            require_once \ABSPATH . 'wp-admin/includes/template.php';
        }

        $this->validator = new SettingsValidator();
    }

    public function testValidateWithEmptyValueReturnsEmpty(): void
    {
        $result = $this->validator->validate('', 'page_for_' . self::BOOK_POST_TYPE, '');

        $this->assertSame('', $result);
    }

    public function testValidateWithZeroReturnsZero(): void
    {
        $result = $this->validator->validate(0, 'page_for_' . self::BOOK_POST_TYPE, 0);

        $this->assertSame(0, $result);
    }

    #[DataProvider('invalidNonNumericValues')]
    public function testValidateWithNonNumericValueAddsError(mixed $value): void
    {
        $this->validator->validate($value, 'page_for_' . self::BOOK_POST_TYPE, '');

        $errors = \get_settings_errors('page_for_' . self::BOOK_POST_TYPE);
        $this->assertNotEmpty($errors);
    }

    #[DataProvider('invalidNonNumericValues')]
    public function testValidateWithNonNumericValueReturnsFallback(mixed $value): void
    {
        $pageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
        ]);
        \update_option('page_for_' . self::BOOK_POST_TYPE, $pageId);

        $result = $this->validator->validate($value, 'page_for_' . self::BOOK_POST_TYPE, '');

        $this->assertSame($pageId, $result);
    }

    #[DataProvider('invalidNonNumericValues')]
    public function testValidateWithNonNumericValueReturnsNullWhenNoFallback(mixed $value): void
    {
        \delete_option('page_for_' . self::BOOK_POST_TYPE);

        $result = $this->validator->validate($value, 'page_for_' . self::BOOK_POST_TYPE, '');

        $this->assertNull($result);
    }

    public static function invalidNonNumericValues(): iterable
    {
        yield 'string' => ['abc'];
        yield 'special chars' => ['!@#'];
        yield 'mixed' => ['12abc'];
    }

    public function testValidateWithUnpublishedPageAddsError(): void
    {
        $draftPageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'draft',
        ]);

        $this->validator->validate($draftPageId, 'page_for_' . self::BOOK_POST_TYPE, '');

        $errors = \get_settings_errors('page_for_' . self::BOOK_POST_TYPE);
        $this->assertNotEmpty($errors);
    }

    public function testValidateWithUnpublishedPageReturnsFallback(): void
    {
        $publishedPageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
        ]);
        \update_option('page_for_' . self::BOOK_POST_TYPE, $publishedPageId);

        $draftPageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'draft',
        ]);

        $result = $this->validator->validate($draftPageId, 'page_for_' . self::BOOK_POST_TYPE, '');

        $this->assertSame($publishedPageId, $result);
    }

    public function testValidateWithDuplicatePageIdAddsError(): void
    {
        $pageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
        ]);

        $_POST['page_for_' . self::BIKE_POST_TYPE] = $pageId;

        try {
            $this->validator->validate($pageId, 'page_for_' . self::BOOK_POST_TYPE, '');

            $errors = \get_settings_errors('page_for_' . self::BOOK_POST_TYPE);
            $this->assertNotEmpty($errors);
        } finally {
            unset($_POST['page_for_' . self::BIKE_POST_TYPE]);
        }
    }

    public function testValidateWithDuplicatePageIdReturnsFallback(): void
    {
        $existingPageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
        ]);
        \update_option('page_for_' . self::BOOK_POST_TYPE, $existingPageId);

        $duplicatePageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
        ]);

        $_POST['page_for_' . self::BIKE_POST_TYPE] = $duplicatePageId;

        try {
            $result = $this->validator->validate($duplicatePageId, 'page_for_' . self::BOOK_POST_TYPE, '');

            $this->assertSame($existingPageId, $result);
        } finally {
            unset($_POST['page_for_' . self::BIKE_POST_TYPE]);
        }
    }

    public function testValidateWithValidPageIdReturnsAbsint(): void
    {
        $pageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
        ]);

        $result = $this->validator->validate($pageId, 'page_for_' . self::BOOK_POST_TYPE, '');

        $this->assertSame(\absint($pageId), $result);
    }

    public function testValidateWithUnknownPostTypeReturnsValue(): void
    {
        $pageId = static::factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
        ]);

        $result = $this->validator->validate($pageId, 'page_for_unknowntype', '');

        $this->assertSame($pageId, $result);
    }
}
