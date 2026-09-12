<?php

declare(strict_types=1);

namespace Sotvokun\Container;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Contracts\Container\SelfBuilding;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionParameter;
use Sotvokun\Container\Aop\ClassResolver;
use Sotvokun\Container\Aop\Weaver;
use Sotvokun\Container\Aop\WeaverInterface;
use Sotvokun\Container\Attributes\Lazy;
use Sotvokun\Container\Lazy\LazyDependencyResolver;
use Symfony\Component\Filesystem\Path;

use function is_string;

class Container extends IlluminateContainer
{
    private ClassResolver|null $classResolver = null;

    private WeaverInterface|null $weaver = null;

    /**
     * Enable attribute-driven AOP for classes found in the supplied directories.
     *
     * @param list<string>     $directories
     * @param non-empty-string $generatedClassDirectory
     */
    public function enableAop(array $directories, string $generatedClassDirectory): void
    {
        foreach ([...$directories, $generatedClassDirectory] as $directory) {
            if (!Path::isAbsolute($directory)) {
                throw new InvalidArgumentException(sprintf(
                    'AOP directories must use absolute paths; received "%s".',
                    $directory,
                ));
            }
        }

        $normalizedGeneratedDirectory = $this->resolvePath($generatedClassDirectory);
        if (!is_dir($normalizedGeneratedDirectory)) {
            throw new InvalidArgumentException(sprintf(
                'AOP generated class directory "%s" must be an existing directory.',
                $generatedClassDirectory,
            ));
        }

        foreach ($directories as $directory) {
            $normalizedScanDirectory = $this->resolvePath($directory);
            if (
                $normalizedScanDirectory !== '' &&
                $normalizedGeneratedDirectory !== '' &&
                Path::isBasePath($normalizedScanDirectory, $normalizedGeneratedDirectory)
            ) {
                throw new InvalidArgumentException(sprintf(
                    'AOP generated class directory "%s" must be outside scan directory "%s".',
                    $generatedClassDirectory,
                    $directory,
                ));
            }
        }

        $this->classResolver = new ClassResolver($directories);
        $this->weaver = new Weaver($this, $this->classResolver, $generatedClassDirectory);
    }

    public function enableLazyInjection(): void
    {
        if (!in_array('newLazyProxy', get_class_methods(ReflectionClass::class), true)) {
            throw new InvalidArgumentException('Lazy injection requires PHP 8.4 native lazy objects.');
        }

        $resolver = new LazyDependencyResolver(
            $this,
            fn(string $key): mixed => $this->getContextualConcrete($key),
            fn(string $key): mixed => $this->instances[$key] ?? null,
            function (string $concrete): ReflectionClass {
                if ($this->classResolver?->shouldWeave($concrete) && $this->weaver !== null && !is_a($concrete, SelfBuilding::class, true)) {
                    return new ReflectionClass($this->weaver->weave($concrete));
                }

                return new ReflectionClass($concrete);
            },
            function (): \Closure {
                // Capture the actual consumer, including inherited constructors.
                $buildContext = end($this->buildStack);
                return function (string $key) use ($buildContext): mixed {
                    $buildStackDepth = count($this->buildStack);
                    $parameterStackDepth = count($this->with);
                    $this->buildStack[] = $buildContext === false ? '' : $buildContext;
                    try {
                        return $this->make($key);
                    } finally {
                        array_splice($this->buildStack, $buildStackDepth);
                        array_splice($this->with, $parameterStackDepth);
                    }
                };
            },
        );

        $this->whenHasAttribute(
            Lazy::class,
            static fn(Lazy $attribute, IlluminateContainer $container, ReflectionParameter $parameter): mixed => $resolver->resolve($attribute, $parameter),
        );
    }

    private function resolvePath(string $path): string
    {
        $absolutePath = Path::canonicalize($path);
        $candidate = $absolutePath;
        $missingSegments = [];
        $resolved = realpath($candidate);
        while ($resolved === false) {
            $parent = Path::getDirectory($candidate);
            if ($parent === '' || $parent === $candidate) {
                break;
            }

            array_unshift($missingSegments, Path::makeRelative($candidate, $parent));
            $candidate = $parent;
            $resolved = realpath($candidate);
        }

        $normalized = $absolutePath;
        if ($resolved !== false) {
            $normalized = Path::join($resolved, ...$missingSegments);
        }

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
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
            is_string($concrete) &&
            $this->classResolver !== null &&
            $this->classResolver->shouldWeave($concrete) &&
            $this->weaver !== null
        ) {
            // Preserve self-building factories and their ordinary build fallback.
            if (is_a($concrete, SelfBuilding::class, true)) {
                /** @var TClass $instance */
                $instance = parent::build($concrete);
                return $instance;
            }

            $reflector = new ReflectionClass($concrete);
            // @phpstan-ignore-next-line function.impossibleType
            if (in_array($concrete, $this->buildStack, true)) {
                throw new CircularDependencyException('Circular dependency detected while building an AOP target.');
            }

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

        return parent::build($concrete);
    }
}
