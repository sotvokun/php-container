<?php

declare(strict_types=1);

namespace Tests\Process;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Ray\Aop\Exception\NotWritableException;
use Tests\Support\ProcessRunner;
use Tests\Support\TemporaryDirectory;

#[CoversNothing]
final class GeneratedClassProcessTest extends TestCase
{
    private TemporaryDirectory $temporaryDirectory;
    private string $script;

    protected function setUp(): void
    {
        $this->temporaryDirectory = new TemporaryDirectory();
        $this->script = __DIR__ . '/Fixtures/generated_classes.php';
    }

    protected function tearDown(): void
    {
        $this->temporaryDirectory->remove();
    }

    /**
     * @return array<string,mixed>
     */
    private function runProcess(array $arguments, float $timeout = 5.0): array
    {
        $result = ProcessRunner::run([PHP_BINARY, $this->script, ...$arguments], $timeout);
        self::assertFalse($result->timedOut);
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);

        return json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR);
    }

    public function testG04FreshProcessLoadsTheExistingProxyFromDisk(): void
    {
        $directory = $this->temporaryDirectory->generatedClasses();
        $first = $this->runProcess(['G04', $directory]);
        $second = $this->runProcess(['G04', $directory]);

        self::assertSame('G04', $first['value']);
        self::assertSame(['before', 'after'], $first['events']);
        self::assertSame($first['class'], $second['class']);
        self::assertSame($first['hash'], $second['hash']);
        self::assertSame($first['mtime'], $second['mtime']);
        self::assertSame(1, $second['files']);
        self::assertSame(['before', 'after'], $second['events']);
    }

    public function testG05CacheKeyTracksMtimeBindingAndDirectoryButNotSourceContent(): void
    {
        $root = $this->temporaryDirectory->path() . '/source';
        self::assertTrue(mkdir($root));
        $mtime = time() - 20;
        $initial = $this->runProcess(['G05', $root, 'one', (string) $mtime]);
        $sameMtime = $this->runProcess(['G05', $root, 'two', (string) $mtime]);
        $changedMtime = $this->runProcess(['G05', $root, 'three', (string) ($mtime + 5)]);
        $otherDirectory = $this->temporaryDirectory->path() . '/other-generated';
        $differentDirectory = $this->runProcess(['G05', $root, 'four', (string) ($mtime + 5), $otherDirectory]);
        $differentBinding = $this->runProcess(['G05', $root, 'five', (string) ($mtime + 5), $root . '/generated', 'state-marker']);

        self::assertSame($initial['class'], $sameMtime['class'], 'Content-only edits are outside Ray AOP cache invalidation.');
        self::assertSame('two', $sameMtime['value'], 'The cached child still dispatches to the newly loaded parent implementation.');
        self::assertNotSame($sameMtime['class'], $changedMtime['class']);
        self::assertSame(2, $changedMtime['files']);
        self::assertNotSame($changedMtime['class'], $differentDirectory['class']);
        self::assertSame(1, $differentDirectory['files']);
        self::assertNotSame($changedMtime['class'], $differentBinding['class']);
        self::assertSame(3, $differentBinding['files']);
    }

    public function testG06CorruptCacheFailsDiagnosticallyAndACleanDirectoryStillWorks(): void
    {
        $directory = $this->temporaryDirectory->generatedClasses();
        $this->runProcess(['G04', $directory]);
        $files = glob($directory . '/*.php') ?: [];
        self::assertCount(1, $files);

        self::assertNotFalse(file_put_contents($files[0], '<?php // deliberately truncated cache'));
        $truncated = $this->runProcess(['G06-load', $directory]);
        self::assertSame(\AssertionError::class, $truncated['error']);

        self::assertNotFalse(file_put_contents($files[0], '<?php class UnrelatedGeneratedCacheClass {}'));
        $wrongClass = $this->runProcess(['G06-load', $directory]);
        self::assertSame(\AssertionError::class, $wrongClass['error']);

        self::assertTrue(chmod($files[0], 0));
        try {
            clearstatcache(true, $files[0]);
            if (!is_readable($files[0])) {
                $unreadable = $this->runProcess(['G06-load', $directory]);
                self::assertSame(\ErrorException::class, $unreadable['error']);
            }
        } finally {
            chmod($files[0], 0644);
        }

        $disappearing = $this->runProcess(['G06-disappearing', $this->temporaryDirectory->path()]);
        self::assertSame(NotWritableException::class, $disappearing['error']);
        self::assertSame('ok', $disappearing['recovered']);
    }

    #[Group('upstream')]
    public function testG07ConcurrentProcessesPublishOneCompleteReusableProxy(): void
    {
        $directory = $this->temporaryDirectory->generatedClasses();
        $processes = [];
        for ($i = 0; $i < 6; ++$i) {
            $process = proc_open([PHP_BINARY, $this->script, 'G07', $directory], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
            self::assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        $payloads = [];
        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $stderr);
            self::assertSame('', $stderr);
            $payloads[] = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        }

        self::assertCount(1, array_unique(array_column($payloads, 'class')));
        self::assertCount(1, array_unique(array_column($payloads, 'hash')));
        foreach ($payloads as $payload) {
            self::assertSame('G07', $payload['value']);
            self::assertSame(['before', 'after'], $payload['events']);
        }
        self::assertCount(1, glob($directory . '/*.php') ?: []);
        self::assertSame('G07', $this->runProcess(['G07', $directory])['value']);
    }
}
