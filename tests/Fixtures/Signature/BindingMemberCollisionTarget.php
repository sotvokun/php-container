<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class BindingMemberCollisionTarget
{
    #[SignatureProvider]
    public function _setBindings(array $bindings): void {}
}
