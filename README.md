# Container

**English** | [简体中文](README.zh-CN.md)

An Illuminate container with opt-in attribute-driven AOP and lazy constructor injection. It retains Illuminate's dependency-injection API while allowing public service methods to be intercepted through PHP attributes.

## Requirements

- PHP 8.4 or later
- A writable directory for generated AOP proxy classes

## Installation

```bash
composer require sotvokun/container
```

## Usage

`Sotvokun\Container\Container` extends `Illuminate\Container\Container`. It is an Illuminate container with two opt-in capabilities: attribute-driven AOP and `#[Lazy]` constructor injection. When neither is enabled, normal Illuminate resolution semantics remain in effect.

### Illuminate Container foundation

Use this container as you would Illuminate Container. It supports automatic constructor resolution, bindings and aliases, transient, singleton, and scoped lifecycles, contextual bindings, tags, extenders, resolving callbacks, `makeWith()`, and PSR-11 access.

```php
use App\Contract\Filesystem;
use App\Controller\PhotoController;
use App\Service\OrderService;
use App\Storage\LocalFilesystem;
use Sotvokun\Container\Container;

$container = new Container();

$container->singleton(Filesystem::class, LocalFilesystem::class);

$container->when(PhotoController::class)
    ->needs(Filesystem::class)
    ->give(LocalFilesystem::class);

$orderService = $container->make(OrderService::class);
```

This inheritance is deliberate: application code can keep using Illuminate's familiar dependency-injection API while selectively enabling the additional behavior below.

### Attribute-driven AOP

AOP keeps cross-cutting work—such as tracing, auditing, authorization, retries, or transactions—out of business methods. Mark a method with an attribute; the attribute supplies interceptors that run around the original method.

When AOP is enabled, the container scans the configured source directories for supported method attributes. Ray AOP generates a subclass for each matching target, and the container resolves both the target and its interceptors. Calling `MethodInvocation::proceed()` advances through the interceptor chain and eventually invokes the original method.

#### Use AOP

First, define an attribute and interceptor:

```php
namespace App\Aop;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;
use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Trace implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [TraceInterceptor::class];
    }
}

final class TraceInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        error_log('Entering ' . $invocation->getMethod()->getName());

        try {
            return $invocation->proceed();
        } finally {
            error_log('Leaving ' . $invocation->getMethod()->getName());
        }
    }
}
```

Apply it to a service method, configure AOP before resolving the service, then resolve it normally:

```php
namespace App\Service;

use App\Aop\Trace;
use Sotvokun\Container\Container;

final class GreetingService
{
    #[Trace]
    public function greet(string $name): string
    {
        return "Hello, {$name}";
    }
}

$container = new Container();

$sourceDirectory = __DIR__ . '/src';
$generatedDirectory = __DIR__ . '/var/aop';

if (! is_dir($generatedDirectory)) {
    mkdir($generatedDirectory, 0775, true);
}

$container->enableAop([$sourceDirectory], $generatedDirectory);

echo $container->make(GreetingService::class)->greet('World');
```

#### AOP rules and limits

The scan directories and generated-class directory must be absolute paths. The generated directory must already exist, must be outside every scan directory, and should not be committed. Generate or warm the proxy cache in one process before starting concurrent Windows workers.

A target must be an instantiable, non-final class. An intercepted method must be declared directly on it and be public, non-static, non-final, and neither its constructor nor destructor. Private and protected attributed methods are ignored; inherited methods must be overridden and attributed on the concrete class.

Some PHP signatures and lifecycle patterns are not supported: named values collected by variadic parameters, `never`, `parent` parameter or return types, `new` default parameter expressions, reference parameters or returns, members colliding with Ray AOP internals such as `_setBindings()`, and calling an attributed method from the target constructor. See the [unsupported capabilities report](docs/unsupported%20capabilities%20(zh).md) for details and workarounds.

### Lazy constructor injection

Lazy injection postpones construction of an expensive or rarely used dependency until it is actually needed. This can avoid work and side effects on requests or jobs that never use that dependency.

#### Explicit factories with Illuminate Container

Without this package's `#[Lazy]` attribute, use an explicit factory. The factory makes the deferred dependency visible in the consumer's API and gives you complete control over when resolution happens:

```php
use Illuminate\Contracts\Container\Container as ContainerContract;

interface ReportGeneratorFactory
{
    public function make(): ReportGenerator;
}

final class ContainerReportGeneratorFactory implements ReportGeneratorFactory
{
    public function __construct(private ContainerContract $container) {}

    public function make(): ReportGenerator
    {
        return $this->container->make(ReportGenerator::class);
    }
}

final class ExportController
{
    public function __construct(private ReportGeneratorFactory $reports) {}

    public function export(): string
    {
        return $this->reports->make()->export();
    }
}

$container->bind(
    ReportGeneratorFactory::class,
    ContainerReportGeneratorFactory::class,
);
```

`ReportGenerator` is constructed only when `export()` calls the factory. This is the usual Illuminate-compatible option when explicit lifetime control matters.

#### Attribute-based lazy injection

`enableLazyInjection()` registers the default resolver from the `Lazy` namespace through Illuminate's `whenHasAttribute()`. The attribute describes the dependency; the resolver validates it and creates the native proxy. Without registration, the attribute reports that lazy injection must be enabled. Custom resolver implementations are not currently a supported extension contract.

Enable lazy injection and annotate a supported object constructor parameter:

```php
use Sotvokun\Container\Attributes\Lazy;

$container->enableLazyInjection();

final class ExportController
{
    public function __construct(#[Lazy] private ReportGenerator $reports) {}
}
```

`#[Lazy]` creates a PHP 8.4 native lazy proxy with `ReflectionClass::newLazyProxy()`; it does not generate a lazy class file. `#[Lazy(SpecialReportGenerator::class)]` selects an explicit resolution key. The actual object is resolved through the normal container flow when PHP initializes the proxy.

Lazy injection is intentionally narrower than normal container resolution. It accepts only a single, non-builtin, non-variadic named object type whose concrete class can be determined without running user code. Union, intersection, builtin, untyped, `self`, `parent`, and variadic parameters are rejected. User closures or factories with no statically known concrete class are rejected, as are unsupported internal classes, abstract proxy classes, and enums. Compatible cached instances are injected directly.

A proxy and its actual object have different identities. An uninitialized scoped proxy resolves in the scope active at initialization; an initialized proxy retains its actual object after the container scope cache is cleared. Configure AOP, bindings, and aliases before creating a lazy consumer, then keep them stable until initialization. Unwoven stateless methods may not initialize a native lazy object; woven AOP methods do initialize it.

### Operational costs and lifecycle discipline

AOP and lazy injection reduce repeated plumbing, but they also move work away from the source line that appears to perform it. AOP changes the runtime class and call stack, while lazy injection can move construction, callbacks, and failures to the first use of a dependency.

Use these features deliberately:

- Keep AOP configuration and service bindings stable before resolving consumers.
- Treat scoped lazy dependencies as request- or job-local; do not retain their consumers in singletons or across scopes.
- Make interceptor order, lazy initialization, and failure paths observable with logs, tracing, and integration tests.
- Prefer explicit factories when a dependency's creation time or ownership needs to be immediately obvious during debugging.

## Development

```bash
composer test           # supported behavior; must pass
composer test:upstream  # known upstream limitations
composer test:all       # complete diagnostic suite
composer analysis
composer format:check
composer validate --strict
```

`composer test` is the release gate and excludes the `upstream` PHPUnit group. The upstream tests keep their original successful contracts, so they intentionally return a non-zero status until Ray AOP fixes the recorded limitations. They remain available through `composer test:upstream`; `composer test:all` runs both scopes. The [test report](docs/test%20report%20(zh).md) records the expected failures and the verified behavior matrix.

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
