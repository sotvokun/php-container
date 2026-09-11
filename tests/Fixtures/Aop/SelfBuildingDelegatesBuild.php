<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Illuminate\Contracts\Container\SelfBuilding;
use Sotvokun\Container\Container;

class SelfBuildingDelegatesBuild implements SelfBuilding
{
    public static function newInstance(Container $container): self
    {
        return $container->build(self::class);
    }

    #[Record]
    public function run(): string
    {
        return 'delegated';
    }
}
