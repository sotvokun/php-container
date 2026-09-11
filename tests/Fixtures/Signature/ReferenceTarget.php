<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class ReferenceTarget
{
    private string $value = 'initial';

    #[SignatureProvider]
    public function &reference(): string
    {
        return $this->value;
    }
}
