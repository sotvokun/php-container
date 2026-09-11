<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reflection;

use Attribute;
use RuntimeException;

interface AnnotationContract {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class BaseAnnotation implements AnnotationContract
{
    public function __construct(
        public string $value
    ) {}
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ChildAnnotation extends BaseAnnotation {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class OtherAnnotation
{
    public function __construct(
        public int $number
    ) {}
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ThrowingAnnotation
{
    public function __construct()
    {
        throw new RuntimeException('attribute construction failed');
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class MethodOnlyAnnotation {}

#[Attribute(Attribute::TARGET_CLASS)]
final class ClassOnlyAnnotation {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RequiredAnnotation
{
    public function __construct(
        public string $required
    ) {}
}

#[BaseAnnotation('class-first')]
#[ChildAnnotation('class-child')]
#[BaseAnnotation('class-last')]
#[OtherAnnotation(41)]
class AnnotatedClass
{
    #[BaseAnnotation('method-first')]
    #[ChildAnnotation('method-child')]
    #[BaseAnnotation('method-last')]
    #[OtherAnnotation(42)]
    public function annotated(): void {}

    public function plain(): void {}
}

class PlainClass
{
    public function plain(): void {}
}

#[ThrowingAnnotation]
class ThrowingClass {}

class ThrowingMethodTarget
{
    #[ThrowingAnnotation]
    public function marked(): void {}
}

#[MethodOnlyAnnotation]
class InvalidTargetClass {}

class InvalidTargetMethodClass
{
    #[ClassOnlyAnnotation]
    public function marked(): void {}
}

#[RequiredAnnotation]
class MissingArgumentClass {}

class MissingArgumentMethodClass
{
    #[RequiredAnnotation]
    public function marked(): void {}
}

class ConstructorlessClass {}

class OwnConstructorClass
{
    public function __construct(
        public string $value = 'own'
    ) {}
}

class ParentConstructorClass
{
    public function __construct(
        public int $value = 7
    ) {}
}

class InheritedConstructorClass extends ParentConstructorClass {}

class MethodFilterParent
{
    public function inheritedPublic(): void {}

    protected function inheritedProtected(): void {}

    private function inheritedPrivate(): void {}

    final public function inheritedFinal(): void {}

    public static function inheritedStatic(): void {}
}

class MethodFilterTarget extends MethodFilterParent
{
    public function ownPublic(): void {}

    protected function ownProtected(): void {}

    private function ownPrivate(): void {}

    final protected function ownFinalProtected(): void {}

    private static function ownPrivateStatic(): void {}

    public static function ownPublicStatic(): void {}
}

trait DescribedTrait
{
    #[BaseAnnotation('trait')]
    public function traitMethod(int $count): string
    {
        return (string) $count;
    }
}

class ReflectionParent
{
    #[BaseAnnotation('parent')]
    public function inheritedMethod(string $name): ?string
    {
        return $name;
    }

    public function overriddenMethod(): object
    {
        return $this;
    }
}

#[OtherAnnotation(99)]
class ReflectionChild extends ReflectionParent
{
    use DescribedTrait;

    #[ChildAnnotation('override')]
    public function overriddenMethod(): static
    {
        return $this;
    }

    public function typedMethod(BaseAnnotation|OtherAnnotation $annotation, int $count = 3): BaseAnnotation|OtherAnnotation
    {
        return $annotation;
    }
}
