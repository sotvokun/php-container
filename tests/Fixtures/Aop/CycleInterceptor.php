<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

final class CycleInterceptor implements MethodInterceptor
{
    public function __construct(
        public CycleInterceptorTarget $target
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}
