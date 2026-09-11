<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class AopComplexDependency
{
    public function __construct(
        public ScenarioPort $port,
        public NestedAopDependency $nested
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->port->name() . ':' . $this->nested->run();
    }
}
