<?php

/**
 * This file is based on the Ray.Aop implementation.
 * @see https://github.com/ray-di/Ray.Aop
 */

namespace Sotvokun\Container\Aop;

use ArrayObject;

/**
 * @template T of object
 */
interface MethodInvocation
{
    /**
     * Proceeds to the next interceptor in the chain.
     *
     * <p>The implementation and the semantics of this method depends
     * on the actual joinpoint type (see the children interfaces).
     *
     * @return mixed see the children interfaces' proceed definition.
     *
     * Throwable if the joinpoint throws an exception
     */
    public function proceed();

    /**
     * Returns the object that holds the current joinpoint's static part.
     *
     * <p>For instance, the target object for an invocation.
     *
     * @return T (can be null if the accessible object is static)
     */
    public function getThis();

    /**
     * Get the arguments as an array object.
     *
     * @return ArrayObject<int, mixed> the argument of the invocation ['arg1', 'arg2']
     */
    public function getArguments(): ArrayObject;

    /**
     * Get the named arguments as an array object.
     *
     * @return ArrayObject<non-empty-string, mixed> the argument of the invocation  [`paramName1'=>'arg1', `paramName2'=>'arg2']
     */
    public function getNamedArguments(): ArrayObject;

    /**
     * Gets the method being called.
     *
     * <p>This method is a friendly implementation of the {@link * Joinpoint#getStaticPart()} method (same result).
     *
     * @return ReflectionMethod method being called
     */
    public function getMethod(): ReflectionMethod;
}
