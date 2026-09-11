<?php

declare(strict_types=1);

namespace Tests\Process;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProcessRunner;

#[CoversNothing]
final class ClassResolverProcessTest extends \PHPUnit\Framework\TestCase
{
    public function testR15ResolverUsesAScanSnapshotAndAFreshResolverFindsAddedFiles(): void
    {
        $result = ProcessRunner::run([PHP_BINARY, __DIR__ . '/Fixtures/r15_snapshot.php']);
        self::assertFalse($result->timedOut);
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);
        self::assertSame(['before' => false, 'oldAfterAdd' => false, 'freshAfterAdd' => true], json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string,array{string,array<string,mixed>}>
     */
    public static function r16Scenarios(): iterable
    {
        yield 'duplicate FQCN' => ['duplicates', ['edge' => true, 'good' => true]];
        yield 'interface trait enum and conditional declaration' => ['non_classes', ['interface' => false, 'trait' => false, 'enum' => false, 'conditional' => false, 'good' => true]];
        yield 'missing dependency' => ['missing_dependency', ['error' => 'Error', 'good' => true]];
        yield 'syntax error' => ['syntax_error', ['error' => 'ParseError', 'freshGood' => true]];
    }

    /**
     * @param array<string,mixed> $expected
     */
    #[DataProvider('r16Scenarios')]
    public function testR16HazardousFilesFailInAControlledProcessWithoutPoisoningCleanTargets(string $scenario, array $expected): void
    {
        $result = ProcessRunner::run([PHP_BINARY, __DIR__ . '/Fixtures/r16_edge_cases.php', $scenario]);
        self::assertFalse($result->timedOut);
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);
        self::assertSame($expected, json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR));
    }
}
