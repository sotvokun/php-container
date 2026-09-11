<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class DefaultConstantTarget
{
    public const LABEL = 'class-label';

    #[SignatureProvider]
    public function values(string $php = PHP_VERSION, string $label = self::LABEL): array
    {
        return [$php, $label];
    }
}
