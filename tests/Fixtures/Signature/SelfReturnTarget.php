<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class SelfReturnTarget
{
    #[SignatureProvider]
    public function fluent(): self
    {
        return $this;
    }
}
