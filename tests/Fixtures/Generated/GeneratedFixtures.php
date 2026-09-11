<?php

declare(strict_types=1);

namespace Tests\Fixtures\Generated;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;
use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

final class GeneratedLog
{
    /** @var list<string> */
    public array $events = [];
}

final class GeneratedInterceptor implements MethodInterceptor
{
    public function __construct(private GeneratedLog $log) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->log->events[] = 'before';
        $result = $invocation->proceed();
        $this->log->events[] = 'after';

        return $result;
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class GeneratedProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [GeneratedInterceptor::class];
    }
}
