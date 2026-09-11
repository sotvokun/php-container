# Container

**English** | [简体中文](README.zh-CN.md)

An attribute-driven AOP extension for the Illuminate container. It keeps the familiar Illuminate dependency-injection API while allowing public service methods to be intercepted through PHP attributes.

## Requirements

- PHP 8.3 or later
- A writable directory for generated AOP proxy classes

## Installation

```bash
composer require sotvokun/container
```

## Usage

### Container

Without AOP, the package behaves like the Illuminate container and supports its usual binding, singleton, aliasing, and automatic dependency-resolution APIs:

```php
use App\Contract\Mailer;
use App\Service\OrderService;
use App\Service\SmtpMailer;
use Sotvokun\Container\Container;

$container = new Container();
$container->bind(Mailer::class, SmtpMailer::class);

$service = $container->make(OrderService::class);
```

Contextual Binding lets the same dependency resolve to a different implementation for a particular consumer:

```php
use App\Contract\Filesystem;
use App\Controller\PhotoController;
use App\Storage\LocalFilesystem;

$container->when(PhotoController::class)
    ->needs(Filesystem::class)
    ->give(LocalFilesystem::class);
```

The container implements PSR-11 through Illuminate Container and can be extended when an application needs a custom container subclass.

### AOP

Create an attribute that declares the interceptors applied to a method:

```php
<?php

namespace App\Aop;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Trace implements InterceptorProvider
{
    public static function interceptors(): array
    {
        return [TraceInterceptor::class];
    }
}
```

Implement the interceptor. Interceptors are resolved through the container, so they may use constructor injection:

```php
<?php

namespace App\Aop;

use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

final class TraceInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        $method = $invocation->getMethod();
        error_log('Entering ' . $method->getName());

        try {
            return $invocation->proceed();
        } finally {
            error_log('Leaving ' . $method->getName());
        }
    }
}
```

Apply the attribute to a public service method:

```php
<?php

namespace App\Service;

use App\Aop\Trace;

class GreetingService
{
    #[Trace]
    public function greet(string $name): string
    {
        return "Hello, {$name}";
    }
}
```

Enable AOP and resolve the service as usual:

```php
<?php

use App\Service\GreetingService;
use Sotvokun\Container\Container;

$container = new Container();

$sourceDirectory = __DIR__ . '/src';
$generatedDirectory = __DIR__ . '/var/aop';

if (!is_dir($generatedDirectory)) {
    mkdir($generatedDirectory, 0775, true);
}

$container->withAop([$sourceDirectory], $generatedDirectory);

$service = $container->make(GreetingService::class);
echo $service->greet('World');
```

Both the scan directories and generated-class directory must be absolute paths. The generated directory must already exist and must be outside every scan directory.

#### How interception works

- `InterceptorProvider` attributes declare interceptor class names or interceptor instances.
- Interceptor classes are resolved from the same container as application services.
- Multiple attributes and multiple interceptors form a nested invocation chain.
- Calling `MethodInvocation::proceed()` advances to the next interceptor or the original method.
- `getArguments()` exposes positional arguments, while `getNamedArguments()` provides a named snapshot.
- Classes without matching method attributes continue through the normal Illuminate build path.

#### AOP target rules

An intercepted target must be an instantiable, non-final class. An intercepted method must be declared directly on that class and must be public, non-static, non-final, and neither a constructor nor a destructor.

Private and protected attributed methods are ignored. Inherited methods are not intercepted unless they are overridden and attributed on the concrete target class.

#### Generated proxies and deployment

Proxy classes are generated on first use and reused from the generated directory. Do not place this directory under a scanned source directory, and do not commit generated proxies to source control.

For concurrent production deployments—particularly on Windows—warm the proxy cache in a single process before starting multiple workers, or give concurrent generators separate directories.

#### Known limitations

Methods passing through AOP currently must not rely on these signatures or lifecycle patterns:

- named arguments captured by a variadic parameter;
- a `never` return type;
- `parent` parameter or return types;
- a parameter whose default value is a `new` expression;
- reference parameters or reference returns;
- business members that collide with Ray AOP internals, especially `_setBindings()`;
- calling an attributed method from the target constructor.

See the [unsupported capabilities report](docs/2026-09-11%20UNSUPPORTED%20CAPABILITIES%20(ZH).md) for detailed behavior and workarounds.

For example, do not use a `new` expression as the default value of an intercepted method parameter. Create the default inside the method instead:

```php
// Unsupported
#[Trace]
public function handle(Options $options = new Options()): void {}

// Supported alternative
#[Trace]
public function handle(?Options $options = null): void
{
    $options ??= new Options();
}
```

Reference parameters and reference returns are not preserved across the interceptor chain. Prefer returning the updated value explicitly:

```php
// Unsupported
#[Trace]
public function normalize(string &$value): void {}

// Supported alternative
#[Trace]
public function normalize(string $value): string
{
    return trim($value);
}
```

## Development

```bash
composer test           # supported behavior; must pass
composer test:upstream  # known upstream limitations
composer test:all       # complete diagnostic suite
composer analysis
composer format:check
composer validate --strict
```

`composer test` is the release gate and excludes the `upstream` PHPUnit group. The upstream tests keep their original successful contracts, so they intentionally return a non-zero status until Ray AOP fixes the recorded limitations. They remain available through `composer test:upstream`; `composer test:all` runs both scopes. The [test report](docs/tests/2026-09-11%20TEST%20REPORT%20(ZH).md) records the expected failures and the verified behavior matrix.

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
