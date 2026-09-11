<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

readonly class ReadonlyTarget
{
    public function __construct(
        public string $value = 'ready'
    ) {}

    #[SignatureProvider]
    public function read(): string
    {
        return $this->value;
    }
}
