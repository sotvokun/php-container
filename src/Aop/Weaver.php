<?php

declare(strict_types=1);

namespace Sotvokun\Container\Aop;

use Illuminate\Container\Container as IlluminateContainer;
use InvalidArgumentException;
use Ray\Aop\Bind;
use Sotvokun\Container\Aop\Adapter\RayMethodInterceptorAdapter;

final class Weaver implements WeaverInterface
{
    /** @var array<class-string, class-string> */
    private array $generatedClasses = [];

    /**
     * @param non-empty-string $generatedClassDirectory
     */
    public function __construct(
        private readonly IlluminateContainer $container,
        private readonly ClassResolver $resolver,
        private readonly string $generatedClassDirectory,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param list<mixed> $arguments
     * @return T
     */
    public function newInstance(string $class, array $arguments): object
    {
        $generated = $this->weave($class);
        $bind = $this->bindFor($class);
        /** @var T $instance */
        $instance = new $generated(...$arguments);
        if ($instance instanceof \Ray\Aop\WeavedInterface) {
            $instance->_setBindings($bind->getBindings());
        }

        return $instance;
    }

    /**
     * @param class-string $class
     * @return class-string
     */
    public function weave(string $class): string
    {
        if (isset($this->generatedClasses[$class])) {
            return $this->generatedClasses[$class];
        }

        $bind = new Bind();
        foreach ($this->resolver->metadataFor($class)->methods as $method => $attributes) {
            $bind->bindInterceptors($method, []);
        }

        return $this->generatedClasses[$class] = (new \Ray\Aop\Weaver($bind, $this->generatedClassDirectory))->weave($class);
    }

    /**
     * @param class-string<InterceptorProvider> $attribute
     * @return list<RayMethodInterceptorAdapter>
     */
    private function resolveInterceptors(string $attribute): array
    {
        $interceptors = [];
        foreach ($attribute::interceptors() as $interceptor) {
            $instance = is_string($interceptor) ? $this->container->make($interceptor) : $interceptor;
            // @phpstan-ignore-next-line instanceof.alwaysTrue
            if (!$instance instanceof MethodInterceptor) {
                throw new InvalidArgumentException("{$attribute} must return Sotvokun\Container\Aop\MethodInterceptor instances or class names.");
            }
            $interceptors[] = new RayMethodInterceptorAdapter($instance);
        }
        return $interceptors;
    }

    /**
     * @param class-string $class
     */
    private function bindFor(string $class): Bind
    {
        $bind = new Bind();
        $classMetadata = $this->resolver->metadataFor($class);
        foreach ($classMetadata->methods as $method => $methodAttributes) {
            foreach ($methodAttributes as $attribute) {
                $bind->bindInterceptors($method, $this->resolveInterceptors($attribute));
            }
        }

        return $bind;
    }
}
