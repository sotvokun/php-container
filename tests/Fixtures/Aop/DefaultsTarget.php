<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class DefaultsTarget
{
    public function __construct(
        public string $text = 'default',
        public ?PlainDependency $dependency = null,
        public ?string $nullable = null
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->text;
    }
}
