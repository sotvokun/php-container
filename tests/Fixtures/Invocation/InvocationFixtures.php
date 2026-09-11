<?php

declare(strict_types=1);

namespace Tests\Fixtures\Invocation;

use ArrayObject;
use Attribute;
use RuntimeException;
use Sotvokun\Container\Aop\InterceptorProvider;
use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class InvocationMarker
{
    public function __construct(
        public string $value
    ) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
final class InvocationProvider implements InterceptorProvider
{
    /** @var list<MethodInterceptor> */
    public static array $interceptors = [];

    public static function interceptors(): array
    {
        return self::$interceptors;
    }
}

final class InvocationLog
{
    /** @var list<mixed> */
    public array $entries = [];
}

final class ProceedingInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}

final class BypassInterceptor implements MethodInterceptor
{
    public function __construct(
        private mixed $result
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $this->result;
    }
}

final class CatchingInterceptor implements MethodInterceptor
{
    public function __construct(
        private mixed $fallback
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        try {
            return $invocation->proceed();
        } catch (RuntimeException) {
            return $this->fallback;
        }
    }
}

final class PositionalMutationInterceptor implements MethodInterceptor
{
    public function __construct(
        private InvocationLog $log
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $first = $invocation->getArguments();
        $second = $invocation->getArguments();
        $this->log->entries[] = ['mutation-arguments', $first, $second];
        $first[0] = 'changed';
        $first[1] = 7;

        return $invocation->proceed();
    }
}

final class ArgumentObservationInterceptor implements MethodInterceptor
{
    public function __construct(
        private InvocationLog $log
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->log->entries[] = ['observed-arguments', $invocation->getArguments()];

        return $invocation->proceed();
    }
}

final class NamedSnapshotInterceptor implements MethodInterceptor
{
    public function __construct(
        private InvocationLog $log
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $first = $invocation->getNamedArguments();
        $second = $invocation->getNamedArguments();
        $first['name'] = 'snapshot-only';
        $this->log->entries[] = ['named', $first, $second, $invocation->getArguments()];

        return $invocation->proceed();
    }
}

final class MetadataInterceptor implements MethodInterceptor
{
    public function __construct(
        private InvocationLog $log
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->log->entries[] = $invocation->getMethod();

        return $invocation->proceed();
    }
}

final class DoubleProceedInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        return [$invocation->proceed(), $invocation->proceed()];
    }
}

final class FinallyInterceptor implements MethodInterceptor
{
    public function __construct(
        private InvocationLog $log
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->log->entries[] = 'before';
        try {
            return $invocation->proceed();
        } finally {
            $this->log->entries[] = 'finally';
        }
    }
}

final class ThrowOnceInterceptor implements MethodInterceptor
{
    private bool $shouldThrow = true;

    public function invoke(MethodInvocation $invocation): mixed
    {
        if ($this->shouldThrow) {
            $this->shouldThrow = false;
            throw new RuntimeException('interceptor failed');
        }

        return $invocation->proceed();
    }
}

final class InvalidArgumentOnceInterceptor implements MethodInterceptor
{
    public function __construct(
        private string $operation
    ) {}

    private bool $invalid = true;

    public function invoke(MethodInvocation $invocation): mixed
    {
        if ($this->invalid) {
            $this->invalid = false;
            $arguments = $invocation->getArguments();
            match ($this->operation) {
                'type' => $arguments[1] = 'not-an-int',
                'delete' => $arguments->offsetUnset(1),
                'add' => $arguments[] = 'extra',
            };
        }

        return $invocation->proceed();
    }
}

final class InvalidReturnOnceInterceptor implements MethodInterceptor
{
    private bool $invalid = true;

    public function invoke(MethodInvocation $invocation): mixed
    {
        if ($this->invalid) {
            $this->invalid = false;
            return [];
        }

        return $invocation->proceed();
    }
}
