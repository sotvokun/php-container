<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

final class ContextInterceptor implements MethodInterceptor
{
    public function __construct(
        private ScenarioPort $port,
        private EventRecorder $recorder
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->recorder->record('interceptor-port:' . $this->port->name());
        return $invocation->proceed();
    }
}
