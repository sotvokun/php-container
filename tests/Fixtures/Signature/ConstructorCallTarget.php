<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class ConstructorCallTarget
{
    /** @var list<string> */
    public array $events = [];
    public string $constructedValue;

    public function __construct()
    {
        $this->constructedValue = $this->marked();
    }

    #[SignatureProvider]
    public function marked(): string
    {
        $this->events[] = 'target:marked';
        return 'constructed';
    }
}
