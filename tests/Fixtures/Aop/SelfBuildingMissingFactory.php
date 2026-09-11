<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Illuminate\Contracts\Container\SelfBuilding;

class SelfBuildingMissingFactory implements SelfBuilding
{
    #[Record]
    public function run(): string
    {
        return 'never';
    }
}
