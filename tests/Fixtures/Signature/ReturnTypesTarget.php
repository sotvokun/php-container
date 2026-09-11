<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

class ReturnTypesTarget
{
    public int $voidCalls = 0;

    #[SignatureProvider]
    public function stringValue(): string
    {
        return '';
    }

    #[SignatureProvider]
    public function intValue(): int
    {
        return 0;
    }

    #[SignatureProvider]
    public function boolValue(): bool
    {
        return false;
    }

    #[SignatureProvider]
    public function arrayValue(): array
    {
        return [];
    }

    #[SignatureProvider]
    public function objectValue(): object
    {
        return new ValueObject('object');
    }

    #[SignatureProvider]
    public function nullValue(): null
    {
        return null;
    }

    #[SignatureProvider]
    public function mixedValue(): mixed
    {
        return 'mixed';
    }

    #[SignatureProvider]
    public function voidValue(): void
    {
        ++$this->voidCalls;
    }
}
