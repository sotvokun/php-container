<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class BoundAopTarget implements ScenarioPort
{
    public function name(): string
    {
        return 'bound';
    }

    #[Record]
    public function run(): string
    {
        return 'bound';
    }
}
