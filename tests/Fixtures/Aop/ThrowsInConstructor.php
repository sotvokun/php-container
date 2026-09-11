<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class ThrowsInConstructor
{
    public function __construct()
    {
        throw new \RuntimeException('constructor');
    }

    #[Record]
    public function run(): string
    {
        return 'never';
    }
}
