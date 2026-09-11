<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

final class RecordingInterceptor implements MethodInterceptor
{
    public function __construct(
        private EventRecorder $recorder
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->recorder->record('before:' . $invocation->getMethod()->getName());
        $result = $invocation->proceed();
        $this->recorder->record('after:' . $invocation->getMethod()->getName());

        return $result;
    }
}
