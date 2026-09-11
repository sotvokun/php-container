<?php

declare(strict_types=1);

namespace Tests\Process;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Support\ProcessRunner;

#[CoversNothing]
final class ProcessRunnerTest extends TestCase
{
    public function testT06ChildProcessHelperCapturesOutputExitCodeAndStderr(): void
    {
        $result = ProcessRunner::run([PHP_BINARY, '-r', 'fwrite(STDERR, "failure"); echo "output"; exit(7);']);

        self::assertFalse($result->timedOut);
        self::assertSame('output', $result->stdout);
        self::assertSame('failure', $result->stderr);
        self::assertSame(7, $result->exitCode);
    }

    public function testT06ChildProcessHelperTerminatesTimedOutWork(): void
    {
        $result = ProcessRunner::run([PHP_BINARY, '-r', 'usleep(500000);'], 0.01);

        self::assertTrue($result->timedOut);
    }
}
