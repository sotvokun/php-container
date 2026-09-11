<?php

declare(strict_types=1);

namespace Tests\Integration;

use ArgumentCountError;
use ArrayObject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Sotvokun\Container\Aop\ClassResolver;
use Sotvokun\Container\Aop\ReflectionMethod;
use Sotvokun\Container\Aop\Weaver;
use Tests\Fixtures\Invocation\ArgumentObservationInterceptor;
use Tests\Fixtures\Invocation\BypassInterceptor;
use Tests\Fixtures\Invocation\CatchingInterceptor;
use Tests\Fixtures\Invocation\DoubleProceedInterceptor;
use Tests\Fixtures\Invocation\FinallyInterceptor;
use Tests\Fixtures\Invocation\InvalidArgumentOnceInterceptor;
use Tests\Fixtures\Invocation\InvalidReturnOnceInterceptor;
use Tests\Fixtures\Invocation\InvocationLog;
use Tests\Fixtures\Invocation\InvocationMarker;
use Tests\Fixtures\Invocation\InvocationProvider;
use Tests\Fixtures\Invocation\InvocationTarget;
use Tests\Fixtures\Invocation\MetadataInterceptor;
use Tests\Fixtures\Invocation\NamedSnapshotInterceptor;
use Tests\Fixtures\Invocation\PositionalMutationInterceptor;
use Tests\Fixtures\Invocation\ProceedingInterceptor;
use Tests\Fixtures\Invocation\ThrowOnceInterceptor;
use Tests\Support\IsolatedTestCase;
use TypeError;

#[CoversNothing]
final class InvocationTest extends IsolatedTestCase
{
    private function fixtures(): string
    {
        return dirname(__DIR__) . '/Fixtures/Invocation';
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once $this->fixtures() . '/InvocationFixtures.php';
        InvocationProvider::$interceptors = [];
    }

    /**
     * @param list<\Sotvokun\Container\Aop\MethodInterceptor> $interceptors
     */
    private function target(array $interceptors): InvocationTarget
    {
        InvocationProvider::$interceptors = $interceptors;
        $weaver = new Weaver($this->illuminateContainer(), new ClassResolver([$this->fixtures()]), $this->temporaryDirectory->generatedClasses());

        return $weaver->newInstance(InvocationTarget::class, []);
    }

    public function testA03ProceedBypassAndBusinessExceptionRecoveryHaveRealTargetEffects(): void
    {
        $target = $this->target([new ProceedingInterceptor()]);
        self::assertSame('name:2', $target->concatenate('name', 2));
        self::assertSame(1, $target->calls);

        $replacement = new \stdClass();
        $target = $this->target([new BypassInterceptor($replacement)]);
        self::assertSame($replacement, $target->flexible());
        self::assertSame(0, $target->calls);

        $target = $this->target([new CatchingInterceptor('recovered')]);
        self::assertSame('recovered', $target->fail());
        self::assertSame(1, $target->calls);
    }

    public function testA04PositionalArgumentsAreSharedWithinOneInvocationAndIsolatedAcrossCalls(): void
    {
        $log = new InvocationLog();
        $target = $this->target([new PositionalMutationInterceptor($log), new ArgumentObservationInterceptor($log)]);
        self::assertSame('changed:7', $target->concatenate('first', 1));
        self::assertSame('changed:7', $target->concatenate('second', 2));

        [$tagOne, $firstAccess, $secondAccess] = $log->entries[0];
        [, $downstream] = $log->entries[1];
        [, $nextInvocation] = $log->entries[2];
        self::assertSame('mutation-arguments', $tagOne);
        self::assertSame($firstAccess, $secondAccess);
        self::assertSame($firstAccess, $downstream);
        self::assertNotSame($firstAccess, $nextInvocation);
        self::assertSame(['changed', 7], $downstream->getArrayCopy());
    }

    public function testA05NamedArgumentsAreIndependentSnapshotsAndDoNotMutatePositionals(): void
    {
        $log = new InvocationLog();
        $target = $this->target([new NamedSnapshotInterceptor($log)]);
        self::assertSame('original:4', $target->concatenate('original', 4));
        [, $first, $second, $positional] = $log->entries[0];
        self::assertInstanceOf(ArrayObject::class, $first);
        self::assertNotSame($first, $second);
        self::assertSame('snapshot-only', $first['name']);
        self::assertSame('original', $second['name']);
        self::assertSame(['original', 4], $positional->getArrayCopy());
    }

    public function testA06EmptyExplicitNullNamedAndPositionalVariadicCallsPreserveRuntimeArguments(): void
    {
        $target = $this->target([new ProceedingInterceptor()]);
        self::assertSame(['default', null], $target->optional());
        self::assertSame(['named', null], $target->optional(name: 'named'));
        self::assertSame(['named', null], $target->optional(note: null, name: 'named'));
        self::assertSame(['p', []], $target->variadic('p'));
        self::assertSame(['p', ['a', 'b']], $target->variadic('p', 'a', 'b'));
    }

    #[Group('upstream')]
    public function testA06NamedVariadicArgumentsPreserveNamesAndValues(): void
    {
        $target = $this->target([new ProceedingInterceptor()]);
        self::assertSame(['p', ['labels' => 'a', 'b' => 'b']], $target->variadic(prefix: 'p', labels: 'a', b: 'b'));
    }

    public function testA07MethodMetadataPointsToOriginalDeclarationAndExposesTypesAndAttributes(): void
    {
        $log = new InvocationLog();
        $target = $this->target([new MetadataInterceptor($log)]);
        self::assertSame('value', $target->described('value'));
        $method = $log->entries[0];
        self::assertInstanceOf(ReflectionMethod::class, $method);
        self::assertSame(InvocationTarget::class, $method->getDeclaringClass()->getName());
        self::assertSame('described', $method->getName());
        self::assertSame('string', (string) $method->getParameters()[0]->getType());
        self::assertSame('string', (string) $method->getReturnType());
        self::assertSame('method-marker', $method->getAnnotation(InvocationMarker::class)?->value);
        self::assertSame('class-marker', $method->getDeclaringClass()->getAnnotation(InvocationMarker::class)?->value);
    }

    public function testA08CallingProceedTwiceExecutesTheTargetTwiceAfterChainExhaustion(): void
    {
        $target = $this->target([new DoubleProceedInterceptor()]);
        self::assertSame([1, 2], $target->counted());
        self::assertSame(2, $target->calls);
    }

    public function testA09ExceptionsRunFinallyAndTheNextInvocationStartsWithACompleteChain(): void
    {
        $log = new InvocationLog();
        $target = $this->target([new FinallyInterceptor($log), new ThrowOnceInterceptor()]);
        try {
            $target->counted();
            self::fail('Expected interceptor exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('interceptor failed', $exception->getMessage());
        }
        self::assertSame(1, $target->counted());
        self::assertSame(['before', 'finally', 'before', 'finally'], $log->entries);

        $target = $this->target([new FinallyInterceptor($log)]);
        try {
            $target->fail();
            self::fail('Expected business exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('business failed', $exception->getMessage());
        }
        self::assertSame(['default', null], $target->optional());
        self::assertSame(['before', 'finally', 'before', 'finally', 'before', 'finally', 'before', 'finally'], $log->entries);
    }

    public function testA10WrongTypeAndDeletedArgumentsFailThenTheNextCallRecovers(): void
    {
        foreach (['type' => TypeError::class, 'delete' => ArgumentCountError::class] as $operation => $exceptionClass) {
            $target = $this->target([new InvalidArgumentOnceInterceptor($operation)]);
            try {
                $target->typed('ok', 1);
                self::fail("Expected {$exceptionClass} for {$operation}.");
            } catch (\Throwable $exception) {
                self::assertInstanceOf($exceptionClass, $exception);
            }
            self::assertSame('ok:1', $target->typed('ok', 1));
            self::assertSame(1, $target->calls);
        }
    }

    public function testA10AnExtraArgumentUsesPhpUserFunctionSemanticsAndDoesNotPoisonRetry(): void
    {
        $target = $this->target([new InvalidArgumentOnceInterceptor('add')]);
        self::assertSame('ok:1', $target->typed('ok', 1));
        self::assertSame('ok:1', $target->typed('ok', 1));
        self::assertSame(2, $target->calls);
    }

    public function testA10InvalidInterceptorReturnMeetsProxyReturnTypeAndThenRecovers(): void
    {
        $target = $this->target([new InvalidReturnOnceInterceptor()]);
        try {
            $target->typed('ok', 1);
            self::fail('Expected proxy return TypeError.');
        } catch (TypeError) {
            self::assertSame(0, $target->calls);
        }
        self::assertSame('ok:1', $target->typed('ok', 1));
        self::assertSame(1, $target->calls);
    }
}
