<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class CycleSelf
{
    public function __construct(
        public CycleSelf $self
    ) {}

    #[Record]
    public function run(): string
    {
        return 'cycle';
    }
}
