<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class InternalCallsTarget
{
    /** @var list<string> */
    public array $events = [];

    #[SignatureProvider]
    public function outer(): string
    {
        $this->events[] = 'target:outer';
        return $this->inner();
    }

    #[SignatureProvider]
    public function inner(int $depth = 0): string
    {
        $this->events[] = 'target:inner:' . $depth;
        return $depth === 0 ? $this->inner(1) : 'done';
    }
}
