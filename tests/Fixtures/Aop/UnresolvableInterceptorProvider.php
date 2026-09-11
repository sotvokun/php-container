<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;

#[Attribute(Attribute::TARGET_METHOD)]
final class UnresolvableInterceptorProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [UnboundInterceptor::class];
    }
}
