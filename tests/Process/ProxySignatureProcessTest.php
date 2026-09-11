<?php

declare(strict_types=1);

namespace Tests\Process;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ProcessRunner;

#[CoversNothing]
final class ProxySignatureProcessTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return iterable<string, array{string,string}>
     */
    public static function supportedProxyEdges(): iterable
    {
        yield 'S03 self return' => ['self', 'same'];
        yield 'S03 static return' => ['static', 'same'];
        yield 'S04 constant defaults and named call' => ['default_constant', '[["' . PHP_VERSION . '","class-label"],["' . PHP_VERSION . '","named"]]'];
        yield 'S04 enum default' => ['default_enum', '"fast"'];
        yield 'S07 readonly class' => ['readonly', 'ready'];
        yield 'S08 magic methods' => ['magic', '{"invoke":"invoke:x","call":["missing",["a"]]}'];
    }

    /**
     * @return iterable<string, array{string,string}>
     */
    public static function upstreamProxyEdges(): iterable
    {
        yield 'S02 never return' => ['never', 'never target'];
        yield 'S03 parent return' => ['parent', 'same'];
        yield 'S04 default new object' => ['default_new', '"default-object"'];
        yield 'S04 named variadic arguments' => ['default_variadic', '["named",{"rest":"a","extra":"b"}]'];
        yield 'S05 reference parameter' => ['reference_parameter', 'input:target'];
        yield 'S05 reference return' => ['reference_return', 'changed'];
        yield 'S08 member collision has explicit rejection' => ['collision', \InvalidArgumentException::class];
    }

    #[DataProvider('supportedProxyEdges')]
    public function testSupportedIsolatedProxyEdgesMeetTheirRuntimeContracts(string $scenario, string $expected): void
    {
        $this->assertProxyScenario($scenario, $expected);
    }

    #[DataProvider('upstreamProxyEdges')]
    #[Group('upstream')]
    public function testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts(string $scenario, string $expected): void
    {
        $this->assertProxyScenario($scenario, $expected);
    }

    private function assertProxyScenario(string $scenario, string $expected): void
    {
        $result = ProcessRunner::run([PHP_BINARY, __DIR__ . '/Fixtures/signature_hazards.php', $scenario]);
        self::assertFalse($result->timedOut);
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);
        self::assertSame($expected, $result->stdout);
    }
}
