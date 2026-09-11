<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class ContextInterceptorTarget
{
    public function __construct(
        public ScenarioPort $port
    ) {}

    #[ContextInterceptorProvider]
    public function run(): string
    {
        return $this->port->name();
    }
}
