<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class ComplexTypesTarget
{
    #[SignatureProvider]
    public function nullable(?string $value): ?string
    {
        return $value;
    }

    #[SignatureProvider]
    public function union(string|int $value): string|int
    {
        return $value;
    }

    #[SignatureProvider]
    public function intersection(Left&Right $value): Left&Right
    {
        return $value;
    }

    #[SignatureProvider]
    public function dnf((Left&Right)|null $value): (Left&Right)|null
    {
        return $value;
    }
}
