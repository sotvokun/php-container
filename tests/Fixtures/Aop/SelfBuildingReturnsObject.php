<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class SelfBuildingReturnsObject implements \Illuminate\Contracts\Container\SelfBuilding
{
    public static function newInstance(): self
    {
        return new self();
    }

    #[Record]
    public function run(): string
    {
        return 'self';
    }
}
