<?php

declare(strict_types=1);

namespace Tests\Unit\Aop;

use ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ray\Aop\MethodInvocation as RayMethodInvocation;
use Ray\Aop\ReflectionMethod as RayReflectionMethod;
use RuntimeException;
use Sotvokun\Container\Aop\Adapter\RayMethodInterceptorAdapter;
use Sotvokun\Container\Aop\Adapter\RayMethodInvocationAdapter;
use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;
use Sotvokun\Container\Aop\ReflectionMethod;
use Tests\Fixtures\Invocation\InvocationTarget;

#[CoversClass(RayMethodInterceptorAdapter::class)]
#[CoversClass(RayMethodInvocationAdapter::class)]
final class InvocationAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/Fixtures/Invocation/InvocationFixtures.php';
    }

    public function testA01InterceptorAdapterWrapsInvocationAndReturnsExactValue(): void
    {
        $ray = $this->createMock(RayMethodInvocation::class);
        $result = new \stdClass();
        $business = new class($ray, $result) implements MethodInterceptor {
            public function __construct(
                private RayMethodInvocation $expected,
                private object $result
            ) {}

            public function invoke(MethodInvocation $invocation): mixed
            {
                TestCase::assertInstanceOf(RayMethodInvocationAdapter::class, $invocation);
                TestCase::assertSame($this->expected, (fn() => $this->invocation)->call($invocation));
                return $this->result;
            }
        };

        self::assertSame($result, (new RayMethodInterceptorAdapter($business))->invoke($ray));
    }

    public function testA01InterceptorAdapterPropagatesTheSameExceptionObject(): void
    {
        $ray = $this->createMock(RayMethodInvocation::class);
        $expected = new RuntimeException('exact exception');
        $business = new class($expected) implements MethodInterceptor {
            public function __construct(
                private RuntimeException $exception
            ) {}

            public function invoke(MethodInvocation $invocation): mixed
            {
                throw $this->exception;
            }
        };

        try {
            (new RayMethodInterceptorAdapter($business))->invoke($ray);
            self::fail('Expected the business interceptor exception.');
        } catch (RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }
    }

    public function testA02InvocationAdapterForwardsEveryOperationOnceAndPreservesIdentities(): void
    {
        $object = new InvocationTarget();
        $arguments = new ArrayObject(['value']);
        $named = new ArrayObject(['value' => 'value']);
        $rayMethod = new RayReflectionMethod(InvocationTarget::class, 'described');
        $ray = $this->createMock(RayMethodInvocation::class);
        $ray->expects(self::once())->method('proceed')->willReturn($object);
        $ray->expects(self::once())->method('getThis')->willReturn($object);
        $ray->expects(self::once())->method('getArguments')->willReturn($arguments);
        $ray->expects(self::once())->method('getNamedArguments')->willReturn($named);
        $ray->expects(self::once())->method('getMethod')->willReturn($rayMethod);
        $adapter = new RayMethodInvocationAdapter($ray);

        self::assertSame($object, $adapter->proceed());
        self::assertSame($object, $adapter->getThis());
        self::assertSame($arguments, $adapter->getArguments());
        self::assertSame($named, $adapter->getNamedArguments());
        $method = $adapter->getMethod();
        self::assertInstanceOf(ReflectionMethod::class, $method);
        self::assertSame(InvocationTarget::class, $method->getDeclaringClass()->getName());
        self::assertSame('described', $method->getName());
    }

    public function testA02GetThisPreservesAllowedNullValue(): void
    {
        $ray = $this->createMock(RayMethodInvocation::class);
        $ray->expects(self::once())->method('getThis')->willReturn(null);

        self::assertNull((new RayMethodInvocationAdapter($ray))->getThis());
    }
}
