<?php

declare(strict_types=1);

namespace Sotvokun\Container\Aop;

use Composer\ClassMapGenerator\ClassMapGenerator;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Filesystem\Path;

use function is_dir;

final class ClassResolver
{
    /**
     * @var array<class-string,WeavableClassMetadata>
     */
    private array $targets = [];

    /**
     * @var array<string, string>
     */
    private array $classMap;

    /**
     * @var array<string, true>
     */
    private array $inspected = [];

    /**
     * @param list<string> $directories
     */
    public function __construct(array $directories)
    {
        $classMapGenerator = (new ClassMapGenerator())->avoidDuplicateScans();
        foreach ($directories as $directory) {
            $directory = Path::canonicalize($directory);
            if (is_dir($directory)) {
                $classMapGenerator->scanPaths($directory);
            }
        }

        $this->classMap = $classMapGenerator->getClassMap()->getMap();
    }

    /**
     * @param class-string $class
     */
    public function shouldWeave(string $class): bool
    {
        $this->inspectIfScanned($class);

        return isset($this->targets[$class]);
    }

    /**
     * @param class-string $class
     *
     * @return WeavableClassMetadata
     */
    public function metadataFor(string $class): WeavableClassMetadata
    {
        $this->inspectIfScanned($class);

        if (!isset($this->targets[$class])) {
            throw new LogicException("Class {$class} is not a weavable class.");
        }

        return $this->targets[$class];
    }

    /**
     * @param class-string $class
     */
    private function inspectIfScanned(string $class): void
    {
        if (isset($this->inspected[$class])) {
            return;
        }

        if (!isset($this->classMap[$class])) {
            $this->inspected[$class] = true;
            return;
        }

        if (!class_exists($class, false)) {
            require_once $this->classMap[$class];
        }
        if (class_exists($class, false)) {
            $this->inspect($class);
        }
        $this->inspected[$class] = true;
    }

    /**
     * @param class-string $class
     */
    private function inspect(string $class): void
    {
        $reflection = new \ReflectionClass($class);

        /** @var list<array{\ReflectionMethod,list<\ReflectionAttribute<InterceptorProvider>>}> */
        $aspectedMethods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $methodAttributes = $method->getAttributes(InterceptorProvider::class, \ReflectionAttribute::IS_INSTANCEOF);
            if ($methodAttributes !== []) {
                $aspectedMethods[] = [$method, $methodAttributes];
            }
        }

        if ($aspectedMethods === []) {
            return;
        }

        $this->assertWeavableClass($reflection);

        /** @var array<non-empty-string,list<class-string<InterceptorProvider>>> */
        $classMethodAttributes = [];
        foreach ($aspectedMethods as [$method, $attributes]) {
            $this->assertWeavableMethod($reflection, $method);

            $methodAttributes = [];
            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();
                $methodAttributes[] = $attributeName;
            }

            $classMethodAttributes[$method->getName()] = $methodAttributes;
        }

        $this->targets[$class] = new WeavableClassMetadata($classMethodAttributes);
    }

    private function assertWeavableClass(\ReflectionClass $class): void
    {
        if ($class->isFinal() || !$class->isInstantiable()) {
            throw new InvalidArgumentException("AOP target {$class->getName()} must be a non-final, instantiable class.");
        }
    }

    private function assertWeavableMethod(\ReflectionClass $class, \ReflectionMethod $method): void
    {
        if ($method->isConstructor() || $method->isDestructor()) {
            throw new InvalidArgumentException("AOP target {$class->getName()}::{$method->getName()}() must not be a constructor or destructor.");
        }
        if (!$method->isPublic() || $method->isStatic() || $method->isFinal()) {
            throw new InvalidArgumentException(sprintf(
                'AOP target %s::%s() must be public, non-static, and non-final.',
                $class->getName(),
                $method->getName(),
            ));
        }
    }
}
