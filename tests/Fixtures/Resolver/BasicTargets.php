<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resolver;

require_once __DIR__ . '/Providers.php';

#[OrdinaryAttribute]
class PlainTarget
{
    #[OrdinaryAttribute]
    public function ordinary(): void {}
}

#[FirstProvider]
class ClassLevelOnlyTarget
{
    public function plain(): void {}
}

class PublicTarget
{
    #[FirstProvider]
    public function marked(): void {}

    public function plain(): void {}
}

class MultipleTarget
{
    #[FirstProvider]
    #[SecondProvider]
    #[FirstProvider]
    public function alpha(): void {}

    #[SecondProvider]
    public function beta(): void {}
}

final class FinalUnmarkedTarget
{
    public function plain(): void {}
}

abstract class AbstractUnmarkedTarget
{
    public function plain(): void {}
}

final class PrivateConstructorUnmarkedTarget
{
    private function __construct() {}

    public function plain(): void {}
}
