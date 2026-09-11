<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class FinalConstructorTarget
{
    final public function __construct(
        public readonly string $value = 'ready'
    ) {}

    #[SignatureProvider]
    public function read(): string
    {
        return $this->value;
    }
}
