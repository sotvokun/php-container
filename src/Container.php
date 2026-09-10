<?php

declare(strict_types=1);

namespace Sotvokun\Container;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Contracts\Container\SelfBuilding;
use ReflectionClass;
use Sotvokun\Container\Aop\ClassResolver;
use Sotvokun\Container\Aop\Weaver;
use Sotvokun\Container\Aop\WeaverInterface;

use function is_string;

final class Container extends IlluminateContainer
{
    private ClassResolver|null $classResolver = null;

    private WeaverInterface|null $weaver = null;

    /**
     * Enable attribute-driven AOP for classes found in the supplied directories.
     *
     * @param list<string>     $directories
     * @param non-empty-string $generatedClassDirectory
     */
    public function withAop(array $directories, string $generatedClassDirectory): void
    {
        $this->classResolver = new ClassResolver($directories);
        $this->weaver = new Weaver($this, $this->classResolver, $generatedClassDirectory);
    }

    /**
     * Resolve AOP constructor dependencies against the original class, then
     * delegate proxy instantiation and interceptor binding to the weaver.
     *
     * @template TClass of object
     * @param \Closure(static, array): TClass|class-string<TClass> $concrete
     * @return TClass
     */
    public function build($concrete)
    {
        if (
            is_string($concrete)
            && $this->classResolver !== null
            && $this->classResolver->shouldWeave($concrete)
            && $this->weaver !== null
        ) {
            // Preserve the explicit factory contract of self-building classes.
            if (
                is_a($concrete, SelfBuilding::class, true)
                && ! in_array($concrete, $this->buildStack, true)
            ) {
                /** @var TClass $instance */
                $instance = parent::build($concrete);
                return $instance;
            }

            $reflector = new ReflectionClass($concrete);
            if (in_array($concrete, $this->buildStack, true)) {
                throw new CircularDependencyException('Circular dependency detected while building an AOP target.');
            }

            /** @psalm-suppress InvalidPropertyAssignmentValue Illuminate documents buildStack as array[] but stores class names and closure IDs. */
            $this->buildStack[] = $concrete;

            try {
                $constructor = $reflector->getConstructor();
                /** @var list<mixed> $arguments */
                $arguments = $constructor === null
                    ? []
                    : $this->resolveDependencies($constructor->getParameters());

                /** @var TClass $instance The generated class extends the original class. */
                $instance = $this->weaver->newInstance($concrete, $arguments);
            } finally {
                array_pop($this->buildStack);
            }

            $this->fireAfterResolvingAttributeCallbacks($reflector->getAttributes(), $instance);

            return $instance;
        }

        /** @psalm-suppress ArgumentTypeCoercion The inherited method accepts the same closure contract. */
        return parent::build($concrete);
    }
}
