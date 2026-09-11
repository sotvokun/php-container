# Container

[English](README.md) | **简体中文**

一个基于 Illuminate Container 的 Attribute 驱动 AOP 扩展。它保留熟悉的 Illuminate 依赖注入 API，同时允许通过 PHP Attribute 拦截公共服务方法。

## 环境要求

- PHP 8.3 或更高版本
- 一个用于生成 AOP 代理类的可写目录

## 安装

```bash
composer require sotvokun/container
```

## 使用方式

### 普通容器

不启用 AOP 时，本项目与 Illuminate Container 的用法一致，支持常规绑定、单例、别名和自动依赖解析：

```php
use App\Contract\Mailer;
use App\Service\OrderService;
use App\Service\SmtpMailer;
use Sotvokun\Container\Container;

$container = new Container();
$container->bind(Mailer::class, SmtpMailer::class);

$service = $container->make(OrderService::class);
```

Contextual Binding（上下文绑定）可以让同一个依赖针对特定消费者解析为不同实现：

```php
use App\Contract\Filesystem;
use App\Controller\PhotoController;
use App\Storage\LocalFilesystem;

$container->when(PhotoController::class)
    ->needs(Filesystem::class)
    ->give(LocalFilesystem::class);
```

本容器通过 Illuminate Container 实现 PSR-11，也允许应用通过继承创建自定义容器子类。

### AOP

首先创建一个 Attribute，用它声明应用于方法的拦截器：

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

实现拦截器。拦截器通过容器解析，因此可以使用构造函数依赖注入：

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

将 Attribute 应用到公共服务方法：

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

启用 AOP，然后像平常一样从容器解析服务：

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

扫描目录和代理类生成目录都必须使用绝对路径。生成目录必须已经存在，并且必须位于所有扫描目录之外。

#### 拦截机制

- `InterceptorProvider` Attribute 声明拦截器类名或拦截器实例。
- 拦截器类和应用服务由同一个容器解析。
- 多个 Attribute 和多个拦截器会组成嵌套调用链。
- 调用 `MethodInvocation::proceed()` 会进入下一个拦截器或原始方法。
- `getArguments()` 暴露位置参数，`getNamedArguments()` 提供命名参数快照。
- 没有匹配方法 Attribute 的类继续使用 Illuminate 原始构建路径。

#### AOP 目标规则

被拦截的目标必须是可实例化的非 final 类。被拦截方法必须直接声明在该类中，并且必须是 public、非 static、非 final，同时不能是构造函数或析构函数。

带 Attribute 的 private 和 protected 方法会被忽略。继承方法不会自动被拦截；具体目标类需要覆盖该方法并在覆盖方法上添加 Attribute。

#### 代理生成与部署

代理类会在第一次使用时生成，之后从生成目录复用。不要把生成目录放在被扫描的源码目录中，也不要把生成的代理文件提交到版本控制。

在并发生产部署中，特别是在 Windows 上，建议先用单进程预热代理缓存，再启动多个工作进程；也可以为并发生成进程分配不同目录。

#### 已知限制

经过 AOP 的方法目前不能依赖以下签名或生命周期模式：

- 通过命名参数向 variadic 参数传值；
- `never` 返回类型；
- `parent` 参数或返回类型；
- 使用 `new` 表达式作为参数默认值；
- 引用参数或引用返回；
- 与 Ray AOP 内部成员冲突的业务成员，尤其是 `_setBindings()`；
- 在目标类构造函数中调用带 Attribute 的方法。

详细行为及规避方案见[不支持能力报告](docs/2026-09-11%20UNSUPPORTED%20CAPABILITIES%20(ZH).md)。

例如，被拦截方法的参数不能使用 `new` 表达式作为默认值。可以改为在方法内部创建默认对象：

```php
// 不支持
#[Trace]
public function handle(Options $options = new Options()): void {}

// 支持的替代方式
#[Trace]
public function handle(?Options $options = null): void
{
    $options ??= new Options();
}
```

引用参数和引用返回无法穿过拦截器链保持引用关系，建议显式返回修改后的值：

```php
// 不支持
#[Trace]
public function normalize(string &$value): void {}

// 支持的替代方式
#[Trace]
public function normalize(string $value): string
{
    return trim($value);
}
```

## 开发

```bash
composer test           # 当前支持范围；必须通过
composer test:upstream  # 已知上游限制
composer test:all       # 完整诊断测试集
composer analysis
composer format:check
composer validate --strict
```

`composer test` 是发布门禁，会排除 PHPUnit 的 `upstream` group。上游测试保留原有的成功契约，因此在 Ray AOP 修复已登记限制前会有意返回非零状态；可通过 `composer test:upstream` 单独执行，`composer test:all` 则执行两个范围。完整的预期失败和行为验证矩阵见[测试报告](docs/tests/2026-09-11%20TEST%20REPORT%20(ZH).md)。

## 许可证

本项目使用 [MIT License](LICENSE) 开源。
