<?php

declare(strict_types=1);

namespace Tests\Unit\Aop;

use ArgumentCountError;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionMethod as NativeReflectionMethod;
use RuntimeException;
use Sotvokun\Container\Aop\ReflectionClass;
use Sotvokun\Container\Aop\ReflectionMethod;
use Tests\Fixtures\Reflection\AnnotatedClass;
use Tests\Fixtures\Reflection\AnnotationContract;
use Tests\Fixtures\Reflection\BaseAnnotation;
use Tests\Fixtures\Reflection\ChildAnnotation;
use Tests\Fixtures\Reflection\ConstructorlessClass;
use Tests\Fixtures\Reflection\InheritedConstructorClass;
use Tests\Fixtures\Reflection\InvalidTargetClass;
use Tests\Fixtures\Reflection\InvalidTargetMethodClass;
use Tests\Fixtures\Reflection\MethodFilterTarget;
use Tests\Fixtures\Reflection\MissingArgumentClass;
use Tests\Fixtures\Reflection\MissingArgumentMethodClass;
use Tests\Fixtures\Reflection\OtherAnnotation;
use Tests\Fixtures\Reflection\OwnConstructorClass;
use Tests\Fixtures\Reflection\ParentConstructorClass;
use Tests\Fixtures\Reflection\PlainClass;
use Tests\Fixtures\Reflection\ReflectionChild;
use Tests\Fixtures\Reflection\ReflectionParent;
use Tests\Fixtures\Reflection\ThrowingClass;
use Tests\Fixtures\Reflection\ThrowingMethodTarget;
use ValueError;

#[CoversClass(ReflectionClass::class)]
#[CoversClass(ReflectionMethod::class)]
final class ReflectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/Fixtures/Reflection/ReflectionFixtures.php';
    }

    public function testF01ClassAndMethodAnnotationsAreInstantiatedInOrderWithArguments(): void
    {
        $class = new ReflectionClass(AnnotatedClass::class);
        $method = new ReflectionMethod(AnnotatedClass::class, 'annotated');

        self::assertAnnotationSequence($class->getAnnotations(), ['class-first', 'class-child', 'class-last', 41]);
        self::assertAnnotationSequence($method->getAnnotations(), ['method-first', 'method-child', 'method-last', 42]);
        self::assertSame([], (new ReflectionClass(PlainClass::class))->getAnnotations());
        self::assertSame([], (new ReflectionMethod(PlainClass::class, 'plain'))->getAnnotations());
    }

    public function testF01EveryAnnotationsCallCreatesFreshInstances(): void
    {
        $class = new ReflectionClass(AnnotatedClass::class);
        $method = new ReflectionMethod(AnnotatedClass::class, 'annotated');

        $firstClassCall = $class->getAnnotations();
        $secondClassCall = $class->getAnnotations();
        $firstMethodCall = $method->getAnnotations();
        $secondMethodCall = $method->getAnnotations();

        foreach ($firstClassCall as $index => $annotation) {
            self::assertNotSame($annotation, $secondClassCall[$index]);
        }
        foreach ($firstMethodCall as $index => $annotation) {
            self::assertNotSame($annotation, $secondMethodCall[$index]);
        }
    }

    public function testF02AnnotationLookupSupportsFirstMatchInheritanceExactMatchAndMissingValues(): void
    {
        foreach ([new ReflectionClass(AnnotatedClass::class), new ReflectionMethod(AnnotatedClass::class, 'annotated')] as $reflection) {
            $base = $reflection->getAnnotation(BaseAnnotation::class);
            self::assertInstanceOf(BaseAnnotation::class, $base);
            self::assertSame(str_starts_with($base->value, 'class-') ? 'class-first' : 'method-first', $base->value);

            self::assertInstanceOf(BaseAnnotation::class, $reflection->getAnnotation(AnnotationContract::class));
            self::assertNull($reflection->getAnnotation(AnnotationContract::class, 0));
            self::assertInstanceOf(ChildAnnotation::class, $reflection->getAnnotation(ChildAnnotation::class, 0));
            self::assertNull($reflection->getAnnotation(RuntimeException::class));
        }
    }

    /**
     * @return iterable<string, array{callable(): void, class-string<\Throwable>}>
     */
    public static function annotationErrors(): iterable
    {
        yield 'F03 class constructor exception' => [
            static fn() => (new ReflectionClass(ThrowingClass::class))->getAnnotations(),
            RuntimeException::class,
        ];
        yield 'F03 method constructor exception' => [
            static fn() => (new ReflectionMethod(ThrowingMethodTarget::class, 'marked'))->getAnnotations(),
            RuntimeException::class,
        ];
        yield 'F03 invalid class attribute target' => [
            static fn() => (new ReflectionClass(InvalidTargetClass::class))->getAnnotations(),
            Error::class,
        ];
        yield 'F03 invalid method attribute target' => [
            static fn() => (new ReflectionMethod(InvalidTargetMethodClass::class, 'marked'))->getAnnotations(),
            Error::class,
        ];
        yield 'F03 missing class attribute argument' => [
            static fn() => (new ReflectionClass(MissingArgumentClass::class))->getAnnotations(),
            ArgumentCountError::class,
        ];
        yield 'F03 missing method attribute argument' => [
            static fn() => (new ReflectionMethod(MissingArgumentMethodClass::class, 'marked'))->getAnnotations(),
            ArgumentCountError::class,
        ];
        yield 'F03 invalid class flags' => [
            static fn() => (new ReflectionClass(AnnotatedClass::class))->getAnnotation(BaseAnnotation::class, 123),
            ValueError::class,
        ];
        yield 'F03 invalid method flags' => [
            static fn() => (new ReflectionMethod(AnnotatedClass::class, 'annotated'))->getAnnotation(BaseAnnotation::class, 123),
            ValueError::class,
        ];
    }

    /**
     * @param callable(): void $operation
     */
    #[DataProvider('annotationErrors')]
    public function testF03AnnotationErrorsAreNotSwallowed(callable $operation, string $expectedException): void
    {
        $this->expectException($expectedException);
        $operation();
    }

    public function testF04DeclaringConstructorAndParentReflectionsUseLibraryWrappers(): void
    {
        $declaring = (new ReflectionMethod(ReflectionChild::class, 'inheritedMethod'))->getDeclaringClass();
        self::assertInstanceOf(ReflectionClass::class, $declaring);
        self::assertSame(ReflectionParent::class, $declaring->getName());

        self::assertNull((new ReflectionClass(ConstructorlessClass::class))->getConstructor());
        $own = (new ReflectionClass(OwnConstructorClass::class))->getConstructor();
        self::assertInstanceOf(ReflectionMethod::class, $own);
        self::assertSame(OwnConstructorClass::class, $own->getDeclaringClass()->getName());
        $inherited = (new ReflectionClass(InheritedConstructorClass::class))->getConstructor();
        self::assertInstanceOf(ReflectionMethod::class, $inherited);
        self::assertSame(ParentConstructorClass::class, $inherited->getDeclaringClass()->getName());

        self::assertFalse((new ReflectionClass(AnnotatedClass::class))->getParentClass());
        $parent = (new ReflectionClass(ReflectionChild::class))->getParentClass();
        self::assertInstanceOf(ReflectionClass::class, $parent);
        self::assertSame(ReflectionParent::class, $parent->getName());
    }

    /**
     * @return iterable<string, array{int|null}>
     */
    public static function methodFilters(): iterable
    {
        yield 'F05 no filter' => [null];
        yield 'F05 public' => [NativeReflectionMethod::IS_PUBLIC];
        yield 'F05 protected' => [NativeReflectionMethod::IS_PROTECTED];
        yield 'F05 private' => [NativeReflectionMethod::IS_PRIVATE];
        yield 'F05 static' => [NativeReflectionMethod::IS_STATIC];
        yield 'F05 final' => [NativeReflectionMethod::IS_FINAL];
        yield 'F05 public static combination' => [NativeReflectionMethod::IS_PUBLIC | NativeReflectionMethod::IS_STATIC];
        yield 'F05 protected final combination' => [NativeReflectionMethod::IS_PROTECTED | NativeReflectionMethod::IS_FINAL];
    }

    #[DataProvider('methodFilters')]
    public function testF05MethodFiltersMatchNativeReflection(int|null $filter): void
    {
        $native = new \ReflectionClass(MethodFilterTarget::class);
        $wrapped = new ReflectionClass(MethodFilterTarget::class);
        $nativeMethods = $filter === null ? $native->getMethods() : $native->getMethods($filter);
        $wrappedMethods = $filter === null ? $wrapped->getMethods() : $wrapped->getMethods($filter);

        self::assertSame(self::methodDescriptors($nativeMethods), self::methodDescriptors($wrappedMethods));
        foreach ($wrappedMethods as $method) {
            self::assertInstanceOf(ReflectionMethod::class, $method);
        }
    }

    public function testF06InheritedTraitAndOverriddenMethodsKeepNativeDeclaringClasses(): void
    {
        foreach (['inheritedMethod', 'traitMethod', 'overriddenMethod'] as $methodName) {
            $native = new \ReflectionMethod(ReflectionChild::class, $methodName);
            $wrapped = new ReflectionMethod(ReflectionChild::class, $methodName);
            self::assertSame($native->getDeclaringClass()->getName(), $wrapped->getDeclaringClass()->getName());
            self::assertSame($native->getName(), $wrapped->getName());
        }
    }

    public function testF06TypesParametersAndAttributesRemainReadableThroughWrappers(): void
    {
        $class = new ReflectionClass(ReflectionChild::class);
        $method = new ReflectionMethod(ReflectionChild::class, 'typedMethod');
        $native = new \ReflectionMethod(ReflectionChild::class, 'typedMethod');

        self::assertSame(99, $class->getAnnotation(OtherAnnotation::class)?->number);
        self::assertSame((string) $native->getReturnType(), (string) $method->getReturnType());
        self::assertSame(count($native->getParameters()), count($method->getParameters()));
        foreach ($native->getParameters() as $index => $parameter) {
            $wrappedParameter = $method->getParameters()[$index];
            self::assertSame($parameter->getName(), $wrappedParameter->getName());
            self::assertSame((string) $parameter->getType(), (string) $wrappedParameter->getType());
            self::assertSame($parameter->isDefaultValueAvailable(), $wrappedParameter->isDefaultValueAvailable());
        }

        $override = new ReflectionMethod(ReflectionChild::class, 'overriddenMethod');
        self::assertSame('override', $override->getAnnotation(ChildAnnotation::class)?->value);
        self::assertSame('static', (string) $override->getReturnType());
    }

    /**
     * @param list<object> $annotations
     */
    private static function assertAnnotationSequence(array $annotations, array $values): void
    {
        self::assertSame([BaseAnnotation::class, ChildAnnotation::class, BaseAnnotation::class, OtherAnnotation::class], array_map(get_class(...), $annotations));
        self::assertSame($values, array_map(static fn(object $annotation) => $annotation->value ?? $annotation->number, $annotations));
    }

    /**
     * @param list<\ReflectionMethod> $methods
     */
    private static function methodDescriptors(array $methods): array
    {
        $descriptors = array_map(
            static fn(\ReflectionMethod $method): string => $method->getDeclaringClass()->getName() . '::' . $method->getName() . ':' . $method->getModifiers(),
            $methods,
        );
        sort($descriptors);

        return $descriptors;
    }
}
