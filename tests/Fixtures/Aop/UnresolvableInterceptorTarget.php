<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class UnresolvableInterceptorTarget
{
    #[UnresolvableInterceptorProvider]
    public function run(): string
    {
        return 'resolved';
    }
}
