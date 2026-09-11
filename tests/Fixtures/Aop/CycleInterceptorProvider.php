<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;

#[Attribute(Attribute::TARGET_METHOD)]
final class CycleInterceptorProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [CycleInterceptor::class];
    }
}
