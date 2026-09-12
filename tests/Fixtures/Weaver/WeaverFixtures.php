<?php

declare(strict_types=1);

namespace Tests\Fixtures\Weaver;

use Attribute;
use RuntimeException;
use Sotvokun\Container\Aop\InterceptorProvider;
use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

final class Log
{
    /** @var list<string> */
    public array $events = [];
}

final class Counts
{
    /** @var array<string, int> */
    public array $values = [];
}

class NamedInterceptor implements MethodInterceptor
{
    public function __construct(
        private Log $log,
        private string $name = 'class'
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->log->events[] = $this->name . ':before:' . $invocation->getMethod()->getName();
        $result = $invocation->proceed();
        $this->log->events[] = $this->name . ':after:' . $invocation->getMethod()->getName();
        return $result;
    }
}

final class CountingInterceptor implements MethodInterceptor
{
    public function __construct(
        private Counts $counts
    ) {
        $this->counts->values['created'] = ($this->counts->values['created'] ?? 0) + 1;
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->counts->values['invoked'] = ($this->counts->values['invoked'] ?? 0) + 1;
        return $invocation->proceed();
    }
}

final class StatefulInterceptor implements MethodInterceptor
{
    public int $calls = 0;

    public function invoke(MethodInvocation $invocation): mixed
    {
        ++$this->calls;
        return $invocation->proceed();
    }
}

final class ClosureInterceptor implements MethodInterceptor
{
    private \Closure $closure;

    public function __construct()
    {
        $this->closure = static fn(): bool => true;
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}

final class ResourceInterceptor implements MethodInterceptor
{
    /** @var resource */
    private $resource;

    public function __construct()
    {
        $this->resource = fopen('php://memory', 'r');
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}

final class ContainerInterceptor implements MethodInterceptor
{
    public function __construct(
        private \Illuminate\Container\Container $container
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}

final class ThrowingSerializeInterceptor implements MethodInterceptor
{
    public function __serialize(): array
    {
        throw new RuntimeException('serialize failed');
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class ClassProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [NamedInterceptor::class];
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class InstanceProvider implements InterceptorProvider
{
    public static ?MethodInterceptor $instance = null;

    public static function interceptors(): array
    {
        return [self::$instance ?? throw new RuntimeException('missing instance')];
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class MixedProvider implements InterceptorProvider
{
    public static ?MethodInterceptor $instance = null;

    public static function interceptors(): array
    {
        return [NamedInterceptor::class, self::$instance ?? throw new RuntimeException('missing instance')];
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class FirstProvider implements InterceptorProvider
{
    /** @var list<MethodInterceptor> */
    public static array $items = [];

    public static function interceptors(): array
    {
        return self::$items;
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class SecondProvider implements InterceptorProvider
{
    /** @var list<MethodInterceptor> */
    public static array $items = [];

    public static function interceptors(): array
    {
        return self::$items;
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class EmptyProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [];
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class ConfigurableProvider implements InterceptorProvider
{
    /** @var array<mixed> */
    public static array $items = [];

    public static function interceptors(): array
    {
        return self::$items;
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class ThrowsProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        throw new RuntimeException('provider failed');
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class BadReturnProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        /** @phpstan-ignore-next-line */
        return null;
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class CountingProvider implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [CountingInterceptor::class];
    }
}
