<?php

namespace Tests\Fixtures\Weaver;

class OrderingTarget
{
    public function __construct(
        private Log $log
    ) {}

    #[FirstProvider, SecondProvider]
    public function first(): string
    {
        $this->log->events[] = 'target:first';
        return 'first';
    }

    #[SecondProvider]
    public function second(): string
    {
        $this->log->events[] = 'target:second';
        return 'second';
    }
}
