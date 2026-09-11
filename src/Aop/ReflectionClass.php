<?php

/**
 * This file is based on the Ray.Aop implementation.
 * @see https://github.com/ray-di/Ray.Aop
 */

declare(strict_types=1);

namespace Sotvokun\Container\Aop;

use Override;
use ReflectionAttribute;
use ReturnTypeWillChange;

use function array_map;

/**
 * @template T of object
 * @template-extends \ReflectionClass<T>
 */
final class ReflectionClass extends \ReflectionClass
{
    /**
     * Get all attributes as instantiated objects
     *
     * @return list<object>
     */
    public function getAnnotations(): array
    {
        $attributes = $this->getAttributes();

        return array_map(
            static fn($attribute) => $attribute->newInstance(),
            $attributes,
        );
    }

    /**
     * Get a specific attribute by name
     *
     * @template TAnnotation of object
     * @param class-string<TAnnotation> $annotationName
     * @param int                       $flags          Optional flags (default: ReflectionAttribute::IS_INSTANCEOF)
     *
     * @return TAnnotation|null
     */
    public function getAnnotation(string $annotationName, int $flags = ReflectionAttribute::IS_INSTANCEOF): object|null
    {
        $attributes = $this->getAttributes($annotationName, $flags);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance();
        }

        return null;
    }

    /**
     * @param int|null $filter
     *
     * @return list<ReflectionMethod>
     */
    #[Override]
    public function getMethods($filter = null): array
    {
        $nativeMethods = parent::getMethods($filter);
        $methods = [];
        foreach ($nativeMethods as $method) {
            $methods[] = new ReflectionMethod($method->class, $method->name);
        }

        return $methods;
    }

    #[Override]
    public function getConstructor(): \ReflectionMethod|null
    {
        $parent = parent::getConstructor();
        if ($parent === null) {
            return null;
        }

        return new ReflectionMethod($parent->class, $parent->name);
    }

    /**
     * @return ReflectionClass<object>|false
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function getParentClass()
    {
        $parent = \ReflectionClass::getParentClass();

        return $parent instanceof \ReflectionClass ? (new ReflectionClass($parent->getName())) : false;
    }
}
