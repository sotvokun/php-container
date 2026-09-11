<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class MagicTarget
{
    #[SignatureProvider]
    public function __invoke(string $value): string
    {
        return 'invoke:' . $value;
    }

    #[SignatureProvider]
    public function __call(string $name, array $arguments): mixed
    {
        return [$name, $arguments];
    }
}
