<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class NeverTarget
{
    #[SignatureProvider]
    public function neverReturns(): never
    {
        throw new \RuntimeException('never target');
    }
}
