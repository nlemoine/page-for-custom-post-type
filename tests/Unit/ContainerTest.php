<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Tests\Unit;

use InvalidArgumentException;
use n5s\PageForCustomPostType\Container;
use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Integration\AdvancedCustomFields\AdvancedCustomFields;
use n5s\PageForCustomPostType\Tests\Fixtures\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testGetReturnsCorrectTypeForApi(): void
    {
        $service = $this->container->get(Api::class);

        $this->assertInstanceOf(Api::class, $service);
    }

    public function testGetReturnsCorrectTypeForAdvancedCustomFieldsIntegration(): void
    {
        $service = $this->container->get(AdvancedCustomFields::class);

        $this->assertInstanceOf(AdvancedCustomFields::class, $service);
    }

    public function testGetReturnsSameInstanceOnRepeatedCalls(): void
    {
        $first = $this->container->get(Api::class);
        $second = $this->container->get(Api::class);

        $this->assertSame($first, $second);
    }

    public function testHasReturnsTrueForKnownServices(): void
    {
        $this->assertTrue($this->container->has(Api::class));
        $this->assertTrue($this->container->has(RewriteManager::class));
        $this->assertTrue($this->container->has(AdvancedCustomFields::class));
    }

    public function testHasReturnsFalseForUnknownServices(): void
    {
        $this->assertFalse($this->container->has('NonExistent\\Service'));
        $this->assertFalse($this->container->has(\stdClass::class));
    }

    public function testGetThrowsInvalidArgumentExceptionForUnknownService(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->container->get('NonExistent\\Service');
    }
}
