<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class CycleInterceptorTarget
{
    #[CycleInterceptorProvider]
    public function run(): string
    {
        return 'never';
    }
}
