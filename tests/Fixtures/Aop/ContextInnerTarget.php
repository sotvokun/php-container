<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class ContextInnerTarget
{
    public function __construct(
        public ScenarioPort $port
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->port->name();
    }
}
