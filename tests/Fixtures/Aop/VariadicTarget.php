<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class VariadicTarget
{
    /** @var list<ScenarioPort> */
    public array $ports;

    public function __construct(ScenarioPort ...$ports)
    {
        $this->ports = $ports;
    }

    /**
     * @return list<string>
     */
    #[Record]
    public function portNames(): array
    {
        return array_map(static fn(ScenarioPort $port): string => $port->name(), $this->ports);
    }
}
