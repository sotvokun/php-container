# AOP 不支持能力报告（中文）

记录日期：2026-09-11。适用依赖：Ray AOP 2.20.2。状态：以下问题均确认为上游限制，当前统一搁置。

本报告汇总 A06、S02、S03、S04、S05、S08、S10、G07。全部对应 PHPUnit `upstream` group：`composer test` 默认排除该组，作为当前支持范围的绿色发布门禁；`composer test:upstream` 单独复现这些限制；`composer test:all` 执行完整诊断集。项目保留真实失败测试，不修改 vendor、不降低断言、不使用永久 skip；只有上游发布修复或项目采用可维护的依赖补丁并完成回归后，才解除对应限制。

## 能力索引

| 编号 | 不支持能力 | 失败形式 | 状态 |
| --- | --- | --- | --- |
| A06 / S04 | AOP 方法接收命名 variadic 参数 | 参数静默丢失 | 搁置 |
| S02 | AOP 方法使用 `never` 返回类型 | 代理代码无法加载 | 搁置 |
| S03 | AOP 方法使用 `parent` 参数或返回类型 | 生成非法类型或改变声明语义 | 搁置 |
| S04 | AOP 方法参数使用默认 `new` 表达式 | 代理代码无法加载 | 搁置 |
| S05 | AOP 方法使用引用参数或引用返回 | 引用身份丢失或签名不兼容 | 搁置 |
| S08 | 业务成员与 Ray AOP 内部保留成员冲突 | warning 后抛出非诊断性 `TypeError` | 搁置 |
| S10 | 构造函数调用 AOP 标记方法 | 绑定尚未安装并抛 `TypeError` | 搁置 |
| G07 | Windows 多进程并发生成同一代理 | 间歇性 `NotWritableException` | 搁置；上游跨平台兼容性问题 |

## A06 / S04：命名 variadic 参数

能力边界：经过 AOP 代理的方法不能通过命名参数向 variadic 部分传值。普通命名参数、默认参数省略、显式 `null`、空参数、零项 variadic 和位置 variadic 已确认支持。

- 预期：`variadic(prefix: 'p', labels: 'a', b: 'b')` 返回 `['p', ['labels' => 'a', 'b' => 'b']]`。
- 实际：返回 `['p', []]`，命名 variadic 参数被静默丢弃。
- 原因：Ray AOP 生成的代理使用 `func_get_args()` 采集参数；额外命名参数保存在代理方法的 variadic 局部变量中，却未进入该数组，且在到达本项目 Adapter 前已经丢失。
- 规避：改用位置 variadic；改成显式数组参数；或不代理该方法。
- 回归：`tests/Integration/InvocationTest.php::testA06NamedVariadicArgumentsPreserveNamesAndValues`；`tests/Process/ProxySignatureProcessTest.php::testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts` 的 `S04 named variadic arguments` 数据集。两者均属于 `upstream` group。

解除条件：上游完整保留命名 variadic 的名称、值和顺序；一个或多个拦截器路径均通过；位置 variadic 无重复或回归；旧代理缓存可安全失效。

## S02：`never` 返回类型

能力边界：不能为返回类型为 `never` 的方法启用 AOP。nullable、union、intersection 和 DNF 类型已确认支持。

- 预期：代理合法加载，目标通过 `RuntimeException('never target')` 结束，异常原样传播。
- 实际：生成代理加载失败，PHP 报 `A never-returning method must not return`，子进程退出码 255。
- 原因：Ray AOP 保留 `: never` 签名，但生成方法体时只为 `void` 省略 `return`，从而为 `never` 生成非法显式返回。
- 规避：不标记 `never` 方法；拦截调用它之前的普通方法；业务契约允许时改用 `void`。
- 回归：`tests/Process/ProxySignatureProcessTest.php::testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts` 的 `S02 never return` 数据集，入口为 `tests/Process/Fixtures/signature_hazards.php`；属于 `upstream` group。

解除条件：生成方法不含显式 `return`；业务异常穿过完整拦截器链；其他返回类型无回归；代理缓存安全失效。

## S03：`parent` 参数或返回类型

能力边界：不能为参数或返回类型含 `parent` 的方法启用 AOP。`self`、`static` 和普通完整类名已确认支持。

- 预期：代理保持原业务方法声明上下文中的父类类型；链式方法可返回同一代理实例。
- 实际：Ray AOP 生成非法 `\parent`；即使只原样输出 `parent`，它在新增代理子类中的指向也会改变。
- 原因：上游类型字符串生成没有把 `parent` 解析为原声明类的实际父类名称。
- 规避：使用实际父类完整名称；在契约适合时使用 `self` 或 `static`；或不代理该方法。
- 回归：`tests/Process/ProxySignatureProcessTest.php::testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts` 的 `S03 parent return` 数据集；属于 `upstream` group。

解除条件：参数和返回类型都保持原声明上下文语义；合法对象身份和 PHP 类型约束通过；`self`、`static` 无回归。

## S04：默认 `new` 表达式

能力边界：不能代理参数默认值使用 `new` 表达式的方法。常量、类常量、枚举默认值及显式传入对象已确认支持。

- 预期：省略参数时执行合法默认表达式并得到 `default-object`。
- 实际：代理加载失败，PHP 报 `Constant expression contains invalid operations`，子进程退出码 255。
- 原因：Ray AOP 根据已实例化对象使用 `var_export()` 重建默认值，不能恢复合法的原始 `new` 表达式。
- 规避：改成 nullable 参数并在方法体创建对象；由调用者显式传入；或不代理该方法。
- 回归：`tests/Process/ProxySignatureProcessTest.php::testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts` 的 `S04 default new object` 数据集；属于 `upstream` group。

解除条件：上游保留或正确重建默认 `new` 表达式；省略与显式参数均通过；其他默认值无回归。

## S05：引用参数与引用返回

能力边界：不能为包含引用参数或引用返回的方法启用 AOP。

引用参数：

- 预期：目标修改 `string &$value` 后，调用者变量由 `input` 变为 `input:target`。
- 实际：调用者变量仍为 `input`。
- 原因：代理的 `func_get_args()`、参数数组和 invocation 展开过程切断了调用者引用身份。

引用返回：

- 预期：代理保留方法签名中的 `&`，修改返回引用后目标属性变为 `changed`。
- 实际：生成的覆盖方法遗漏 `&`，加载时因声明不兼容而致命退出，退出码 255；调用链本身也按值返回。
- 原因：Ray AOP 的签名生成和 invocation/interceptor 返回模型没有端到端保留引用。

规避：显式返回修改后的普通值；使用可变对象承载状态；拆分读写方法；或让引用方法绕过 AOP。

回归：`tests/Process/ProxySignatureProcessTest.php::testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts` 的 `S05 reference parameter` 与 `S05 reference return` 数据集；属于 `upstream` group。

解除条件：一个或多个拦截器后仍保留参数和返回引用身份；参数访问器不切断引用；普通参数、返回值及 variadic 无回归。

## S08：Ray AOP 内部成员冲突

能力边界：需要代理的业务类不能声明 `_setBindings()`，并应避免与 Ray 注入的 trait、接口或内部状态成员同名。

- 预期：生成或实例化前以清晰的 `InvalidArgumentException` 或专用异常拒绝冲突，并指出成员名。
- 实际：业务 `_setBindings()` 被纳入代理调用路径，产生 `Undefined array key "_setBindings"` warning，随后抛出 `TypeError`。
- 原因：Ray AOP 注入 `InterceptTrait`/`ReadOnlyInterceptTrait` 并依赖 `WeavedInterface::_setBindings()`，但未前置检查业务成员冲突。
- 规避：重命名业务成员；避免依赖 Ray 内部成员名；或让该类绕过 AOP。
- 回归：`tests/Process/ProxySignatureProcessTest.php::testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts` 的 `S08 member collision has explicit rejection` 数据集；属于 `upstream` group。

解除条件：上游维护并检查完整保留成员集合；标记和未标记同名成员都得到清晰拒绝；魔术方法及普通方法无回归。

## S10：构造函数调用标记方法

能力边界：AOP 目标的构造函数不能直接或间接调用带 Provider Attribute 的方法。

- 预期：构造阶段安全执行目标逻辑，构造完成后调用正常经过完整拦截器链；上游也可以定义另一种明确且可用的稳定契约。
- 实际：构造期间动态分派进入代理覆盖方法，绑定表尚为空，`ReflectiveMethodInvocation` 收到 null 并抛出 `TypeError`，对象无法创建。
- 原因：Ray AOP 先构造代理对象，构造结束后才调用 `_setBindings()`；生成的覆盖方法没有处理绑定尚未安装的阶段。
- 规避：构造函数只调用未标记的初始化方法；构造后由工厂或调用方显式调用标记方法；或让该类绕过 AOP。
- 回归：`tests/Integration/ProxySignatureTest.php::testS10ConstructorCallRunsUninterceptedBeforeBindingsThenLaterCallsAreIntercepted`；属于 `upstream` group。

解除条件：直接和间接构造调用均安全；构造后仍从完整链开始；构造异常不污染后续对象；普通、readonly 和依赖注入构造场景无回归。

## G07：Windows 多进程并发生成

能力边界：在 Windows 上，多个进程不能可靠地并发生成并发布同一个尚未缓存的 AOP 代理文件。代理已经完整生成后由多个进程只读复用不属于该限制。

- 预期：所有并发进程获得同一个完整代理类，代理文件内容和哈希一致，后续新进程可以复用该文件。
- 实际：Windows PHP 8.4.24 ZTS 下单次测试可能通过；连续 100 次定向压力复测中 32 次通过、68 次失败，失败均为 `Ray\Aop\Exception\NotWritableException`，未观察到挂起或其他失败类型。
- 原因：Ray AOP 2.20.2 的 `FilePutContents` 先在目标目录写临时文件，再调用 `rename($tmp, $file)` 原子发布。多个进程竞争时，首个进程创建目标文件，后续进程在 Windows 上不能依赖 POSIX 式 `rename()` 覆盖该目标，于是上游将发布竞争误报为目录或文件不可写。
- 影响范围：这是 Windows 优先暴露的上游跨平台兼容性问题；其他不支持原子覆盖式 `rename()` 的文件系统或挂载方式也可能受影响，因而不绝对限定为 Windows 独有。
- 规避：部署或启动阶段由单进程预热代理缓存；确保业务进程启动前代理文件已经生成；或让每个并发生成者使用独立生成目录。不要把并发首次生成失败当作普通权限问题盲目重试。
- 回归：`tests/Process/GeneratedClassProcessTest.php::testG07ConcurrentProcessesPublishOneCompleteReusableProxy`；属于 `upstream` group。

解除条件：上游把“目标已由竞争者成功发布”视为可复用结果，并验证目标文件完整且对应预期代理类；Windows 多进程压力测试稳定通过；POSIX 平台和已有代理缓存复用无回归。

## 上游定位

集中涉及的上游文件为 `vendor/ray/aop/src/AopCode.php`、`MethodSignatureString.php`、`ReflectiveMethodInvocation.php`、`InterceptTrait.php`、`ReadOnlyInterceptTrait.php`、`WeavedInterface.php`、`Weaver.php`、`Compiler.php` 和 `FilePutContents.php`。上游项目为 `ray-di/Ray.Aop`。

完整的测试状态、失败实际结果及测试架构见 `docs/tests/2026-09-11 TEST REPORT (ZH).md`。
