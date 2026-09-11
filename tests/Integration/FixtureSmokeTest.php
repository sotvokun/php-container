<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixtures\Aop\GreetingService;
use Tests\Support\IsolatedTestCase;

#[CoversNothing]
final class FixtureSmokeTest extends IsolatedTestCase
{
    public function testT02T03T04FixturesCanBeWovenFromRealFilesInAnIsolatedDirectory(): void
    {
        $container = $this->container();
        $container->withAop([$this->fixtureDirectory()], $this->temporaryDirectory->generatedClasses());

        $service = $container->make(GreetingService::class);

        self::assertInstanceOf(GreetingService::class, $service);
        self::assertSame('hello Ada', $service->greet('Ada'));
        self::assertSame('plain', $service->plain());
    }
}
