<?php

declare(strict_types=1);

namespace Sotvokun\Container\Lazy;

use Closure;
use Illuminate\Container\Container as IlluminateContainer;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use Sotvokun\Container\Attributes\Lazy;

/**
 * Default native lazy-object resolver, assembled by the container.
 *
 * @internal
 */
final class LazyDependencyResolver
{
    /**
     * @param Closure(string): mixed $contextualConcrete
     * @param Closure(string): mixed $cachedInstance
     * @param Closure(class-string): ReflectionClass<object> $proxyReflection
     * @param Closure(): (Closure(string): mixed) $deferredResolution
     */
    public function __construct(
        private readonly IlluminateContainer $container,
        private readonly Closure $contextualConcrete,
        private readonly Closure $cachedInstance,
        private readonly Closure $proxyReflection,
        private readonly Closure $deferredResolution,
    ) {}

    public function resolve(Lazy $attribute, ReflectionParameter $parameter): mixed
    {
        $consumer = $parameter->getDeclaringClass()?->getName() ?? '<unknown>';
        $type = $parameter->getType();
        $resolutionKey = $attribute->resolutionKey;
        if ($parameter->isVariadic()) {
            throw $this->unresolvableLazy($consumer, $parameter, 'variadic parameters cannot be lazily injected');
        }
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw $this->unsupportedLazy($consumer, $parameter, $resolutionKey, 'only a single non-builtin named type is supported');
        }
        $declaredType = $type->getName();
        if ($declaredType === 'self' || $declaredType === 'parent') {
            throw $this->unresolvableLazy($consumer, $parameter, "relative type {$declaredType} cannot be lazily injected");
        }
        $resolutionKey ??= $declaredType;

        $canonical = $this->container->getAlias($resolutionKey);
        $contextual = ($this->contextualConcrete)($canonical);
        $needsContextualBuild = $contextual !== null;
        $instance = ($this->cachedInstance)($canonical);
        if ($instance !== null && !$needsContextualBuild) {
            if (!$instance instanceof $declaredType) {
                throw $this->lazyFailure($consumer, $parameter, $resolutionKey, "cached instance does not satisfy {$declaredType}");
            }
            return $instance;
        }

        $concrete = $contextual ?? $this->staticConcrete($canonical);
        if (!is_string($concrete) || !class_exists($concrete)) {
            throw $this->unsupportedLazy($consumer, $parameter, $resolutionKey, 'the registration cannot be reduced to a concrete class without executing user code');
        }
        if (!is_a($concrete, $declaredType, true)) {
            throw $this->unsupportedLazy($consumer, $parameter, $resolutionKey, "concrete {$concrete} does not satisfy {$declaredType}");
        }

        $reflection = new ReflectionClass($concrete);
        if ($reflection->isAbstract() || $reflection->isEnum()) {
            $kind = $reflection->isEnum() ? 'an enum' : 'an abstract class';
            throw $this->unsupportedLazy($consumer, $parameter, $resolutionKey, "concrete {$concrete} is {$kind} and cannot be made lazy");
        }
        for ($ancestor = $reflection; $ancestor !== false; $ancestor = $ancestor->getParentClass()) {
            if ($ancestor->isInternal() && $ancestor->getName() !== \stdClass::class) {
                $reason = $ancestor === $reflection
                    ? "concrete {$concrete} is an internal class and cannot be made lazy"
                    : "concrete {$concrete} inherits internal class {$ancestor->getName()} and cannot be made lazy";
                throw $this->unsupportedLazy($consumer, $parameter, $resolutionKey, $reason);
            }
        }

        $reflection = ($this->proxyReflection)($concrete);
        $resolve = ($this->deferredResolution)();
        return $reflection->newLazyProxy(function () use ($resolve, $resolutionKey, $declaredType, $concrete): object {
            $actual = $resolve($resolutionKey);
            if (!$actual instanceof $declaredType || !$actual instanceof $concrete) {
                throw new \Illuminate\Contracts\Container\BindingResolutionException("Lazy resolution of [{$resolutionKey}] returned an incompatible object.");
            }
            return $actual;
        });
    }

    private function staticConcrete(string $abstract): mixed
    {
        $bindings = $this->container->getBindings();
        if (!isset($bindings[$abstract])) {
            return $abstract;
        }
        $concrete = $bindings[$abstract]['concrete'];
        if (!$concrete instanceof \Closure) {
            return $concrete;
        }
        $function = new ReflectionFunction($concrete);
        if ($function->getClosureScopeClass()?->getName() !== IlluminateContainer::class) {
            return null;
        }
        $variables = $function->getStaticVariables();
        return isset($variables['concrete']) && is_string($variables['concrete']) ? $variables['concrete'] : null;
    }

    private function unsupportedLazy(string $consumer, ReflectionParameter $parameter, string|null $resolutionKey, string $reason): \Illuminate\Contracts\Container\BindingResolutionException
    {
        $type = $parameter->getType();
        $key = $resolutionKey ?? ($type instanceof ReflectionNamedType ? $type->getName() : '<unknown>');
        return $this->lazyFailure($consumer, $parameter, $key, $reason);
    }

    private function lazyFailure(string $consumer, ReflectionParameter $parameter, string $key, string $reason): \Illuminate\Contracts\Container\BindingResolutionException
    {
        return new \Illuminate\Contracts\Container\BindingResolutionException("Cannot lazily inject [{$key}] into {$consumer}::\${$parameter->getName()}: {$reason}.");
    }

    private function unresolvableLazy(string $consumer, ReflectionParameter $parameter, string $reason): UnresolvableLazyDependencyException
    {
        return new UnresolvableLazyDependencyException("Cannot handle lazy injection for {$consumer}::\${$parameter->getName()}: {$reason}.");
    }
}
