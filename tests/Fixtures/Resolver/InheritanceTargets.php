<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resolver;

require_once __DIR__ . '/Providers.php';

class MarkedParent
{
    #[FirstProvider]
    public function inherited(): void {}
}

class InheritsMarkedMethod extends MarkedParent {}

class OverridesWithoutAttribute extends MarkedParent
{
    public function inherited(): void {}
}

class OverridesWithAttribute extends MarkedParent
{
    #[SecondProvider]
    public function inherited(): void {}
}

trait MarkedTrait
{
    #[FirstProvider]
    public function fromTrait(): void {}
}

class UsesMarkedTrait
{
    use MarkedTrait;
}

class UsesMarkedTraitAlias
{
    use MarkedTrait {
        fromTrait as aliasedTrait;
    }
}

class UsesProtectedTraitAlias
{
    use MarkedTrait {
        fromTrait as protected hiddenTrait;
    }
}

interface MarkedInterface
{
    #[FirstProvider]
    public function contract(): void;
}

class ImplementsMarkedInterface implements MarkedInterface
{
    public function contract(): void {}
}
