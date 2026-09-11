<?php

declare(strict_types=1);

namespace Tests\Unit\Aop;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Sotvokun\Container\Aop\ClassResolver;
use Tests\Fixtures\Resolver\AbstractMarkedTarget;
use Tests\Fixtures\Resolver\AbstractUnmarkedTarget;
use Tests\Fixtures\Resolver\ClassLevelOnlyTarget;
use Tests\Fixtures\Resolver\ConstructorMarkedTarget;
use Tests\Fixtures\Resolver\DestructorMarkedTarget;
use Tests\Fixtures\Resolver\FinalMarkedTarget;
use Tests\Fixtures\Resolver\FinalMethodMarkedTarget;
use Tests\Fixtures\Resolver\FinalUnmarkedTarget;
use Tests\Fixtures\Resolver\FirstProvider;
use Tests\Fixtures\Resolver\MixedVisibilityTarget;
use Tests\Fixtures\Resolver\MultipleTarget;
use Tests\Fixtures\Resolver\NonPublicMarkedTarget;
use Tests\Fixtures\Resolver\PlainTarget;
use Tests\Fixtures\Resolver\PrivateConstructorMarkedTarget;
use Tests\Fixtures\Resolver\PrivateConstructorUnmarkedTarget;
use Tests\Fixtures\Resolver\PublicTarget;
use Tests\Fixtures\Resolver\SecondProvider;
use Tests\Fixtures\Resolver\StaticMarkedTarget;
use Tests\Support\IsolatedTestCase;

#[CoversClass(ClassResolver::class)]
final class ClassResolverTest extends IsolatedTestCase
{
    public function testR01InvalidDirectoriesAreIgnoredAndUnknownClassesAreNotWeavable(): void
    {
        $file = __FILE__;
        foreach ([[], [$this->temporaryDirectory->path()], [$this->temporaryDirectory->path() . '/missing'], [$file]] as $directories) {
            $resolver = new ClassResolver($directories);
            self::assertFalse($resolver->shouldWeave(PublicTarget::class));
            try {
                $resolver->metadataFor(PublicTarget::class);
                self::fail('R01 metadataFor must reject a class outside the scan map.');
            } catch (LogicException $exception) {
                self::assertStringContainsString(PublicTarget::class, $exception->getMessage());
            }
        }
    }

    public function testR04OnlyPublicMethodProviderAttributesCreateTargets(): void
    {
        $resolver = $this->resolver();
        self::assertTrue($resolver->shouldWeave(PublicTarget::class));
        self::assertSame(['marked' => [FirstProvider::class]], $resolver->metadataFor(PublicTarget::class)->methods);
        self::assertFalse($resolver->shouldWeave(PlainTarget::class));
        self::assertFalse($resolver->shouldWeave(ClassLevelOnlyTarget::class));
    }

    public function testR05MethodAndRepeatableAttributeOrderIsStableWithoutDuplicates(): void
    {
        $resolver = $this->resolver();
        $expected = ['alpha' => [FirstProvider::class, SecondProvider::class, FirstProvider::class], 'beta' => [SecondProvider::class]];
        self::assertSame($expected, $resolver->metadataFor(MultipleTarget::class)->methods);
        self::assertSame($expected, $resolver->metadataFor(MultipleTarget::class)->methods);
    }

    /**
     * @return iterable<string,array{class-string}>
     */
    public static function invalidClasses(): iterable
    {
        yield 'final' => [FinalMarkedTarget::class];
        yield 'abstract' => [AbstractMarkedTarget::class];
        yield 'private constructor' => [PrivateConstructorMarkedTarget::class];
    }

    #[DataProvider('invalidClasses')]
    public function testR06InvalidMarkedClassesAreRejected(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($class);
        $this->resolver()->shouldWeave($class);
    }

    public function testR06InvalidUnmarkedClassesDoNotTriggerAopValidation(): void
    {
        $resolver = $this->resolver();
        self::assertFalse($resolver->shouldWeave(FinalUnmarkedTarget::class));
        self::assertFalse($resolver->shouldWeave(AbstractUnmarkedTarget::class));
        self::assertFalse($resolver->shouldWeave(PrivateConstructorUnmarkedTarget::class));
    }

    /**
     * @return iterable<string,array{class-string}>
     */
    public static function invalidMethods(): iterable
    {
        yield 'static' => [StaticMarkedTarget::class];
        yield 'final' => [FinalMethodMarkedTarget::class];
        yield 'constructor' => [ConstructorMarkedTarget::class];
        yield 'destructor' => [DestructorMarkedTarget::class];
    }

    #[DataProvider('invalidMethods')]
    public function testR07InvalidPublicMethodsAreRejected(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($class);
        $this->resolver()->shouldWeave($class);
    }

    public function testR07OrdinaryPublicMethodSucceeds(): void
    {
        self::assertTrue($this->resolver()->shouldWeave(PublicTarget::class));
    }

    public function testR08NonPublicProviderMethodsAreSilentlyIgnored(): void
    {
        $resolver = $this->resolver();
        self::assertFalse($resolver->shouldWeave(NonPublicMarkedTarget::class));
        self::assertSame(['visible' => [SecondProvider::class]], $resolver->metadataFor(MixedVisibilityTarget::class)->methods);
    }

    private function resolver(): ClassResolver
    {
        return new ClassResolver([dirname(__DIR__, 2) . '/Fixtures/Resolver']);
    }
}
