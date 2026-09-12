<?php

declare(strict_types=1);

namespace Sotvokun\Container\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Container\ContextualAttribute;
use ReflectionParameter;
use Sotvokun\Container\Container;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Lazy implements ContextualAttribute
{
    public function __construct(
        public string|null $resolutionKey = null
    ) {}

    public static function resolve(self $attribute, ContainerContract $container, ReflectionParameter $parameter): mixed
    {
        if (!$container instanceof Container) {
            throw new \Illuminate\Contracts\Container\BindingResolutionException(
                '#[Lazy] requires Sotvokun\\Container\\Container.',
            );
        }

        $consumer = $parameter->getDeclaringClass()?->getName() ?? '<unknown>';
        $key = $attribute->resolutionKey ?? '<parameter type>';
        throw new \Illuminate\Contracts\Container\BindingResolutionException(
            "Cannot lazily inject [{$key}] into {$consumer}::\${$parameter->getName()}: lazy injection is not configured; call enableLazyInjection() first.",
        );
    }
}
