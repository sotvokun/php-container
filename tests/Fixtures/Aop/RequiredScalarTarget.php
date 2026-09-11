<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class RequiredScalarTarget
{
    public function __construct(
        public string $value
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->value;
    }
}
