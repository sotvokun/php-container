<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

#[ClassCallbackMarker]
class CallbackTarget
{
    public function __construct(
        #[ParameterCallbackMarker] public PlainDependency $dependency
    ) {}

    #[Record]
    public function run(): string
    {
        return 'callback';
    }
}
