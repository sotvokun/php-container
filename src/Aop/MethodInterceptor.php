<?php

/**
 * This file is based on the Ray.Aop implementation.
 * @see https://github.com/ray-di/Ray.Aop
 */

namespace Sotvokun\Container\Aop;

/**
 * Description of an invocation to a method, given to an interceptor
 * upon method-call.
 *
 * <p>A method invocation is a joinpoint and can be intercepted by a method
 * interceptor.
 *
 * @template T of object
 */
interface MethodInterceptor
{
    /**
     * Gets the method being called.
     *
     * <p>This method is a friendly implementation of the {@link * Joinpoint#getStaticPart()} method (same result).
     *
     * @return ReflectionMethod method being called
     */
    public function invoke(MethodInvocation $invocation): mixed;
}
