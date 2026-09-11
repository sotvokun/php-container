<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

final class AlternateScenarioPortImplementation implements ScenarioPort
{
    public function name(): string
    {
        return 'alternate';
    }
}
