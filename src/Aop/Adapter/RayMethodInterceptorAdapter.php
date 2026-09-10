<?php

declare(strict_types=1);

namespace Sotvokun\Container\Aop\Adapter;

use Ray\Aop\MethodInvocation as RayMethodInvocation;
use Sotvokun\Container\Aop\MethodInterceptor;

final readonly class RayMethodInterceptorAdapter implements \Ray\Aop\MethodInterceptor
{
    public function __construct(private MethodInterceptor $interceptor) {}

    public function invoke(RayMethodInvocation $invocation): mixed
    {
        return $this->interceptor->invoke(new RayMethodInvocationAdapter($invocation));
    }
}
