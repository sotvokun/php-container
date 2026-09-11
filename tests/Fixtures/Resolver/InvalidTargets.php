<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resolver;

require_once __DIR__ . '/Providers.php';

final class FinalMarkedTarget
{
    #[FirstProvider]
    public function marked(): void {}
}

abstract class AbstractMarkedTarget
{
    #[FirstProvider]
    public function marked(): void {}
}

class PrivateConstructorMarkedTarget
{
    private function __construct() {}

    #[FirstProvider]
    public function marked(): void {}
}

class StaticMarkedTarget
{
    #[FirstProvider]
    public static function marked(): void {}
}

class FinalMethodMarkedTarget
{
    #[FirstProvider]
    final public function marked(): void {}
}

class ConstructorMarkedTarget
{
    #[FirstProvider]
    public function __construct() {}
}

class DestructorMarkedTarget
{
    #[FirstProvider]
    public function __destruct() {}
}

class NonPublicMarkedTarget
{
    #[FirstProvider]
    private function hidden(): void {}

    #[SecondProvider]
    protected function protectedMethod(): void {}
}

class MixedVisibilityTarget
{
    #[FirstProvider]
    private function hidden(): void {}

    #[SecondProvider]
    public function visible(): void {}
}
