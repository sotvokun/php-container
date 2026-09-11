<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class BadProvider implements \Sotvokun\Container\Aop\InterceptorProvider
{
    public static function interceptors(): array
    {
        return [new \stdClass()];
    }
}
