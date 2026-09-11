<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class CycleA
{
    public function __construct(
        public CycleB $b
    ) {}

    #[Record]
    public function run(): string
    {
        return 'a';
    }
}
