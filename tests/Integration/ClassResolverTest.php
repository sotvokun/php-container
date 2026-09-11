<?php

declare(strict_types=1);

namespace Tests\Integration;

use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionAttribute;
use ReflectionClass;
use Sotvokun\Container\Aop\ClassResolver;
use Symfony\Component\Filesystem\Path;
use Tests\Fixtures\Resolver\DeferredTarget;
use Tests\Fixtures\Resolver\FirstProvider;
use Tests\Fixtures\Resolver\ImplementsMarkedInterface;
use Tests\Fixtures\Resolver\InheritsMarkedMethod;
use Tests\Fixtures\Resolver\InvalidArgumentsTarget;
use Tests\Fixtures\Resolver\MarkedParent;
use Tests\Fixtures\Resolver\MissingAttributeDeclarationTarget;
use Tests\Fixtures\Resolver\OverridesWithAttribute;
use Tests\Fixtures\Resolver\OverridesWithoutAttribute;
use Tests\Fixtures\Resolver\ParameterizedTarget;
use Tests\Fixtures\Resolver\ParameterProvider;
use Tests\Fixtures\Resolver\PublicTarget;
use Tests\Fixtures\Resolver\RepeatedNonRepeatableTarget;
use Tests\Fixtures\Resolver\SecondProvider;
use Tests\Fixtures\Resolver\UsesMarkedTrait;
use Tests\Fixtures\Resolver\UsesMarkedTraitAlias;
use Tests\Fixtures\Resolver\UsesProtectedTraitAlias;
use Tests\Fixtures\Resolver\WrongTargetDeclarationTarget;
use Tests\Fixtures\ResolverLazy\LazyTarget;
use Tests\Fixtures\ResolverLazy\ThrowingTarget;
use Tests\Support\IsolatedTestCase;

#[CoversClass(ClassResolver::class)]
final class ClassResolverTest extends IsolatedTestCase
{
    public function testR02DuplicateAndOverlappingDirectoriesProduceTheSameMetadata(): void
    {
        $root = $this->fixtureRoot();
        $expected = ['marked' => [FirstProvider::class]];
        foreach ([[$root], [$root, $root], [dirname($root), $root]] as $directories) {
            self::assertSame($expected, (new ClassResolver($directories))->metadataFor(PublicTarget::class)->methods);
        }
    }

    public function testR03NativePathsWithTrailingSeparatorSpaceUnicodeAndDotSegmentsAreScanned(): void
    {
        $source = $this->fixtureRoot();
        $special = $this->temporaryDirectory->path() . '/space 中文';
        self::assertTrue(mkdir($special));
        foreach (glob($source . '/*.php') ?: [] as $file) {
            copy($file, $special . '/' . basename($file));
        }

        $paths = [$special, $special . DIRECTORY_SEPARATOR, $special . '/./nested/..'];
        if (DIRECTORY_SEPARATOR === '\\') {
            $paths[] = str_replace('/', '\\', $special);
        }

        foreach ($paths as $path) {
            self::assertSame(Path::canonicalize($special), Path::canonicalize($path));
            self::assertTrue((new ClassResolver([$path]))->shouldWeave(PublicTarget::class));
        }
    }

    public function testR09OnlyAttributesDeclaredOnTheResolvedClassAreCollected(): void
    {
        $resolver = $this->resolver();
        self::assertSame(['inherited' => [FirstProvider::class]], $resolver->metadataFor(MarkedParent::class)->methods);
        self::assertFalse($resolver->shouldWeave(InheritsMarkedMethod::class));
        self::assertFalse($resolver->shouldWeave(OverridesWithoutAttribute::class));
        self::assertSame(['inherited' => [SecondProvider::class]], $resolver->metadataFor(OverridesWithAttribute::class)->methods);
    }

    public function testR10TraitMethodsFollowFinalReflectionWhileInterfaceAttributesAreNotInherited(): void
    {
        $resolver = $this->resolver();
        self::assertSame(['fromTrait' => [FirstProvider::class]], $resolver->metadataFor(UsesMarkedTrait::class)->methods);
        self::assertSame(['aliasedTrait' => [FirstProvider::class], 'fromTrait' => [FirstProvider::class]], $resolver->metadataFor(UsesMarkedTraitAlias::class)->methods);
        self::assertSame(['fromTrait' => [FirstProvider::class]], $resolver->metadataFor(UsesProtectedTraitAlias::class)->methods);
        self::assertFalse($resolver->shouldWeave(ImplementsMarkedInterface::class));
    }

    public function testR11ResolverLoadsLazilyOnceAndDoesNotCacheFailedLoadsAsSuccess(): void
    {
        unset($GLOBALS['resolver_lazy_loads'], $GLOBALS['resolver_throwing_loads']);
        $resolver = new ClassResolver([dirname($this->fixtureRoot()) . '/ResolverLazy']);
        self::assertArrayNotHasKey('resolver_lazy_loads', $GLOBALS);
        self::assertTrue($resolver->shouldWeave(LazyTarget::class));
        self::assertTrue($resolver->shouldWeave(LazyTarget::class));
        self::assertSame(1, $GLOBALS['resolver_lazy_loads']);

        try {
            $resolver->shouldWeave(ThrowingTarget::class);
            self::fail('R11 first load of the failing fixture must throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('controlled resolver load failure', $exception->getMessage());
        }
        self::assertFalse($resolver->shouldWeave(ThrowingTarget::class));
        self::assertSame(1, $GLOBALS['resolver_throwing_loads']);
    }

    public function testR12ProviderConstructorArgumentsAreNotReadDuringDiscovery(): void
    {
        ParameterProvider::$constructed = 0;
        $metadata = $this->resolver()->metadataFor(ParameterizedTarget::class);
        self::assertSame(['first' => [ParameterProvider::class], 'second' => [ParameterProvider::class]], $metadata->methods);
        self::assertSame(0, ParameterProvider::$constructed);
    }

    public function testR13MalformedProviderDeclarationsAreDeferredUntilAttributeInstantiation(): void
    {
        $resolver = $this->resolver();
        foreach ([MissingAttributeDeclarationTarget::class, WrongTargetDeclarationTarget::class, RepeatedNonRepeatableTarget::class, InvalidArgumentsTarget::class] as $class) {
            self::assertTrue($resolver->shouldWeave($class));
            $attribute = (new ReflectionClass($class))->getMethod('marked')->getAttributes()[0];
            self::assertAttributeInstantiationFails($attribute);
        }
    }

    public function testR14QueriesAreExactClassMapKeysIncludingCaseSlashAndAliases(): void
    {
        $resolver = $this->resolver();
        self::assertTrue($resolver->shouldWeave(PublicTarget::class));
        self::assertFalse($resolver->shouldWeave(strtolower(PublicTarget::class)));
        self::assertFalse($resolver->shouldWeave('\\' . PublicTarget::class));
        class_alias(PublicTarget::class, 'Tests\Fixtures\Resolver\PublicTargetAlias');
        self::assertFalse($resolver->shouldWeave('Tests\Fixtures\Resolver\PublicTargetAlias'));
    }

    private static function assertAttributeInstantiationFails(ReflectionAttribute $attribute): void
    {
        try {
            $attribute->newInstance();
            self::fail('R13 malformed attribute unexpectedly instantiated.');
        } catch (Error $error) {
            self::assertNotSame('', $error->getMessage());
        }
    }

    private function resolver(): ClassResolver
    {
        return new ClassResolver([$this->fixtureRoot()]);
    }

    private function fixtureRoot(): string
    {
        return dirname(__DIR__) . '/Fixtures/Resolver';
    }
}
