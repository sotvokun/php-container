<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Illuminate\Contracts\Container\SelfBuilding;
use RuntimeException;

class SelfBuildingThrows implements SelfBuilding
{
    public static function newInstance(): self
    {
        throw new RuntimeException('self-building');
    }

    #[Record]
    public function run(): string
    {
        return 'never';
    }
}
