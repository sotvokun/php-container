<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class BadProviderTarget
{
    #[BadProvider]
    public function run(): string
    {
        return 'bad';
    }
}
