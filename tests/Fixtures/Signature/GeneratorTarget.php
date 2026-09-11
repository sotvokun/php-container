<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class GeneratorTarget
{
    /** @var list<string> */
    public array $events = [];

    #[SignatureProvider]
    public function stream(bool $fail = false): \Generator
    {
        $this->events[] = 'target:create';
        try {
            yield 'first';
            if ($fail) {
                throw new \RuntimeException('generator failed');
            }
            yield 'second';
        } finally {
            $this->events[] = 'target:finally';
        }
    }
}
