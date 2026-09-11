<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class ContextOuterTarget
{
    public function __construct(
        public ScenarioPort $port,
        public ContextInnerTarget $inner
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->port->name() . ':' . $this->inner->run();
    }
}
