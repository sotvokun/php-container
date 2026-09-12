<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\BindingResolutionException;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;
use Sotvokun\Container\Container;
use Tests\Fixtures\Aop\AlternateScenarioPortImplementation;
use Tests\Fixtures\Aop\AopComplexDependency;
use Tests\Fixtures\Aop\AopNoConstructor;
use Tests\Fixtures\Aop\AopWithConstructor;
use Tests\Fixtures\Aop\BadProvider;
use Tests\Fixtures\Aop\BadProviderTarget;
use Tests\Fixtures\Aop\BoundAopTarget;
use Tests\Fixtures\Aop\CallbackTarget;
use Tests\Fixtures\Aop\ClassCallbackMarker;
use Tests\Fixtures\Aop\ConstructorResolvesService;
use Tests\Fixtures\Aop\ContextInnerTarget;
use Tests\Fixtures\Aop\ContextInterceptorTarget;
use Tests\Fixtures\Aop\ContextOuterTarget;
use Tests\Fixtures\Aop\ContextTarget;
use Tests\Fixtures\Aop\CountingInterceptor;
use Tests\Fixtures\Aop\DefaultObjectTarget;
use Tests\Fixtures\Aop\DefaultsTarget;
use Tests\Fixtures\Aop\EventRecorder;
use Tests\Fixtures\Aop\GreetingService;
use Tests\Fixtures\Aop\IndependentAfterFailure;
use Tests\Fixtures\Aop\InterfaceTarget;
use Tests\Fixtures\Aop\NestedAopDependency;
use Tests\Fixtures\Aop\OverridesTarget;
use Tests\Fixtures\Aop\ParameterCallbackMarker;
use Tests\Fixtures\Aop\PlainDependency;
use Tests\Fixtures\Aop\PlainService;
use Tests\Fixtures\Aop\RecordingInterceptor;
use Tests\Fixtures\Aop\RequiredScalarTarget;
use Tests\Fixtures\Aop\ScenarioPort;
use Tests\Fixtures\Aop\ScenarioPortImplementation;
use Tests\Fixtures\Aop\SelfBuildingDelegatesBuild;
use Tests\Fixtures\Aop\SelfBuildingMissingFactory;
use Tests\Fixtures\Aop\SelfBuildingNeedsDependency;
use Tests\Fixtures\Aop\SelfBuildingReturnsObject;
use Tests\Fixtures\Aop\SelfBuildingThrows;
use Tests\Fixtures\Aop\ThrowsInConstructor;
use Tests\Fixtures\Aop\ThrowsProviderTarget;
use Tests\Fixtures\Aop\TypedBoth;
use Tests\Fixtures\Aop\TypedParent;
use Tests\Fixtures\Aop\TypedTarget;
use Tests\Fixtures\Aop\UnboundInterceptor;
use Tests\Fixtures\Aop\UnresolvableInterceptorTarget;
use Tests\Fixtures\Aop\VariadicLabelsTarget;
use Tests\Fixtures\Aop\VariadicTarget;
use Tests\Fixtures\AopAlternate\AlternateAopTarget;
use Tests\Support\IsolatedTestCase;

#[CoversNothing]
final class ContainerAopTest extends IsolatedTestCase
{
    private function aopContainer(): Container
    {
        $container = $this->container();
        $container->enableAop([$this->fixtureDirectory()], $this->temporaryDirectory->generatedClasses());
        return $container;
    }

    public function testC18ContainerCanBeExtendedWithoutLosingAopBehavior(): void
    {
        $container = new class extends Container {
            public function extensionMarker(): string
            {
                return 'extended';
            }
        };
        $container->enableAop([$this->fixtureDirectory()], $this->temporaryDirectory->generatedClasses());
        $recorder = new EventRecorder();
        $container->instance(EventRecorder::class, $recorder);

        self::assertSame('extended', $container->extensionMarker());
        self::assertSame('hello Codex', $container->make(GreetingService::class)->greet('Codex'));
        self::assertSame(['before:greet', 'after:greet'], $recorder->events);
    }

    public function testC01WithoutAopMatchesNormalContainerBuildPaths(): void
    {
        $container = $this->container();
        self::assertInstanceOf(PlainService::class, $container->make(PlainService::class));
        self::assertInstanceOf(PlainDependency::class, $container->make(AopWithConstructor::class)->dependency);
        $factoryObject = new \stdClass();
        $container->bind('factory', static fn() => $factoryObject);
        self::assertSame($factoryObject, $container->make('factory'));
        $this->expectException(BindingResolutionException::class);
        $container->make('Tests\Fixtures\Aop\DoesNotExist');
    }

    public function testC02NonTargetsStayOrdinaryAndUnmarkedMethodsAreNotIntercepted(): void
    {
        $container = $this->aopContainer();
        $recorder = new EventRecorder();
        $container->instance(EventRecorder::class, $recorder);
        self::assertSame('plain', $container->make(PlainService::class)->value());
        self::assertSame(\stdClass::class, $container->make(\stdClass::class)::class);
        $service = $container->make(GreetingService::class);
        self::assertSame('plain', $service->plain());
        self::assertSame([], $recorder->events);
    }

    public function testC03AopClassesWithAndWithoutConstructorsAreBuiltOnceAndIntercepted(): void
    {
        AopWithConstructor::$constructed = 0;
        $container = $this->aopContainer();
        $recorder = new EventRecorder();
        $container->instance(EventRecorder::class, $recorder);
        $withoutConstructor = $container->make(AopNoConstructor::class);
        $withConstructor = $container->make(AopWithConstructor::class);
        self::assertInstanceOf(AopNoConstructor::class, $withoutConstructor);
        self::assertInstanceOf(AopWithConstructor::class, $withConstructor);
        self::assertSame('run', $withoutConstructor->run());
        self::assertSame('dependency', $withConstructor->run());
        self::assertSame(1, AopWithConstructor::$constructed);
        self::assertSame(['before:run', 'after:run', 'before:run', 'after:run'], $recorder->events);
    }

    public function testC04ResolvesConcreteInterfaceNestedAndAopDependencies(): void
    {
        $container = $this->aopContainer();
        $container->bind(ScenarioPort::class, ScenarioPortImplementation::class);
        $recorder = new EventRecorder();
        $container->instance(EventRecorder::class, $recorder);
        $service = $container->make(AopComplexDependency::class);
        self::assertInstanceOf(AopComplexDependency::class, $service);
        self::assertInstanceOf(NestedAopDependency::class, $service->nested);
        self::assertInstanceOf(ScenarioPortImplementation::class, $service->port);
        self::assertSame('port:nested', $service->run());
        self::assertSame(['before:run', 'before:run', 'after:run', 'after:run'], $recorder->events);
    }

    public function testC05ContextualBindingsUseOriginalTargetContext(): void
    {
        $container = $this->aopContainer();
        $container->when(ContextTarget::class)->needs(ScenarioPort::class)->give(ScenarioPortImplementation::class);
        $container->when(ContextTarget::class)->needs('$label')->give('context');
        self::assertSame('context:port', $container->make(ContextTarget::class)->run());

        $container->bind(ScenarioPort::class, ScenarioPortImplementation::class);
        $container->when(ContextOuterTarget::class)->needs(ScenarioPort::class)->give(AlternateScenarioPortImplementation::class);
        $container->when(ContextInnerTarget::class)->needs(ScenarioPort::class)->give(ScenarioPortImplementation::class);
        self::assertSame('alternate:port', $container->make(ContextOuterTarget::class)->run());

        $recorder = new EventRecorder();
        $container->instance(EventRecorder::class, $recorder);
        $container->when(ContextInterceptorTarget::class)->needs(ScenarioPort::class)->give(AlternateScenarioPortImplementation::class);
        self::assertSame('alternate', $container->make(ContextInterceptorTarget::class)->run());
        self::assertSame(['interceptor-port:port'], $recorder->events);
    }

    public function testC06MakeWithKeepsFalseyAndObjectOverridesWithoutLeaking(): void
    {
        $container = $this->aopContainer();
        $object = new \stdClass();
        $made = $container->makeWith(OverridesTarget::class, ['text' => '', 'zero' => 0, 'flag' => false, 'nullable' => null, 'object' => $object]);
        self::assertSame('', $made->text);
        self::assertSame(0, $made->zero);
        self::assertFalse($made->flag);
        self::assertNull($made->nullable);
        self::assertSame($object, $made->object);
        $container->when(DefaultsTarget::class)->needs('$text')->give('context');
        self::assertSame('override', $container->makeWith(DefaultsTarget::class, ['text' => 'override'])->text);
        self::assertSame('context', $container->make(DefaultsTarget::class)->text);
        try {
            $container->make(OverridesTarget::class);
            self::fail('Expected missing constructor parameters.');
        } catch (BindingResolutionException) {
            self::assertSame('context', $container->make(DefaultsTarget::class)->text);
        }
    }

    public function testC07DefaultsNullableAndUnresolvableDependenciesFollowContainerRules(): void
    {
        $container = $this->aopContainer();
        $defaults = $container->make(DefaultsTarget::class);
        self::assertSame('default', $defaults->text);
        self::assertNull($defaults->dependency);
        self::assertNull($defaults->nullable);
        self::assertInstanceOf(PlainDependency::class, $container->make(DefaultObjectTarget::class)->dependency);
        $container->when(DefaultsTarget::class)->needs('$text')->give('bound');
        $container->when(DefaultsTarget::class)->needs(PlainDependency::class)->give(PlainDependency::class);
        $bound = $container->make(DefaultsTarget::class);
        self::assertSame('bound', $bound->text);
        self::assertInstanceOf(PlainDependency::class, $bound->dependency);
        foreach ([RequiredScalarTarget::class, InterfaceTarget::class] as $unresolvable) {
            try {
                $container->make($unresolvable);
                self::fail("Expected {$unresolvable} to be unresolvable.");
            } catch (BindingResolutionException) {
            }
        }
    }

    public function testC08VariadicAndContextualArrayBindingsMatchIlluminateBehavior(): void
    {
        $aop = $this->aopContainer();
        $native = $this->illuminateContainer();
        foreach ([$aop, $native] as $container) {
            $container->when(VariadicTarget::class)->needs(ScenarioPort::class)->give([
                ScenarioPortImplementation::class,
                AlternateScenarioPortImplementation::class,
            ]);
            $container->when(VariadicLabelsTarget::class)->needs('$labels')->give(['first', 'second']);
        }

        $expected = $native->make(VariadicTarget::class);
        $actual = $aop->make(VariadicTarget::class);
        self::assertSame($expected->portNames(), $actual->portNames());
        self::assertSame(
            $native->make(VariadicLabelsTarget::class)->labels,
            $aop->make(VariadicLabelsTarget::class)->labels,
        );
        $emptyAop = $this->aopContainer();
        self::assertSame([], $emptyAop->make(VariadicTarget::class)->ports);
        self::assertSame([], $emptyAop->make(VariadicLabelsTarget::class)->labels);
        self::assertSame($native->make(TypedTarget::class)->describe(), $aop->make(TypedTarget::class)->describe());
        $intersection = new TypedBoth();
        $selfValue = new TypedTarget();
        $parentValue = new TypedParent();
        $typedParameters = [
            'union' => 0,
            'intersection' => $intersection,
            'selfValue' => $selfValue,
            'parentValue' => $parentValue,
        ];
        $nativeTyped = $native->makeWith(TypedTarget::class, $typedParameters);
        $aopTyped = $aop->makeWith(TypedTarget::class, $typedParameters);
        self::assertSame($nativeTyped->describe(), $aopTyped->describe());
        self::assertSame($intersection, $aopTyped->intersection);
        self::assertSame($selfValue, $aopTyped->selfValue);
        self::assertSame($parentValue, $aopTyped->parentValue);
    }

    public function testC09BindingsAliasesAndSingletonsPreserveIdentityWithoutAccumulatingInterceptors(): void
    {
        $container = $this->aopContainer();
        $recorder = new EventRecorder();
        $container->instance(EventRecorder::class, $recorder);
        $container->singleton('singleton-target', BoundAopTarget::class);
        $container->alias('singleton-target', 'target-alias');
        $first = $container->make('target-alias');
        $second = $container->make('singleton-target');
        self::assertSame($first, $second);
        $first->run();
        $second->run();
        self::assertSame(['before:run', 'after:run', 'before:run', 'after:run'], $recorder->events);

        $container->bind(ScenarioPort::class, BoundAopTarget::class);
        $transientOne = $container->make(ScenarioPort::class);
        $transientTwo = $container->make(ScenarioPort::class);
        self::assertInstanceOf(BoundAopTarget::class, $transientOne);
        self::assertInstanceOf(BoundAopTarget::class, $transientTwo);
        self::assertNotSame($transientOne, $transientTwo);
        $transientOne->run();
        $transientTwo->run();
        self::assertSame(8, count($recorder->events));
    }

    public function testC10InstancesAndClosureResultsAreNotRetrofittedWhileNestedMakesAre(): void
    {
        $container = $this->aopContainer();
        $existing = new AopNoConstructor();
        $container->instance('existing', $existing);
        self::assertSame($existing, $container->make('existing'));
        $container->bind('factory', static fn() => new AopNoConstructor());
        self::assertSame(AopNoConstructor::class, $container->make('factory')::class);
        $container->bind('nested', fn(Container $c) => $c->make(AopNoConstructor::class));
        self::assertNotSame(AopNoConstructor::class, $container->make('nested')::class);
    }

    public function testC11ConstructorDependencyFailureCanBeCorrectedAndRetried(): void
    {
        $container = $this->aopContainer();

        try {
            $container->make(InterfaceTarget::class);
            self::fail('Expected an unbound interface failure.');
        } catch (BindingResolutionException $exception) {
            self::assertStringContainsString(ScenarioPort::class, $exception->getMessage());
        }

        $container->bind(ScenarioPort::class, ScenarioPortImplementation::class);
        self::assertSame('port', $container->make(InterfaceTarget::class)->run());
        self::assertSame('ok', $container->make(IndependentAfterFailure::class)->run());
    }

    public function testC11ConstructorExceptionIsPreservedAndContainerRecovers(): void
    {
        $container = $this->aopContainer();

        try {
            $container->make(ThrowsInConstructor::class);
            self::fail('Expected the constructor exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('constructor', $exception->getMessage());
        }

        self::assertSame('ok', $container->make(IndependentAfterFailure::class)->run());
    }

    public function testC11ProviderExceptionIsPreservedAndContainerRecovers(): void
    {
        $container = $this->aopContainer();

        try {
            $container->make(ThrowsProviderTarget::class);
            self::fail('Expected the provider exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('provider', $exception->getMessage());
        }

        self::assertSame('ok', $container->make(IndependentAfterFailure::class)->run());
    }

    public function testC11InterceptorResolutionFailureCanBeCorrectedAndRetried(): void
    {
        $container = $this->aopContainer();

        try {
            $container->make(UnresolvableInterceptorTarget::class);
            self::fail('Expected interceptor resolution to fail.');
        } catch (BindingResolutionException $exception) {
            self::assertStringContainsString(UnboundInterceptor::class, $exception->getMessage());
        }

        $container->bind(UnboundInterceptor::class, CountingInterceptor::class);
        $target = $container->make(UnresolvableInterceptorTarget::class);
        self::assertSame('resolved', $target->run());
        self::assertSame('ok', $container->make(IndependentAfterFailure::class)->run());
    }

    public function testC11InvalidInterceptorIsIdentifiedAndContainerRecovers(): void
    {
        $container = $this->aopContainer();

        try {
            $container->make(BadProviderTarget::class);
            self::fail('Expected interceptor validation to fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString(BadProvider::class, $exception->getMessage());
        }

        self::assertSame('ok', $container->make(IndependentAfterFailure::class)->run());
    }

    public function testC13ResolvingCallbacksReceiveAopObjectOnceAndFailuresDoNotPoisonContainer(): void
    {
        $container = $this->aopContainer();
        $seen = [];
        $container->resolving(CallbackTarget::class, static function (CallbackTarget $object) use (&$seen): void {
            $seen[] = ['resolving', $object];
        });
        $container->afterResolving(CallbackTarget::class, static function (CallbackTarget $object) use (&$seen): void {
            $seen[] = ['after', $object];
        });
        $container->afterResolvingAttribute(ClassCallbackMarker::class, static function ($attribute, object $object) use (&$seen): void {
            $seen[] = ['class-attribute', $object];
        });
        $container->afterResolvingAttribute(ParameterCallbackMarker::class, static function ($attribute, object $object) use (&$seen): void {
            $seen[] = ['parameter-attribute', $object];
        });
        $object = $container->make(CallbackTarget::class);
        self::assertSame([
            ['parameter-attribute', $object->dependency],
            ['class-attribute', $object],
            ['resolving', $object],
            ['after', $object],
        ], $seen);
        self::assertSame('callback', $object->run());

        $throwing = $this->aopContainer();
        $throwing->resolving(CallbackTarget::class, static fn() => throw new RuntimeException('callback'));
        try {
            $throwing->make(CallbackTarget::class);
            self::fail('Expected resolving callback exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('callback', $exception->getMessage());
        }
        self::assertSame('ok', $throwing->make(IndependentAfterFailure::class)->run());

        $callbackCounts = [];
        $dependency = new PlainDependency();
        foreach (['aop' => $this->aopContainer(), 'native' => $this->illuminateContainer()] as $name => $candidate) {
            $callbackCounts[$name] = ['resolving' => 0, 'after' => 0];
            $candidate->resolving(CallbackTarget::class, static function () use (&$callbackCounts, $name): void {
                ++$callbackCounts[$name]['resolving'];
            });
            $candidate->afterResolving(CallbackTarget::class, static function () use (&$callbackCounts, $name): void {
                ++$callbackCounts[$name]['after'];
            });
            self::assertSame($dependency, $candidate->makeWith(CallbackTarget::class, ['dependency' => $dependency])->dependency);
        }
        self::assertSame($callbackCounts['native'], $callbackCounts['aop']);
    }

    public function testC14SelfBuildingFactoriesHaveObservableParentContainerBehavior(): void
    {
        $container = $this->aopContainer();
        $container->instance(Container::class, $container);
        self::assertSame(SelfBuildingReturnsObject::class, $container->make(SelfBuildingReturnsObject::class)::class);
        $withDependency = $container->make(SelfBuildingNeedsDependency::class);
        $delegated = $container->make(SelfBuildingDelegatesBuild::class);
        self::assertSame(SelfBuildingNeedsDependency::class, $withDependency::class);
        self::assertSame(SelfBuildingDelegatesBuild::class, $delegated::class);
        self::assertSame('dependency', $withDependency->run());
        self::assertSame('delegated', $delegated->run());
        foreach ([$container, $this->illuminateContainer()] as $candidate) {
            try {
                $candidate->make(SelfBuildingThrows::class);
                self::fail('Expected self-building factory exception.');
            } catch (RuntimeException $exception) {
                self::assertSame('self-building', $exception->getMessage());
            }
            try {
                $candidate->make(SelfBuildingMissingFactory::class);
                self::fail('Expected missing newInstance failure.');
            } catch (BindingResolutionException $exception) {
                self::assertStringContainsString(SelfBuildingMissingFactory::class, $exception->getMessage());
            }
        }
    }

    public function testC15ConstructorDependencyResolutionKeepsContainerUsable(): void
    {
        $container = $this->aopContainer();
        $container->instance(IlluminateContainer::class, $container);
        $container->bind(ScenarioPort::class, ScenarioPortImplementation::class);
        $container->when(ConstructorResolvesService::class)->needs(ScenarioPort::class)->give(AlternateScenarioPortImplementation::class);
        $service = $container->make(ConstructorResolvesService::class);
        self::assertSame('dependency:alternate', $service->run());
        self::assertSame(ConstructorResolvesService::class, $service->constructingContext);

        $native = $this->illuminateContainer();
        $native->instance(IlluminateContainer::class, $native);
        $native->bind(ScenarioPort::class, ScenarioPortImplementation::class);
        $native->when(ConstructorResolvesService::class)->needs(ScenarioPort::class)->give(AlternateScenarioPortImplementation::class);
        $nativeService = $native->make(ConstructorResolvesService::class);
        self::assertSame('dependency:port', $nativeService->run());
        self::assertNull($nativeService->constructingContext);
        self::assertSame('ok', $container->make(IndependentAfterFailure::class)->run());
    }

    public function testC16ReplacingAopConfigurationOnlyAffectsFutureBuilds(): void
    {
        $container = $this->aopContainer();
        $container->singleton(GreetingService::class);
        $existing = $container->make(GreetingService::class);
        self::assertNotSame(GreetingService::class, $existing::class);

        $secondGeneratedDirectory = $this->temporaryDirectory->path() . DIRECTORY_SEPARATOR . 'generated-two';
        mkdir($secondGeneratedDirectory);
        $container->enableAop(
            [dirname(__DIR__) . '/Fixtures/AopAlternate'],
            $secondGeneratedDirectory,
        );
        self::assertSame($existing, $container->make(GreetingService::class));
        $container->forgetInstance(GreetingService::class);
        self::assertSame(GreetingService::class, $container->make(GreetingService::class)::class);
        self::assertNotSame(AlternateAopTarget::class, $container->make(AlternateAopTarget::class)::class);
        $container->scoped('scoped-alternate', AlternateAopTarget::class);
        $scoped = $container->make('scoped-alternate');
        self::assertSame($scoped, $container->make('scoped-alternate'));
        $container->forgetScopedInstances();
        self::assertNotSame($scoped, $container->make('scoped-alternate'));
    }

    public function testC17ContainersWithSharedProxyDirectoryKeepInterceptorBindingsIsolated(): void
    {
        $directory = $this->temporaryDirectory->generatedClasses();
        $left = $this->container();
        $right = $this->container();
        $left->enableAop([$this->fixtureDirectory()], $directory);
        $right->enableAop([$this->fixtureDirectory()], $directory);
        $leftRecorder = new EventRecorder();
        $rightRecorder = new EventRecorder();
        $left->instance(EventRecorder::class, $leftRecorder);
        $right->instance(EventRecorder::class, $rightRecorder);
        $leftInterceptor = new RecordingInterceptor($leftRecorder);
        $rightInterceptor = new RecordingInterceptor($rightRecorder);
        self::assertNotSame($leftInterceptor, $rightInterceptor);
        $left->instance(RecordingInterceptor::class, $leftInterceptor);
        $right->instance(RecordingInterceptor::class, $rightInterceptor);
        $leftTarget = $left->make(AopNoConstructor::class);
        $rightTarget = $right->make(AopNoConstructor::class);
        self::assertNotSame($leftTarget, $rightTarget);
        self::assertSame($leftTarget::class, $rightTarget::class);
        $leftTarget->run();
        $rightTarget->run();
        $leftTarget->run();
        self::assertSame(['before:run', 'after:run', 'before:run', 'after:run'], $leftRecorder->events);
        self::assertSame(['before:run', 'after:run'], $rightRecorder->events);
    }
}
