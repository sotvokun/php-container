<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class AopNoConstructor
{
    #[Record]
    public function run(): string
    {
        return 'run';
    }
}
