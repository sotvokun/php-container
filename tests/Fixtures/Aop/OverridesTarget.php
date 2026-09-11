<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class OverridesTarget
{
    public function __construct(
        public string $text,
        public int $zero,
        public bool $flag,
        public ?string $nullable,
        public object $object
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->text;
    }
}
