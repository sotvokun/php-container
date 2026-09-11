<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class ThrowsProviderTarget
{
    #[ThrowsProvider]
    public function run(): string
    {
        return 'never';
    }
}
