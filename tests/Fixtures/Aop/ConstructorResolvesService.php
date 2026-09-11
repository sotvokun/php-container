<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Illuminate\Container\Container;

class ConstructorResolvesService
{
    public PlainDependency $dependency;
    public ScenarioPort $port;
    public string|null $constructingContext;

    public function __construct(Container $container)
    {
        $this->constructingContext = $container->currentlyResolving();
        $this->dependency = $container->make(PlainDependency::class);
        $this->port = $container->make(ScenarioPort::class);
    }

    #[Record]
    public function run(): string
    {
        return $this->dependency->value() . ':' . $this->port->name();
    }
}
