<?php

namespace Sotvokun\Container\Aop;

interface WeaverInterface
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @param list<mixed> $arguments
     * @return T
     */
    public function newInstance(string $class, array $arguments): object;

    /**
     * Prepare a stable proxy class without resolving interceptor providers.
     *
     * @param class-string $class
     * @return class-string
     */
    public function weave(string $class): string;
}
