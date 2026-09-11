<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class StatefulTarget
{
    public function __construct(
        public int $value = 0
    ) {}

    #[SignatureProvider]
    public function increment(): int
    {
        return ++$this->value;
    }
}
