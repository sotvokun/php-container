<?php

declare(strict_types=1);

namespace Tests\Fixtures\Invocation;

use RuntimeException;

#[InvocationMarker('class-marker')]
class InvocationTarget
{
    public int $calls = 0;

    #[InvocationProvider]
    public function concatenate(string $name, int $number): string
    {
        ++$this->calls;
        return $name . ':' . $number;
    }

    #[InvocationProvider]
    public function flexible(): mixed
    {
        ++$this->calls;
        return 'original';
    }

    #[InvocationProvider]
    public function fail(): string
    {
        ++$this->calls;
        throw new RuntimeException('business failed');
    }

    #[InvocationProvider]
    public function optional(string $name = 'default', ?string $note = null): array
    {
        ++$this->calls;
        return [$name, $note];
    }

    #[InvocationProvider]
    public function variadic(string $prefix, string ...$labels): array
    {
        ++$this->calls;
        return [$prefix, $labels];
    }

    #[InvocationProvider, InvocationMarker('method-marker')]
    public function described(string $value): string
    {
        return $value;
    }

    #[InvocationProvider]
    public function counted(): mixed
    {
        return ++$this->calls;
    }

    #[InvocationProvider]
    public function typed(string $name, int $number): string
    {
        ++$this->calls;
        return $name . ':' . $number;
    }
}
