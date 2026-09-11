<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Container\Container as IlluminateContainer;
use PHPUnit\Framework\Assert;
use Throwable;

final class ContainerComparison
{
    /**
     * Applies identical bindings to a native container and an AOP container,
     * then compares the observable result or exception type.
     *
     * @param callable(IlluminateContainer): void $configure
     * @param callable(IlluminateContainer): mixed $resolve
     */
    public static function assertSameObservableBehavior(callable $configure, callable $resolve): void
    {
        $native = new IlluminateContainer();
        $aop = new \Sotvokun\Container\Container();
        $configure($native);
        $configure($aop);

        $nativeResult = self::capture($resolve, $native);
        $aopResult = self::capture($resolve, $aop);

        Assert::assertSame($nativeResult['exception'], $aopResult['exception']);
        if ($nativeResult['exception'] === null) {
            Assert::assertSame($nativeResult['value'], $aopResult['value']);
        }
    }

    /**
     * Checks whether resolving the same abstract twice has the same identity
     * relationship in both containers (for example, singleton versus bind).
     *
     * @param callable(IlluminateContainer): void $configure
     * @param callable(IlluminateContainer): object $resolve
     */
    public static function assertSameResolutionIdentity(callable $configure, callable $resolve): void
    {
        $native = new IlluminateContainer();
        $aop = new \Sotvokun\Container\Container();
        $configure($native);
        $configure($aop);

        $nativeFirst = $resolve($native);
        $nativeSecond = $resolve($native);
        $aopFirst = $resolve($aop);
        $aopSecond = $resolve($aop);

        Assert::assertSame($nativeFirst === $nativeSecond, $aopFirst === $aopSecond);
        Assert::assertSame($nativeFirst::class, $aopFirst::class);
    }

    /**
     * @return array{value:mixed,exception:class-string<Throwable>|null}
     */
    private static function capture(callable $resolve, IlluminateContainer $container): array
    {
        try {
            return ['value' => $resolve($container), 'exception' => null];
        } catch (Throwable $exception) {
            return ['value' => null, 'exception' => $exception::class];
        }
    }
}
