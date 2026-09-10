<?php

declare(strict_types=1);

namespace Sotvokun\Container\Aop;

final readonly class WeavableClassMetadata
{
    /**
     * @param array<non-empty-string,list<class-string<InterceptorProvider>>> $methods
     */
    public function __construct(
        public readonly array $methods
    ) {}
}
