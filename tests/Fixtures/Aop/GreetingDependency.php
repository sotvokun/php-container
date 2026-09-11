<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

final class GreetingDependency
{
    public function prefix(): string
    {
        return 'hello';
    }
}
