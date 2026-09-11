<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class ReferenceParameterTarget
{
    #[SignatureProvider]
    public function mutate(string &$value): void
    {
        $value .= ':target';
    }
}
