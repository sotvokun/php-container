# Container

[English](README.md) | **简体中文**

一个继承 Illuminate Container 的容器，提供可选的 Attribute 驱动 AOP 与构造函数延迟注入能力。它保留熟悉的 Illuminate 依赖注入 API，同时允许通过 PHP Attribute 拦截公共服务方法。

## 环境要求

- PHP 8.4 或更高版本
- 一个用于生成 AOP 代理类的可写目录

## 安装

```bash
composer require sotvokun/container
```

## 使用方式

`Sotvokun\Container\Container` 继承自 `Illuminate\Container\Container`。它本身就是一个 Illuminate Container，并额外提供两个可选能力：Attribute 驱动 AOP 与 `#[Lazy]` 构造函数延迟注入。两者均未启用时，解析行为保持 Illuminate Container 的原有语义。

### Illuminate Container 基础能力

本容器沿用 Illuminate Container 的使用方式，支持构造函数依赖自动解析、绑定与别名、transient/singleton/scoped 生命周期、上下文绑定、tag、extend、解析回调、`makeWith()` 以及 PSR-11 访问。

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

继承 Illuminate Container 的目的，是让应用继续使用熟悉的依赖注入 API，同时按需启用后文的扩展能力。

### Attribute 驱动 AOP

AOP 用于将日志、追踪、审计、鉴权、重试和事务等横切逻辑从业务方法中抽离，减少重复代码。只需在方法上添加 Attribute；该 Attribute 决定哪些拦截器会在原方法前后执行。

启用 AOP 后，容器扫描配置目录中支持的目标方法 Attribute。Ray AOP 会为匹配目标生成子类，容器再解析目标与拦截器。调用 `MethodInvocation::proceed()` 会依次进入下一个拦截器，最后调用原始方法。

#### 使用 AOP

先定义 Attribute 与拦截器：

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

将 Attribute 标记在服务方法上；在解析服务前配置 AOP，之后照常从容器解析：

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

#### AOP 规则与限制

扫描目录和代理类生成目录必须使用绝对路径。生成目录必须预先存在、位于所有扫描目录之外，且不应提交到版本控制。在 Windows 等并发启动场景中，应先由单进程生成或预热代理缓存，再启动多个工作进程。

目标必须是可实例化的非 final 类。被拦截方法必须直接声明在该类中，并且是 public、非 static、非 final，且不是构造函数或析构函数。带 Attribute 的 private 与 protected 方法会被忽略；继承方法需要在具体类中覆盖并重新标记后才会被拦截。

目前不支持以下签名或生命周期模式：通过命名参数向 variadic 参数传值、`never`、`parent` 参数或返回类型、以 `new` 表达式作为参数默认值、引用参数或引用返回、与 Ray AOP 内部成员（如 `_setBindings()`）冲突，以及在目标构造函数中调用带 Attribute 的方法。详细行为和规避方式见[不支持能力报告](docs/unsupported%20capabilities%20(zh).md)。

### 延迟注入

延迟注入将昂贵或很少使用的依赖推迟到真正需要时才构建。这样可避免在从未使用该依赖的请求或任务中执行不必要的工作与副作用。

#### 使用 Illuminate Container 的显式工厂

不使用本项目的 `#[Lazy]` 时，可采用显式工厂。工厂将延迟依赖明确呈现在消费者 API 中，并由应用决定实际解析发生的时机：

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

`ReportGenerator` 只会在 `export()` 调用工厂时构建。这是需要明确控制生命周期时，最符合 Illuminate Container 使用习惯的方式。

#### Attribute 标注式延迟注入

`enableLazyInjection()` 通过 Illuminate 的 `whenHasAttribute()` 注册 `Lazy` 命名空间下的默认解析器。Attribute 描述依赖，解析器负责检查并创建原生代理；未注册处理器时，Attribute 会提示启用延迟注入。目前不提供受支持的自定义解析器实现契约。

启用延迟注入后，在受支持的对象构造参数上使用 Attribute：

```php
use Sotvokun\Container\Attributes\Lazy;

$container->enableLazyInjection();

final class ExportController
{
    public function __construct(#[Lazy] private ReportGenerator $reports) {}
}
```

`#[Lazy]` 使用 PHP 8.4 的 `ReflectionClass::newLazyProxy()` 创建原生 lazy proxy，不会生成 Lazy 类文件。可通过 `#[Lazy(SpecialReportGenerator::class)]` 指定解析键；实际对象会在 PHP 初始化 proxy 时重新进入正常容器解析流程。

Lazy 注入的支持范围比普通容器解析更窄：它只接受能够在不执行用户代码时确定 concrete class 的单一、非 builtin、非 variadic 命名对象类型。union、intersection、builtin、无类型、`self`、`parent` 与 variadic 参数均会被拒绝；没有静态 concrete class 的用户 Closure/factory、不能代理的内部类、抽象代理类和 enum 也会被拒绝。兼容的已缓存实例会直接注入。

proxy 与 actual object 的身份不同。未初始化的 scoped proxy 会在首次初始化时按当时作用域解析；已初始化 proxy 在容器清理作用域缓存后仍持有原 actual object。应在创建 Lazy 消费者前完成 AOP、binding 和 alias 配置，并在初始化前保持稳定。未织入的无状态方法可能不会初始化 Native Lazy Object；织入后的 AOP 方法会触发初始化。

### 使用成本与生命周期约束

AOP 与延迟注入能够减少重复样板代码，但也会把工作移离表面上执行它的源码位置。AOP 会改变运行时类与调用栈；延迟注入会将构造、回调和异常推迟到依赖的首次使用。

使用这些能力时应保持约束：

- 在解析消费者前固定 AOP 配置和服务绑定。
- 将 scoped Lazy 依赖限定在一次请求或任务中，不要将其消费者保存到 singleton 或跨作用域使用。
- 用日志、追踪和集成测试观察拦截器顺序、Lazy 初始化和失败路径。
- 当依赖的创建时机或所有权需要在调试时一目了然，应优先使用显式工厂。

## 开发

```bash
composer test           # 当前支持范围；必须通过
composer test:upstream  # 已知上游限制
composer test:all       # 完整诊断测试集
composer analysis
composer format:check
composer validate --strict
```

`composer test` 是发布门禁，会排除 PHPUnit 的 `upstream` group。上游测试保留原有的成功契约，因此在 Ray AOP 修复已登记限制前会有意返回非零状态；可通过 `composer test:upstream` 单独执行，`composer test:all` 则执行两个范围。完整的预期失败和行为验证矩阵见[测试报告](docs/test%20report%20(zh).md)。

## 许可证

本项目使用 [MIT License](LICENSE) 开源。
