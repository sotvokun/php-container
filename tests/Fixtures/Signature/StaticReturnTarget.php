<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class StaticReturnTarget
{
    #[SignatureProvider]
    public function fluent(): static
    {
        return $this;
    }
}
