<?php

namespace Tests\Fixtures\Weaver;

class ConstructorTarget
{
    public static int $constructed = 0;

    public function __construct(
        public string $value
    ) {
        ++self::$constructed;
    }

    #[ClassProvider]
    public function run(): string
    {
        return $this->value;
    }
}
