<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class NestedAopDependency
{
    #[Record]
    public function run(): string
    {
        return 'nested';
    }
}
