<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Sotvokun\Container\Aop\ClassResolver;
use Sotvokun\Container\Aop\Weaver;
use Tests\Fixtures\Signature\Both;
use Tests\Fixtures\Signature\ComplexTypesTarget;
use Tests\Fixtures\Signature\ConstructorCallTarget;
use Tests\Fixtures\Signature\FinalConstructorTarget;
use Tests\Fixtures\Signature\GeneratorTarget;
use Tests\Fixtures\Signature\InternalCallsTarget;
use Tests\Fixtures\Signature\ParentCallTarget;
use Tests\Fixtures\Signature\ProceedInterceptor;
use Tests\Fixtures\Signature\PromotedReadonlyTarget;
use Tests\Fixtures\Signature\ReturnTypesTarget;
use Tests\Fixtures\Signature\SignatureProvider;
use Tests\Fixtures\Signature\StatefulTarget;
use Tests\Fixtures\Signature\TraceInterceptor;
use Tests\Support\IsolatedTestCase;

#[CoversNothing]
final class ProxySignatureTest extends IsolatedTestCase
{
    private function fixtures(): string
    {
        return dirname(__DIR__) . '/Fixtures/Signature';
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once $this->fixtures() . '/SignatureFixtures.php';
        SignatureProvider::$interceptors = [new ProceedInterceptor()];
    }

    /**
     * @template T of object @param class-string<T> $class @param list<mixed> $arguments @return T
     */
    private function target(string $class, array $arguments = []): object
    {
        $weaver = new Weaver($this->illuminateContainer(), new ClassResolver([$this->fixtures()]), $this->temporaryDirectory->generatedClasses());
        return $weaver->newInstance($class, $arguments);
    }

    public function testS01ScalarObjectNullMixedAndVoidReturnsPreserveValuesAndEffects(): void
    {
        $target = $this->target(ReturnTypesTarget::class);
        self::assertSame('', $target->stringValue());
        self::assertSame(0, $target->intValue());
        self::assertFalse($target->boolValue());
        self::assertSame([], $target->arrayValue());
        self::assertSame('object', $target->objectValue()->value);
        self::assertNull($target->nullValue());
        self::assertSame('mixed', $target->mixedValue());
        $target->voidValue();
        self::assertSame(1, $target->voidCalls);
    }

    public function testS02NullableUnionIntersectionAndDnfSignaturesExecuteCorrectly(): void
    {
        $target = $this->target(ComplexTypesTarget::class);
        $both = new Both();
        self::assertNull($target->nullable(null));
        self::assertSame(12, $target->union(12));
        self::assertSame($both, $target->intersection($both));
        self::assertSame($both, $target->dnf($both));
        self::assertNull($target->dnf(null));
    }

    public function testS06GeneratorAspectFinishesBeforeTheTargetRunsDuringIteration(): void
    {
        $aspect = [];
        SignatureProvider::$interceptors = [new TraceInterceptor($aspect)];
        $target = $this->target(GeneratorTarget::class);
        $generator = $target->stream();
        self::assertSame(['aspect:before:stream', 'aspect:after:stream'], $aspect);
        self::assertSame([], $target->events);
        self::assertSame(['first', 'second'], iterator_to_array($generator, false));
        self::assertSame(['target:create', 'target:finally'], $target->events);

        try {
            iterator_to_array($target->stream(true), false);
            self::fail('Expected failure while iterating the generator.');
        } catch (\RuntimeException $exception) {
            self::assertSame('generator failed', $exception->getMessage());
        }
        self::assertSame(['target:create', 'target:finally', 'target:create', 'target:finally'], $target->events);
    }

    public function testS07ReadonlyPromotedPropertiesAndFinalConstructorsRemainUsable(): void
    {
        self::assertSame('promoted', $this->target(PromotedReadonlyTarget::class, ['promoted'])->read());
        self::assertSame('final', $this->target(FinalConstructorTarget::class, ['final'])->read());
    }

    public function testS09InternalAndRecursiveCallsPassThroughProxyOverrides(): void
    {
        $aspect = [];
        SignatureProvider::$interceptors = [new TraceInterceptor($aspect)];
        $target = $this->target(InternalCallsTarget::class);
        self::assertSame('done', $target->outer());
        self::assertSame(['target:outer', 'target:inner:0', 'target:inner:1'], $target->events);
        self::assertSame([
            'aspect:before:outer',
            'aspect:before:inner',
            'aspect:before:inner',
            'aspect:after:inner',
            'aspect:after:inner',
            'aspect:after:outer',
        ], $aspect);
    }

    public function testS09ExplicitParentCallRunsParentImplementationWithoutAnInheritedBinding(): void
    {
        $aspect = [];
        SignatureProvider::$interceptors = [new TraceInterceptor($aspect)];
        $target = $this->target(ParentCallTarget::class);
        self::assertSame('parent', $target->callParent());
        self::assertSame(['target:parent'], $target->events);
        self::assertSame(['aspect:before:callParent', 'aspect:after:callParent'], $aspect);
    }

    #[Group('upstream')]
    public function testS10ConstructorCallRunsUninterceptedBeforeBindingsThenLaterCallsAreIntercepted(): void
    {
        $aspect = [];
        SignatureProvider::$interceptors = [new TraceInterceptor($aspect)];
        $target = $this->target(ConstructorCallTarget::class);
        self::assertSame('constructed', $target->constructedValue);
        self::assertSame(['target:marked'], $target->events);
        self::assertSame([], $aspect);
        self::assertSame('constructed', $target->marked());
        self::assertSame(['aspect:before:marked', 'aspect:after:marked'], $aspect);
    }

    public function testS11ClonePreservesBindingsAndSeparatesTargetState(): void
    {
        $target = $this->target(StatefulTarget::class, [2]);
        $clone = clone $target;
        self::assertSame(3, $target->increment());
        self::assertSame(3, $clone->increment());
        self::assertSame(4, $target->increment());
    }

    public function testS11SerializedProxyRestoresTargetStateAndBindings(): void
    {
        $target = $this->target(StatefulTarget::class, [5]);
        $restored = unserialize(serialize($target), ['allowed_classes' => true]);
        self::assertInstanceOf(StatefulTarget::class, $restored);
        self::assertSame(6, $restored->increment());
        self::assertSame(5, $target->value);
    }
}
