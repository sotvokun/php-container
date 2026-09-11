<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class DefaultEnumTarget
{
    #[SignatureProvider]
    public function value(DefaultMode $mode = DefaultMode::Fast): DefaultMode
    {
        return $mode;
    }
}
