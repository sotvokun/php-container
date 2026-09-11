<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class ParentCallTarget extends ParentMethodBase
{
    #[SignatureProvider]
    public function callParent(): string
    {
        return parent::inherited();
    }
}
