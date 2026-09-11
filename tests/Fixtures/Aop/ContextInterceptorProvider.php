<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class ContextInterceptorProvider implements \Sotvokun\Container\Aop\InterceptorProvider
{
    public static function interceptors(): array
    {
        return [ContextInterceptor::class];
    }
}
