<?php

declare(strict_types=1);

namespace Tests\Process;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Aop\CycleA;
use Tests\Fixtures\Aop\CycleInterceptorTarget;
use Tests\Fixtures\Aop\CycleSelf;
use Tests\Support\ProcessRunner;

#[CoversNothing]
final class ContainerAopProcessTest extends TestCase
{
    public function testC12AndC15CircularAopDependenciesFailPromptlyAndDoNotPoisonContainer(): void
    {
        $script = __DIR__ . '/Fixtures/c12_cycles.php';
        $result = ProcessRunner::run([PHP_BINARY, $script], 3.0);

        self::assertFalse($result->timedOut, 'Circular resolution did not terminate within three seconds.');
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);
        self::assertSame([
            CycleSelf::class => 'circular',
            CycleA::class => 'circular',
            CycleInterceptorTarget::class => 'circular',
            'recovery' => 'ok',
        ], json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR));
    }

    public function testC15NativeContainerSelfResolutionDoesNotProvideAopCircularProtection(): void
    {
        $script = __DIR__ . '/Fixtures/c15_native_self.php';
        $result = ProcessRunner::run([PHP_BINARY, '-d', 'memory_limit=512M', $script], 0.25);

        self::assertTrue($result->timedOut, 'Native self-resolution unexpectedly terminated instead of recursing.');
    }
}
