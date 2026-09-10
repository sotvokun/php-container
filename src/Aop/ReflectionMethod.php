<?php

/**
 * This file is based on the Ray.Aop implementation.
 * @see https://github.com/ray-di/Ray.Aop
 */

declare(strict_types=1);

namespace Sotvokun\Container\Aop;

use Override;
use ReflectionAttribute;

use function array_map;

final class ReflectionMethod extends \ReflectionMethod
{
    /**
     * @return ReflectionClass<object>
     *
     * @psalm-external-mutation-free
     */
    #[Override]
    public function getDeclaringClass(): ReflectionClass
    {
        $parent = parent::getDeclaringClass();

        return new ReflectionClass($parent->getName());
    }

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
            $attributes
        );
    }

    /**
     * Get a specific attribute by name
     *
     * @template T of object
     * @param class-string<T> $annotationName
     * @param int             $flags          Optional flags (e.g., ReflectionAttribute::IS_INSTANCEOF)
     *
     * @return T|null
     */
    public function getAnnotation(string $annotationName, int $flags = ReflectionAttribute::IS_INSTANCEOF): object|null
    {
        $attributes = $this->getAttributes($annotationName, $flags);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance();
        }

        return null;
    }
}
