<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class NamedVariadicTarget
{
    #[SignatureProvider]
    public function values(string $first = 'default', string ...$rest): array
    {
        return [$first, $rest];
    }
}
