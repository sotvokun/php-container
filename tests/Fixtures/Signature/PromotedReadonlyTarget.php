<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class PromotedReadonlyTarget
{
    public function __construct(
        public readonly string $value = 'ready'
    ) {}

    #[SignatureProvider]
    public function read(): string
    {
        return $this->value;
    }
}
