<?php

declare(strict_types=1);

namespace Sotvokun\Container\Aop\Adapter;

use ArrayObject;
use Ray\Aop\MethodInvocation as RayMethodInvocation;
use Sotvokun\Container\Aop\MethodInvocation;
use Sotvokun\Container\Aop\ReflectionMethod;

/**
 * @implements MethodInvocation<object>
 */
final readonly class RayMethodInvocationAdapter implements MethodInvocation
{
    public function __construct(
        private RayMethodInvocation $invocation
    ) {}

    public function proceed(): mixed
    {
        return $this->invocation->proceed();
    }

    // @phpstan-ignore-next-line return.unusedType
    public function getThis(): object|null
    {
        return $this->invocation->getThis();
    }

    /**
     * @return ArrayObject<int, mixed>
     */
    public function getArguments(): ArrayObject
    {
        return $this->invocation->getArguments();
    }

    /**
     * @return ArrayObject<non-empty-string, mixed>
     */
    public function getNamedArguments(): ArrayObject
    {
        return $this->invocation->getNamedArguments();
    }

    public function getMethod(): ReflectionMethod
    {
        $method = $this->invocation->getMethod();

        return new ReflectionMethod($method->getDeclaringClass()->getName(), $method->getName());
    }
}
