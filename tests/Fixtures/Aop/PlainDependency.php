<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

final class PlainDependency
{
    public function value(): string
    {
        return 'dependency';
    }
}
