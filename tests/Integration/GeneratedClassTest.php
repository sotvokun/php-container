<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Ray\Aop\Exception\NotWritableException;
use Tests\Fixtures\Generated\GeneratedLog;
use Tests\Fixtures\Generated\GeneratedTarget;
use Tests\Support\IsolatedTestCase;

#[CoversNothing]
final class GeneratedClassTest extends IsolatedTestCase
{
    private function fixtures(): string
    {
        return dirname(__DIR__) . '/Fixtures/Generated';
    }

    private function configuredContainer(string $generatedDirectory): \Sotvokun\Container\Container
    {
        require_once $this->fixtures() . '/GeneratedFixtures.php';
        $container = $this->container();
        $container->instance(GeneratedLog::class, new GeneratedLog());
        $container->enableAop([$this->fixtures()], $generatedDirectory);

        return $container;
    }

    public function testG01WritableDirectoryGeneratesProxyAndMissingDirectoryIsRejectedDuringConfiguration(): void
    {
        $generated = $this->temporaryDirectory->generatedClasses();
        $container = $this->configuredContainer($generated);
        self::assertSame([], glob($generated . '/*.php') ?: []);

        $target = $container->make(GeneratedTarget::class);
        self::assertInstanceOf(GeneratedTarget::class, $target);
        self::assertSame('ok', $target->run());
        self::assertCount(1, glob($generated . '/*.php') ?: []);

        $missing = $this->temporaryDirectory->path() . '/missing/generated';
        self::assertDirectoryDoesNotExist($missing);
        try {
            $this->configuredContainer($missing);
            self::fail('G01 expected a missing generation directory to be rejected by enableAop().');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('must be an existing directory', $exception->getMessage());
        }
    }

    public function testG01ExistingNonWritableDirectoryIsRejectedWhenPlatformCanRepresentIt(): void
    {
        $directory = $this->temporaryDirectory->path() . '/read-only';
        self::assertTrue(mkdir($directory));
        self::assertTrue(chmod($directory, 0555));

        try {
            if (is_writable($directory)) {
                self::markTestSkipped('This runtime can write to chmod 0555 directories; the permission condition is not representable.');
            }
            $container = $this->configuredContainer($directory);
            $this->expectException(NotWritableException::class);
            $container->make(GeneratedTarget::class);
        } finally {
            chmod($directory, 0755);
        }
    }

    public function testG02EmptyRelativeAndFilePathsAreRejectedDuringConfiguration(): void
    {
        $file = $this->temporaryDirectory->path() . '/a-file';
        self::assertNotFalse(file_put_contents($file, 'not a directory'));

        foreach ([['', 'absolute paths'], ['relative-proxies', 'absolute paths'], [$file, 'existing directory']] as [$path, $message]) {
            try {
                $this->configuredContainer($path);
                self::fail('G02 expected an invalid generation path to be rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testG02AbsoluteSpaceAndUnicodePathGeneratesOnlyInTheSelectedDirectory(): void
    {
        $unicode = $this->temporaryDirectory->path() . '/proxy space-中文';
        self::assertTrue(mkdir($unicode));
        $target = $this->configuredContainer($unicode)->make(GeneratedTarget::class);
        self::assertSame('unicode', $target->run('unicode'));
        self::assertCount(1, glob($unicode . '/*.php') ?: []);
    }

    public function testG02RelativeScanDirectoryIsRejectedDuringConfiguration(): void
    {
        $container = $this->container();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('absolute paths');
        $container->enableAop(['tests/Fixtures/Generated'], $this->temporaryDirectory->generatedClasses());
    }

    public function testG03RepeatedCreationReusesProxyWithoutRedeclarationOrExtraAdvice(): void
    {
        $directory = $this->temporaryDirectory->generatedClasses();
        $container = $this->configuredContainer($directory);
        $log = $container->make(GeneratedLog::class);
        $first = $container->make(GeneratedTarget::class);
        $second = $container->make(GeneratedTarget::class);

        self::assertSame($first::class, $second::class);
        self::assertNotSame($first, $second);
        self::assertSame('first', $first->run('first'));
        self::assertSame('second', $second->run('second'));
        self::assertSame(1, $first->calls);
        self::assertSame(1, $second->calls);
        self::assertSame(['before', 'after', 'before', 'after'], $log->events);
        self::assertCount(1, glob($directory . '/*.php') ?: []);
    }

    public function testG08GeneratedDirectoryInsideScanTreeIsRejectedDuringConfiguration(): void
    {
        require_once $this->fixtures() . '/GeneratedFixtures.php';
        $root = $this->temporaryDirectory->path() . '/scan-tree';
        self::assertTrue(mkdir($root));
        self::assertTrue(copy($this->fixtures() . '/GeneratedTarget.php', $root . '/GeneratedTarget.php'));

        $container = $this->container();
        $nested = $root . '/generated';
        self::assertTrue(mkdir($nested));
        foreach ([$root, $nested] as $generated) {
            try {
                $container->enableAop([$root], $generated);
                self::fail('G08 expected a generation directory inside the scan tree to be rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('must be outside scan directory', $exception->getMessage());
            }
        }

        if (function_exists('symlink')) {
            $link = $this->temporaryDirectory->path() . '/scan-link';
            if (@symlink($root, $link)) {
                try {
                    $container->enableAop([$root], $link . '/generated');
                    self::fail('G08 expected a generation directory below a symlinked scan tree to be rejected.');
                } catch (\InvalidArgumentException $exception) {
                    self::assertStringContainsString('must be outside scan directory', $exception->getMessage());
                } finally {
                    unlink($link);
                }
            }
        }

        $sibling = $this->temporaryDirectory->path() . '/scan-tree-generated';
        self::assertTrue(mkdir($sibling));
        $container->instance(GeneratedLog::class, new GeneratedLog());
        $container->enableAop([$root], $sibling);
        self::assertSame('outside', $container->make(GeneratedTarget::class)->run('outside'));
        self::assertCount(1, glob($sibling . '/*.php') ?: []);
    }
}
