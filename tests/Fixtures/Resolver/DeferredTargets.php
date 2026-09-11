<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resolver;

require_once __DIR__ . '/Providers.php';

$GLOBALS['resolver_fixture_loads'] = ($GLOBALS['resolver_fixture_loads'] ?? 0) + 1;

class DeferredTarget
{
    #[FirstProvider]
    public function marked(): void {}
}

class ParameterizedTarget
{
    #[ParameterProvider('one')]
    public function first(): void {}

    #[ParameterProvider('two')]
    public function second(): void {}
}

class MissingAttributeDeclarationTarget
{
    #[NotAnAttributeProvider]
    public function marked(): void {}
}

class WrongTargetDeclarationTarget
{
    #[ClassOnlyProvider]
    public function marked(): void {}
}

class RepeatedNonRepeatableTarget
{
    #[SecondProvider]
    #[SecondProvider]
    public function marked(): void {}
}

class InvalidArgumentsTarget
{
    #[ParameterProvider]
    public function marked(): void {}
}
