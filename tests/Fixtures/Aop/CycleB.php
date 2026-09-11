<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class CycleB
{
    public function __construct(
        public CycleA $a
    ) {}

    #[Record]
    public function run(): string
    {
        return 'b';
    }
}
