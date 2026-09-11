<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class ParentReturnTarget extends TypeParent
{
    #[SignatureProvider]
    public function fluent(): parent
    {
        return $this;
    }
}
