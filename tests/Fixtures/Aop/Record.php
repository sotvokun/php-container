<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Record implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [RecordingInterceptor::class];
    }
}
