<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class ContextTarget
{
    public function __construct(
        public ScenarioPort $port,
        public string $label
    ) {}

    #[Record]
    public function run(): string
    {
        return $this->label . ':' . $this->port->name();
    }
}
