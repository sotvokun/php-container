<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class AopWithConstructor
{
    public static int $constructed = 0;

    public function __construct(
        public PlainDependency $dependency
    ) {
        ++self::$constructed;
    }

    #[Record]
    public function run(): string
    {
        return $this->dependency->value();
    }
}
