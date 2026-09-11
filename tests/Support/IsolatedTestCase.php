<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Container\Container as IlluminateContainer;
use PHPUnit\Framework\TestCase;
use Sotvokun\Container\Container;

abstract class IsolatedTestCase extends TestCase
{
    protected TemporaryDirectory $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryDirectory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->temporaryDirectory->remove();
        parent::tearDown();
    }

    protected function container(): Container
    {
        return new Container();
    }

    protected function illuminateContainer(): IlluminateContainer
    {
        return new IlluminateContainer();
    }

    protected function fixtureDirectory(): string
    {
        return dirname(__DIR__) . '/Fixtures/Aop';
    }
}
