<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class DefaultObjectTarget
{
    public function __construct(
        public PlainDependency $dependency = new PlainDependency()
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->dependency->value();
    }
}
