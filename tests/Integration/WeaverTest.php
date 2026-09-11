<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;
use Sotvokun\Container\Aop\ClassResolver;
use Sotvokun\Container\Aop\Weaver;
use Tests\Fixtures\Weaver\BadReturnTarget;
use Tests\Fixtures\Weaver\ClosureInterceptor;
use Tests\Fixtures\Weaver\ConfigurableProvider;
use Tests\Fixtures\Weaver\ConfigurableTarget;
use Tests\Fixtures\Weaver\ConstructorTarget;
use Tests\Fixtures\Weaver\ContainerInterceptor;
use Tests\Fixtures\Weaver\CountingInterceptor;
use Tests\Fixtures\Weaver\CountingTarget;
use Tests\Fixtures\Weaver\Counts;
use Tests\Fixtures\Weaver\EmptyTarget;
use Tests\Fixtures\Weaver\FirstProvider;
use Tests\Fixtures\Weaver\InstanceProvider;
use Tests\Fixtures\Weaver\Log;
use Tests\Fixtures\Weaver\MixedProvider;
use Tests\Fixtures\Weaver\NamedInterceptor;
use Tests\Fixtures\Weaver\OrderingTarget;
use Tests\Fixtures\Weaver\PlainTarget;
use Tests\Fixtures\Weaver\ProviderTarget;
use Tests\Fixtures\Weaver\ResourceInterceptor;
use Tests\Fixtures\Weaver\SecondProvider;
use Tests\Fixtures\Weaver\StatefulInterceptor;
use Tests\Fixtures\Weaver\ThrowingSerializeInterceptor;
use Tests\Fixtures\Weaver\ThrowsTarget;
use Tests\Support\IsolatedTestCase;

#[CoversNothing]
final class WeaverTest extends IsolatedTestCase
{
    private function fixtures(): string
    {
        return dirname(__DIR__) . '/Fixtures/Weaver';
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once $this->fixtures() . '/WeaverFixtures.php';
    }

    private function weaver(?\Illuminate\Container\Container $container = null): Weaver
    {
        require_once $this->fixtures() . '/WeaverFixtures.php';
        return new Weaver($container ?? $this->illuminateContainer(), new ClassResolver([$this->fixtures()]), $this->temporaryDirectory->generatedClasses());
    }

    public function testW01NewInstancePassesConstructorArgumentsAndWeaveDoesNotConstruct(): void
    {
        ConstructorTarget::$constructed = 0;
        $container = $this->illuminateContainer();
        $log = new Log();
        $container->instance(Log::class, $log);
        $weaver = $this->weaver($container);
        $generated = $weaver->weave(ConstructorTarget::class);
        self::assertTrue(is_a($generated, ConstructorTarget::class, true));
        self::assertSame(0, ConstructorTarget::$constructed);
        $object = $weaver->newInstance(ConstructorTarget::class, ['passed']);
        self::assertInstanceOf(ConstructorTarget::class, $object);
        self::assertSame('passed', $object->run());
        self::assertSame(1, ConstructorTarget::$constructed);
        self::assertSame(['class:before:run', 'class:after:run'], $log->events);
    }

    public function testW02ProvidersAcceptClassNamesInstancesAndMixedListsInOrder(): void
    {
        $container = $this->illuminateContainer();
        $log = new Log();
        $container->instance(Log::class, $log);
        $instance = new NamedInterceptor($log, 'instance');
        InstanceProvider::$instance = $instance;
        MixedProvider::$instance = $instance;
        $object = $this->weaver($container)->newInstance(ProviderTarget::class, []);
        self::assertSame('class', $object->byClass());
        self::assertSame('instance', $object->byInstance());
        self::assertSame('mixed', $object->mixed());
        self::assertSame(['class:before:byClass', 'class:after:byClass', 'instance:before:byInstance', 'instance:after:byInstance', 'class:before:mixed', 'instance:before:mixed', 'instance:after:mixed', 'class:after:mixed'], $log->events);
        self::assertSame($instance, InstanceProvider::$instance);
    }

    public function testW03MultipleAttributesNestInDeclarationOrderAndMethodsAreIsolated(): void
    {
        $log = new Log();
        FirstProvider::$items = [new NamedInterceptor($log, 'A')];
        SecondProvider::$items = [new NamedInterceptor($log, 'B')];
        $object = $this->weaver()->newInstance(OrderingTarget::class, [$log]);
        self::assertSame('first', $object->first());
        self::assertSame('second', $object->second());
        self::assertSame(['A:before:first', 'B:before:first', 'target:first', 'B:after:first', 'A:after:first', 'B:before:second', 'target:second', 'B:after:second'], $log->events);
    }

    public function testW04EmptyProviderExecutesOriginalExactlyOnce(): void
    {
        $object = $this->weaver()->newInstance(EmptyTarget::class, []);
        self::assertSame('unchanged', $object->run());
        self::assertSame(1, $object->calls);
    }

    public function testW05InvalidProviderValuesAreRejectedAndResolutionErrorsSurvive(): void
    {
        foreach ([new \stdClass(), 7, null] as $invalid) {
            ConfigurableProvider::$items = [$invalid];
            try {
                $this->weaver()->weave(ConfigurableTarget::class);
                self::fail('Expected invalid interceptor rejection.');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString(ConfigurableProvider::class, $e->getMessage());
            }
        }
        ConfigurableProvider::$items = [\stdClass::class];
        $this->expectException(InvalidArgumentException::class);
        $this->weaver()->weave(ConfigurableTarget::class);
    }

    public function testW05UnboundInterceptorInterfaceKeepsContainerException(): void
    {
        ConfigurableProvider::$items = [\Tests\Fixtures\Aop\UnboundInterceptor::class];
        $this->expectException(BindingResolutionException::class);
        $this->weaver()->weave(ConfigurableTarget::class);
    }

    public function testW06ProviderFailuresPropagateAndDoNotPoisonRetry(): void
    {
        try {
            $this->weaver()->weave(ThrowsTarget::class);
            self::fail('Expected provider exception.');
        } catch (RuntimeException $e) {
            self::assertSame('provider failed', $e->getMessage());
        }
        try {
            $this->weaver()->weave(BadReturnTarget::class);
            self::fail('Expected return type error.');
        } catch (\TypeError) {
            self::assertTrue(true);
        }
        $log = new Log();
        ConfigurableProvider::$items = [new NamedInterceptor($log, 'retry')];
        $object = $this->weaver()->newInstance(ConfigurableTarget::class, []);
        self::assertSame('ok', $object->run());
        self::assertSame(['retry:before:run', 'retry:after:run'], $log->events);
    }

    public function testW07EveryWeaveAndNewInstanceResolvesTransientInterceptorsWhileSingletonIsReused(): void
    {
        $container = $this->illuminateContainer();
        $counts = new Counts();
        $container->instance(Counts::class, $counts);
        $weaver = $this->weaver($container);
        $weaver->weave(CountingTarget::class);
        $weaver->newInstance(CountingTarget::class, []);
        $weaver->newInstance(CountingTarget::class, []);
        self::assertSame(3, $counts->values['created']);
        $counts->values['created'] = 0;
        $container->singleton(CountingInterceptor::class);
        $weaver = $this->weaver($container);
        $weaver->weave(CountingTarget::class);
        $weaver->newInstance(CountingTarget::class, []);
        self::assertSame(1, $counts->values['created']);
    }

    public function testW08NonSerializableInterceptorStateProducesObservableSerializationFailures(): void
    {
        foreach ([new ClosureInterceptor(), new ThrowingSerializeInterceptor()] as $interceptor) {
            ConfigurableProvider::$items = [$interceptor];
            try {
                $this->weaver()->weave(ConfigurableTarget::class);
                self::fail('Expected serialization failure.');
            } catch (\Throwable $e) {
                self::assertTrue($e instanceof RuntimeException || $e instanceof \Exception);
            }
        }
        ConfigurableProvider::$items = [new ResourceInterceptor()];
        self::assertTrue(is_a($this->weaver()->weave(ConfigurableTarget::class), ConfigurableTarget::class, true));
        ConfigurableProvider::$items = [new ContainerInterceptor($this->illuminateContainer())];
        self::assertTrue(is_a($this->weaver()->weave(ConfigurableTarget::class), ConfigurableTarget::class, true));
    }

    public function testW09SerializedInterceptorStateCanSelectDifferentGeneratedClassesWithoutLeakingInstances(): void
    {
        $weaver = $this->weaver();
        $first = new StatefulInterceptor();
        ConfigurableProvider::$items = [$first];
        $one = $weaver->newInstance(ConfigurableTarget::class, []);
        $one->run();
        $filesBeforeStateChange = count(glob($this->temporaryDirectory->generatedClasses() . '/*.php') ?: []);
        $changedState = $weaver->newInstance(ConfigurableTarget::class, []);
        $changedState->run();
        $filesAfterStateChange = count(glob($this->temporaryDirectory->generatedClasses() . '/*.php') ?: []);
        self::assertNotSame($one::class, $changedState::class);
        self::assertGreaterThan($filesBeforeStateChange, $filesAfterStateChange);
        self::assertSame(2, $first->calls);

        $second = new StatefulInterceptor();
        ConfigurableProvider::$items = [$second];
        $isolated = $weaver->newInstance(ConfigurableTarget::class, []);
        $isolated->run();
        self::assertSame(1, $second->calls);
        self::assertSame(2, $first->calls);
        self::assertNotSame($one, $isolated);
    }

    public function testW10NonTargetsFailButStandaloneWeaverMatchesContainerEntryPoint(): void
    {
        foreach (['weave', 'newInstance'] as $method) {
            try {
                $this->weaver()->{$method}(PlainTarget::class, ...($method === 'newInstance' ? [[]] : []));
                self::fail('Expected non-target failure.');
            } catch (LogicException) {
                self::assertTrue(true);
            }
        }
        $container = $this->illuminateContainer();
        $log = new Log();
        $container->instance(Log::class, $log);
        self::assertSame('value', $this->weaver($container)->newInstance(ConstructorTarget::class, ['value'])->run());
    }

    public function testW11WeaveOnlyCreatesAnUnboundSubclassWhileNewInstanceBindsInterceptors(): void
    {
        $container = $this->illuminateContainer();
        $log = new Log();
        $container->instance(Log::class, $log);
        $weaver = $this->weaver($container);
        $class = $weaver->weave(ConstructorTarget::class);
        $manual = new $class('manual');
        try {
            @$manual->run();
            self::fail('An instance created manually must not masquerade as fully bound AOP.');
        } catch (\TypeError) {
            self::assertSame([], $log->events);
        }
        self::assertSame('managed', $weaver->newInstance(ConstructorTarget::class, ['managed'])->run());
        self::assertSame(['class:before:run', 'class:after:run'], $log->events);
    }
}
