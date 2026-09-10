<?php

declare(strict_types=1);

namespace Sotvokun\Container\Aop;

interface InterceptorProvider
{
    /**
     * @return list<class-string<MethodInterceptor>|MethodInterceptor>
     */
    public static function interceptors(): array;
}
