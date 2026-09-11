<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resolver;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class FirstProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [];
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class SecondProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [];
    }
}

#[Attribute(Attribute::TARGET_ALL)]
final class OrdinaryAttribute {}

#[Attribute(Attribute::TARGET_CLASS)]
final class ClassOnlyProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [];
    }
}

final class NotAnAttributeProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [];
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class ParameterProvider implements InterceptorProvider
{
    public static int $constructed = 0;

    public function __construct(
        public string $value
    ) {
        ++self::$constructed;
    }

    public static function interceptors(): array
    {
        return [];
    }
}
