<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class GreetingService
{
    public function __construct(
        public readonly GreetingDependency $dependency
    ) {}

    #[Record]
    public function greet(string $name): string
    {
        return $this->dependency->prefix() . ' ' . $name;
    }

    public function plain(): string
    {
        return 'plain';
    }
}
