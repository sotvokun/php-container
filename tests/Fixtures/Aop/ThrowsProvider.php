<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Attribute;
use RuntimeException;
use Sotvokun\Container\Aop\InterceptorProvider;

#[Attribute(Attribute::TARGET_METHOD)]
final class ThrowsProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        throw new RuntimeException('provider');
    }
}
