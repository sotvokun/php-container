<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Contracts\Container\BindingResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use Sotvokun\Container\Attributes\Lazy;
use Sotvokun\Container\Container;
use Sotvokun\Container\Lazy\UnresolvableLazyDependencyException;
use Tests\Fixtures\Weaver\CountingTarget;
use Tests\Fixtures\Weaver\Counts;
use Tests\Fixtures\Weaver\StatefulLazyAopTarget;
use Tests\Support\IsolatedTestCase;

interface LazyPort
{
    public function value(string $suffix = '!'): string;
}

class LazyService implements LazyPort
{
    public static int $constructions = 0;
    public string $name = 'lazy';

    public function __construct()
    {
        self::$constructions++;
    }

    public function value(string $suffix = '!'): string
    {
        return $this->name . $suffix;
    }
}

class AlternateLazyService extends LazyService
{
    public function value(string $suffix = '!'): string
    {
        return 'alternate' . $suffix;
    }
}

class LazyConsumer
{
    public function __construct(
        #[Lazy] public LazyPort $service
    ) {}
}

final class InheritedLazyConsumer extends LazyConsumer {}

final class ExplicitLazyConsumer
{
    public function __construct(
        #[Lazy(LazyPort::class)] public LazyPort $service
    ) {}
}

final class AliasLazyConsumer
{
    public function __construct(
        #[Lazy('lazy.port.alias')] public LazyPort $service
    ) {}
}

final class LazyAopConsumer
{
    public function __construct(
        #[Lazy] public CountingTarget $target
    ) {}
}

final class StatefulLazyAopConsumer
{
    public function __construct(
        #[Lazy] public StatefulLazyAopTarget $target
    ) {}
}

final class InternalClassLazyConsumer
{
    public function __construct(
        #[Lazy] public \DateTimeImmutable $value
    ) {}
}

abstract class AbstractLazyService
{
    public string $value = 'initialized';
}

final class ConcreteLazyService extends AbstractLazyService {}

final class AbstractLazyConsumer
{
    public function __construct(
        #[Lazy] public AbstractLazyService $service
    ) {}
}

enum LazyChoice
{
    case First;
}

final class EnumLazyConsumer
{
    public function __construct(
        #[Lazy] public LazyChoice $service
    ) {}
}

class LazyDate extends \DateTimeImmutable {}

class IndirectLazyDate extends LazyDate {}

class LazyStdClass extends \stdClass
{
    public string $value;

    public function __construct()
    {
        $this->value = 'initialized';
    }
}

class IndirectLazyStdClass extends LazyStdClass {}

final class LazyStdClassConsumer
{
    public function __construct(
        #[Lazy] public \stdClass $value
    ) {}
}

final class VariadicLazyConsumer
{
    public function __construct(#[Lazy] LazyPort ...$items) {}
}

class RelativeTypeLazyConsumer
{
    public function __construct(#[Lazy] self|null $value = null) {}
}

class RelativeTypeLazyParent {}

class ParentTypeLazyConsumer extends RelativeTypeLazyParent
{
    public function __construct(#[Lazy] parent|null $value = null) {}
}

final class InspectableLazyContainer extends Container
{
    public function resolutionState(): array
    {
        return [$this->buildStack, $this->with];
    }
}

#[CoversClass(Container::class)]
final class LazyInjectionTest extends IsolatedTestCase
{
    public function testDisabledLazyInjectionReportsTheConsumerAndEnableMethod(): void
    {
        $container = $this->container();
        $container->bind(LazyPort::class, LazyService::class);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Cannot lazily inject [<parameter type>] into ' . LazyConsumer::class . '::$service: lazy injection is not configured; call enableLazyInjection() first.');
        $container->make(LazyConsumer::class);
    }

    public function testNativeContainerWithoutHandlerRejectsLazyAttribute(): void
    {
        $container = $this->illuminateContainer();
        $container->bind(LazyPort::class, LazyService::class);

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('#[Lazy] requires Sotvokun\\Container\\Container.');
        $container->make(LazyConsumer::class);
    }

    public function testRegisteredHandlerReceivesParameterAndOverridesAttributeFallback(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $service = new LazyService();
        $container->whenHasAttribute(Lazy::class, static function (Lazy $attribute, Container $actualContainer, \ReflectionParameter $parameter) use ($container, $service): LazyService {
            self::assertSame($container, $actualContainer);
            self::assertSame(LazyPort::class, $attribute->resolutionKey);
            self::assertSame('service', $parameter->getName());
            return $service;
        });

        self::assertSame($service, $container->make(ExplicitLazyConsumer::class)->service);
    }

    public function testRepeatedEnablementAndSeparateContainersKeepIndependentResolvers(): void
    {
        $first = $this->container();
        $second = $this->container();
        $first->enableLazyInjection();
        $first->bind(LazyPort::class, LazyService::class);
        $pending = $first->make(LazyConsumer::class)->service;
        $first->enableLazyInjection();
        $second->enableLazyInjection();
        $second->bind(LazyPort::class, AlternateLazyService::class);

        self::assertSame(0, LazyService::$constructions);
        self::assertSame('alternate!', $second->make(LazyConsumer::class)->service->value());
        self::assertSame('lazy!', $pending->value());
        self::assertSame('lazy!', $first->make(LazyConsumer::class)->service->value());
    }

    public function testFailedInitializationRestoresContextAndCanBeRetried(): void
    {
        $container = new InspectableLazyContainer();
        $container->enableLazyInjection();
        $container->bind(LazyPort::class, LazyService::class);
        $container->when(InheritedLazyConsumer::class)->needs(LazyPort::class)->give(AlternateLazyService::class);
        $attempts = 0;
        $container->resolving(LazyPort::class, static function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw new \RuntimeException('initialization failed');
            }
        });
        $service = $container->make(InheritedLazyConsumer::class)->service;
        $before = $container->resolutionState();
        $reflection = new \ReflectionClass($service);
        try {
            $reflection->initializeLazyObject($service);
            self::fail('The first initialization must fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('initialization failed', $exception->getMessage());
        }
        self::assertSame($before, $container->resolutionState());
        self::assertTrue($reflection->isUninitializedLazyObject($service));
        $reflection->initializeLazyObject($service);
        self::assertSame('alternate!', $service->value());
        self::assertSame($before, $container->resolutionState());
        self::assertSame('lazy!', $container->make(LazyPort::class)->value());
    }

    protected function setUp(): void
    {
        parent::setUp();
        LazyService::$constructions = 0;
    }

    // L01: 类绑定延迟构建与容器生命周期
    public function testClassBindingIsActuallyDelayedAndUsesNormalContainerLifecycle(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $container->singleton(LazyPort::class, LazyService::class);
        $callbacks = 0;
        $container->resolving(LazyPort::class, static function () use (&$callbacks): void {
            $callbacks++;
        });

        $consumer = $container->make(LazyConsumer::class);
        self::assertSame(0, LazyService::$constructions);
        self::assertInstanceOf(LazyPort::class, $consumer->service);
        self::assertSame('lazy?', $consumer->service->value('?'));
        self::assertSame(1, LazyService::$constructions);
        self::assertSame(1, $callbacks);
        self::assertSame($container->make(LazyPort::class), (new \ReflectionClass($consumer->service))->initializeLazyObject($consumer->service));
    }

    // L02: 显式参数覆盖优先级与 factory 拒绝
    public function testExplicitParameterOverrideWinsAndFactoryStrategiesAreAppliedAtConsumerResolution(): void
    {
        $override = new LazyService();
        $container = $this->container();
        $container->enableLazyInjection();
        $container->bind(LazyPort::class, static fn(): LazyPort => new LazyService());
        self::assertSame($override, $container->makeWith(ExplicitLazyConsumer::class, ['service' => $override])->service);

        $this->expectException(BindingResolutionException::class);
        $container->make(ExplicitLazyConsumer::class);
    }

    // L03: Unsupported factories must never execute.
    public function testUnsupportedFactoryIsRejectedWithoutExecutingUserCode(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $calls = 0;
        $container->bind(LazyPort::class, static function () use (&$calls): LazyPort {
            $calls++;
            return new LazyService();
        });
        try {
            $container->make(LazyConsumer::class);
            self::fail('The factory should have been rejected.');
        } catch (BindingResolutionException $exception) {
            self::assertStringContainsString(LazyConsumer::class . '::$service', $exception->getMessage());
            self::assertSame(0, $calls);
            self::assertSame(0, LazyService::$constructions);
        }
    }

    public function testUnsupportedParametersDoNotFallBackToDefaultsOrContextualValues(): void
    {
        $consumers = [
            new class('initial') {
                public function __construct(
                    #[Lazy] public string $value = 'default'
                ) {}
            },
            new class(null) {
                public function __construct(
                    #[Lazy] public ?LazyPort $value = null
                ) {}
            },
            new class(null) {
                public function __construct(
                    #[Lazy] public LazyPort|string|null $value = null
                ) {}
            },
            new class(null) {
                public function __construct(
                    #[Lazy] public $value = null
                ) {}
            },
        ];
        foreach ($consumers as $consumer) {
            $container = $this->container();
            $container->enableLazyInjection();
            $container->when($consumer::class)->needs('$value')->give('configured');
            try {
                $container->make($consumer::class);
                self::fail('Unsupported lazy parameters should be rejected.');
            } catch (BindingResolutionException $exception) {
                self::assertStringContainsString('Cannot lazily inject', $exception->getMessage());
                self::assertStringContainsString('::$value', $exception->getMessage());
            }
        }
    }

    // L04: 内部类直接拒绝
    public function testInternalClassIsRejectedBeforeCreatingNativeProxy(): void
    {
        $throwing = $this->container();
        $throwing->enableLazyInjection();
        try {
            $throwing->make(InternalClassLazyConsumer::class);
            self::fail('The unsupported internal class should have been rejected.');
        } catch (BindingResolutionException $exception) {
            self::assertStringContainsString('internal class and cannot be made lazy', $exception->getMessage());
        }
    }

    public function testAbstractClassIsRejectedWithInjectionContext(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Cannot lazily inject [' . AbstractLazyService::class . '] into ' . AbstractLazyConsumer::class . '::$service: concrete ' . AbstractLazyService::class . ' is an abstract class and cannot be made lazy.');
        $container->make(AbstractLazyConsumer::class);
    }

    public function testEnumIsRejectedWithInjectionContext(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Cannot lazily inject [' . LazyChoice::class . '] into ' . EnumLazyConsumer::class . '::$service: concrete ' . LazyChoice::class . ' is an enum and cannot be made lazy.');
        $container->make(EnumLazyConsumer::class);
    }

    public function testAbstractTypeBoundToConcreteClassCanStillBeLazy(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $container->bind(AbstractLazyService::class, ConcreteLazyService::class);

        $service = $container->make(AbstractLazyConsumer::class)->service;
        self::assertInstanceOf(ConcreteLazyService::class, $service);
        self::assertTrue((new \ReflectionClass($service))->isUninitializedLazyObject($service));
        self::assertSame('initialized', $service->value);
    }

    public function testCachedEnumIsStillInjectedDirectly(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $container->instance(LazyChoice::class, LazyChoice::First);

        self::assertSame(LazyChoice::First, $container->make(EnumLazyConsumer::class)->service);
    }

    public function testInternalAncestorsAreRejected(): void
    {
        foreach ([LazyDate::class, IndirectLazyDate::class] as $concrete) {
            $throwing = $this->container();
            $throwing->enableLazyInjection();
            $throwing->bind(\DateTimeImmutable::class, $concrete);
            try {
                $throwing->make(InternalClassLazyConsumer::class);
                self::fail('An internal ancestor should have been rejected.');
            } catch (BindingResolutionException $exception) {
                self::assertStringContainsString("concrete {$concrete} inherits internal class DateTimeImmutable", $exception->getMessage());
                self::assertStringContainsString(InternalClassLazyConsumer::class . '::$value', $exception->getMessage());
            }
        }
    }

    public function testStdClassExceptionAllowsNativeLazyObjects(): void
    {
        foreach ([\stdClass::class, LazyStdClass::class, IndirectLazyStdClass::class] as $concrete) {
            $container = $this->container();
            $container->enableLazyInjection();
            $container->bind(\stdClass::class, $concrete);
            $consumer = $container->make(LazyStdClassConsumer::class);
            self::assertInstanceOf($concrete, $consumer->value);
            if ($concrete !== \stdClass::class) {
                $reflection = new \ReflectionClass($consumer->value);
                self::assertTrue($reflection->isUninitializedLazyObject($consumer->value));
                self::assertSame('initialized', $consumer->value->value);
                self::assertFalse($reflection->isUninitializedLazyObject($consumer->value));
            }
        }
    }

    // L05: variadic 与相对类型统一拒绝
    public function testVariadicAndRelativeTypesAreUnresolvable(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();

        foreach ([VariadicLazyConsumer::class, RelativeTypeLazyConsumer::class, ParentTypeLazyConsumer::class] as $consumer) {
            try {
                $container->make($consumer);
                self::fail("{$consumer} should have been rejected.");
            } catch (UnresolvableLazyDependencyException $exception) {
                self::assertStringContainsString('Cannot handle lazy injection', $exception->getMessage());
            }
        }
    }

    // L06: alias 与 contextual binding
    public function testAliasAndContextualBindingsRetainTheirResolutionSemantics(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $container->bind(LazyPort::class, LazyService::class);
        $container->alias(LazyPort::class, 'lazy.port.alias');
        $container->when(LazyConsumer::class)->needs(LazyPort::class)->give(AlternateLazyService::class);

        $contextual = $container->make(LazyConsumer::class);
        $aliased = $container->make(AliasLazyConsumer::class);
        self::assertSame(0, LazyService::$constructions);
        self::assertSame('alternate!', $contextual->service->value());
        self::assertSame('lazy!', $aliased->service->value());
        self::assertSame(1, LazyService::$constructions);
    }

    // L12: 继承构造函数的实际消费者上下文与初始化后隔离
    public function testInheritedConstructorRetainsActualConsumerContextDuringInitialization(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $container->bind(LazyPort::class, LazyService::class);
        $container->when(InheritedLazyConsumer::class)->needs(LazyPort::class)->give(AlternateLazyService::class);

        $consumer = $container->make(InheritedLazyConsumer::class);
        self::assertSame(0, LazyService::$constructions);
        $reflection = new \ReflectionClass($consumer->service);
        self::assertTrue($reflection->isUninitializedLazyObject($consumer->service));
        $actual = $reflection->initializeLazyObject($consumer->service);
        self::assertInstanceOf(AlternateLazyService::class, $actual);
        self::assertSame(1, LazyService::$constructions);
        self::assertSame('alternate!', $consumer->service->value());
        self::assertSame('lazy!', $container->make(LazyPort::class)->value());
        self::assertSame('lazy!', $container->make(LazyConsumer::class)->service->value());
    }

    // L07: 已缓存 singleton 与多个代理共享 actual
    public function testExistingSingletonIsInjectedDirectlyAndPendingProxiesShareTheActualSingleton(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $container->singleton(LazyPort::class, LazyService::class);
        $first = $container->make(LazyConsumer::class);
        $second = $container->make(LazyConsumer::class);
        self::assertNotSame($first->service, $second->service);

        $firstActual = (new \ReflectionClass($first->service))->initializeLazyObject($first->service);
        $secondActual = (new \ReflectionClass($second->service))->initializeLazyObject($second->service);
        self::assertSame($firstActual, $secondActual);
        self::assertSame($firstActual, $container->make(LazyPort::class));

        $third = $container->make(LazyConsumer::class);
        self::assertSame($firstActual, $third->service);
    }

    // L08: 绑定变化后的原生类型兼容性检查
    public function testIncompatibleBindingChangeIsRejectedByNativeProxy(): void
    {
        $container = $this->container();
        $container->enableLazyInjection();
        $container->bind(LazyPort::class, LazyService::class);
        $consumer = $container->make(LazyConsumer::class);
        $container->bind(LazyPort::class, AlternateLazyService::class);

        $this->expectException(\TypeError::class);
        (new \ReflectionClass($consumer->service))->initializeLazyObject($consumer->service);
    }

    // L09: 无状态织入方法初始化与拦截
    public function testStatelessAopMethodInitializesAndEntersAop(): void
    {
        require_once dirname(__DIR__) . '/Fixtures/Weaver/WeaverFixtures.php';
        require_once dirname(__DIR__) . '/Fixtures/Weaver/CountingTarget.php';
        $container = $this->container();
        $generated = $this->temporaryDirectory->generatedClasses();
        $container->instance(Counts::class, $counts = new Counts());
        $container->enableLazyInjection();
        $container->enableAop([dirname(__DIR__) . '/Fixtures/Weaver'], $generated);

        $consumer = $container->make(LazyAopConsumer::class);
        self::assertArrayNotHasKey('created', $counts->values);
        self::assertSame('ok', $consumer->target->run());
        self::assertSame(1, $counts->values['created']);
        self::assertSame(1, $counts->values['invoked']);
    }

    // L10: 有状态织入方法、重复调用与瞬态类名稳定
    public function testStatefulMethodCombinesLazyInitializationWithAopSubclass(): void
    {
        require_once dirname(__DIR__) . '/Fixtures/Weaver/WeaverFixtures.php';
        require_once dirname(__DIR__) . '/Fixtures/Weaver/StatefulLazyAopTarget.php';
        $container = $this->container();
        $generated = $this->temporaryDirectory->generatedClasses();
        $container->instance(Counts::class, $counts = new Counts());
        $container->enableLazyInjection();
        $container->enableAop([dirname(__DIR__) . '/Fixtures/Weaver'], $generated);

        $consumer = $container->make(StatefulLazyAopConsumer::class);
        self::assertArrayNotHasKey('created', $counts->values);
        self::assertArrayNotHasKey('constructed', $counts->values);

        self::assertSame('ok', $consumer->target->run());
        self::assertSame(1, $counts->values['created']);
        self::assertSame(1, $counts->values['invoked']);
        $actual = (new \ReflectionClass($consumer->target))->initializeLazyObject($consumer->target);
        self::assertSame($consumer->target::class, $actual::class);
        self::assertSame('ok', $consumer->target->run());
        self::assertSame(1, $counts->values['created']);
        self::assertSame(2, $counts->values['invoked']);
        self::assertSame(1, $counts->values['constructed']);

        // Transient interceptor state must not change the generated class name.
        $second = $container->make(StatefulLazyAopConsumer::class);
        self::assertSame($consumer->target::class, $second->target::class);
        self::assertSame('ok', $second->target->run());
        self::assertSame(2, $counts->values['created']);
        self::assertSame(2, $counts->values['constructed']);
        self::assertSame(3, $counts->values['invoked']);
    }

    // L11: AOP singleton 共享与缓存直接注入
    public function testLazyAopProxiesShareSingletonAndCachedInstanceIsInjectedDirectly(): void
    {
        require_once dirname(__DIR__) . '/Fixtures/Weaver/WeaverFixtures.php';
        require_once dirname(__DIR__) . '/Fixtures/Weaver/StatefulLazyAopTarget.php';
        $container = $this->container();
        $container->instance(Counts::class, $counts = new Counts());
        $container->enableLazyInjection();
        $container->enableAop([dirname(__DIR__) . '/Fixtures/Weaver'], $this->temporaryDirectory->generatedClasses());
        $container->singleton(StatefulLazyAopTarget::class);
        $first = $container->make(StatefulLazyAopConsumer::class)->target;
        $second = $container->make(StatefulLazyAopConsumer::class)->target;
        self::assertSame([], $counts->values);
        $actual = $container->make(StatefulLazyAopTarget::class);
        self::assertSame($actual, (new \ReflectionClass($first))->initializeLazyObject($first));
        self::assertSame($actual, (new \ReflectionClass($second))->initializeLazyObject($second));
        self::assertSame($actual, $container->make(StatefulLazyAopConsumer::class)->target);
        self::assertSame('ok', $first->run());
        self::assertSame(1, $counts->values['constructed']);
        self::assertSame(1, $counts->values['invoked']);
    }
}
