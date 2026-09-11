<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class SelfBuildingNeedsDependency implements \Illuminate\Contracts\Container\SelfBuilding
{
    public static function newInstance(PlainDependency $dependency): self
    {
        return new self($dependency);
    }

    public function __construct(
        public PlainDependency $dependency
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->dependency->value();
    }
}
