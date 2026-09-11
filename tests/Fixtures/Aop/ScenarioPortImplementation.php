<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

final class ScenarioPortImplementation implements ScenarioPort
{
    public function name(): string
    {
        return 'port';
    }
}
