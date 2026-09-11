<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class DefaultNewTarget
{
    #[SignatureProvider]
    public function value(ValueObject $object = new ValueObject('default-object')): string
    {
        return $object->value;
    }
}
