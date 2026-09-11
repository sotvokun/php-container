<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

final class CountingInterceptor implements MethodInterceptor
{
    public int $calls = 0;

    public function invoke(MethodInvocation $invocation): mixed
    {
        ++$this->calls;
        return $invocation->proceed();
    }
}
