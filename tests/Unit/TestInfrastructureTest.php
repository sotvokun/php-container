<?php

declare(strict_types=1);

namespace Tests\Unit;

use Composer\InstalledVersions;
use Illuminate\Container\Container as IlluminateContainer;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixtures\Aop\EventRecorder;
use Tests\Fixtures\Aop\GreetingService;
use Tests\Fixtures\Aop\RecordingInterceptor;
use Tests\Support\ContainerComparison;
use Tests\Support\IsolatedTestCase;
use Tests\Support\ProcessRunner;

#[CoversNothing]
final class TestInfrastructureTest extends IsolatedTestCase
{
    public function testT01PhpunitBootstrapLoadsProjectAutoloaderAndSuitesAreConfigured(): void
    {
        self::assertTrue(class_exists(\Sotvokun\Container\Container::class));
        self::assertFileExists(dirname(__DIR__, 2) . '/phpunit.xml');
    }

    public function testT02RealFileFixturesHaveStableClassesAndAreNotBootstrapLoaded(): void
    {
        $bootstrap = dirname(__DIR__) . '/bootstrap.php';
        $code = sprintf('require %s; echo class_exists(%s, false) ? "loaded" : "not-loaded";', var_export($bootstrap, true), var_export(GreetingService::class, true));
        $result = ProcessRunner::run([PHP_BINARY, '-r', $code]);

        self::assertFalse($result->timedOut);
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);
        self::assertSame('not-loaded', $result->stdout);
        self::assertFileExists(dirname(__DIR__) . '/Fixtures/Aop/GreetingService.php');
    }

    public function testT03TemporaryDirectoriesAreUniquePreparedAndRemoved(): void
    {
        $generated = $this->temporaryDirectory->generatedClasses();
        self::assertDirectoryExists($generated);
        self::assertFileExists($this->temporaryDirectory->path() . '/.sotvokun-container-test');
    }

    public function testT04BaselineRecorderAndInterceptorAreSerializable(): void
    {
        $interceptor = new RecordingInterceptor(new EventRecorder());
        $restored = unserialize(serialize($interceptor), ['allowed_classes' => true]);

        self::assertInstanceOf(RecordingInterceptor::class, $restored);
    }

    public function testT05NativeContainerComparisonHelperComparesTheSameBindingBehavior(): void
    {
        ContainerComparison::assertSameObservableBehavior(
            static fn(IlluminateContainer $container) => $container->bind('test.value', static fn() => 'value'),
            static fn(IlluminateContainer $container) => $container->make('test.value'),
        );

        ContainerComparison::assertSameResolutionIdentity(
            static fn(IlluminateContainer $container) => $container->singleton('test.object', static fn() => new \stdClass()),
            static fn(IlluminateContainer $container) => $container->make('test.object'),
        );
    }

    public function testT07RuntimeAndInstalledDependenciesMatchTheTestBaseline(): void
    {
        self::assertGreaterThanOrEqual(80400, PHP_VERSION_ID);

        $contents = file_get_contents(dirname(__DIR__, 2) . '/composer.lock');
        self::assertIsString($contents);
        $lock = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        foreach ([...$lock['packages'], ...$lock['packages-dev']] as $package) {
            $name = $package['name'];
            self::assertTrue(InstalledVersions::isInstalled($name), "Missing locked dependency: {$name}");
            self::assertSame($package['version'], InstalledVersions::getPrettyVersion($name), "Installed version differs from composer.lock: {$name}");
        }
    }
}
