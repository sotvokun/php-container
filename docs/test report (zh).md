# 测试报告

## 第零部分：报告使用说明

### 使用规则

- P0 为核心功能、状态污染和代理可用性；P1 为常用边界及兼容性；P2 为低频语法、缓存、进程和环境边界。
- U 使用单元替身验证本库接口；I 使用真实 Illuminate/Ray AOP、fixture 和临时代理目录；X 使用限时子进程隔离致命错误、类重定义、源文件变更和并发。
- “契约”按明确代码或继承语义断言；“风险”固定目标行为并允许失败暴露缺陷；“待定”先记录可观察行为，未决项不用永久 skip 伪装完成。
- 每个编号必须出现在测试方法名、数据集名或测试注释中；多场景项目只有在所有有效断言完成后才能标记 `[x]`。
- 失败必须记录预期、实际、原因和处置状态；不以修改断言、空测试、不实际执行的代码或完整依赖异常文案换取绿灯。
- 代理测试每例使用新容器和独立已验证临时目录；子进程有超时限制；不共享静态记录器、singleton 或可写代理目录。
- 验收必须覆盖顺序、对象身份、调用次数、异常类型/上下文和异常后恢复；覆盖率数字不能代替行为验证。

### 编号与类别

| 前缀 | 类别 | 主要测试层级 |
| --- | --- | --- |
| T | 测试基础设施 | U / I / X |
| C | Container 依赖注入、状态与生命周期 | I / X |
| R | ClassResolver 扫描与合法性 | U / I / X |
| W | Weaver 绑定、顺序与生命周期 | I |
| A | Invocation 与 Adapter 调用语义 | U / I |
| S | PHP 代理方法和签名边界 | I / X |
| F | 自定义 Reflection | U |
| G | 生成目录、代理缓存与进程 | I / X |
| L | Lazy 延迟注入与 AOP 组合 | I |
| V | 全套验收流程 | U / I / X / 静态检查 |

类别标记：P0/P1/P2 为优先级；U 为单元测试；I 为真实依赖集成测试；X 为独立进程或环境测试；契约/风险/待定表示断言依据。

## 第一部分：测试项目

### T：测试基础设施

对应文件：`tests/Unit/TestInfrastructureTest.php`、`tests/Integration/FixtureSmokeTest.php`、`tests/Process/ProcessRunnerTest.php`、`tests/Support/`。

- [x] T01 [P0] PHPUnit 配置、bootstrap 与测试套件隔离
- [x] T02 [P0] 稳定真实文件 fixture 与延迟加载
- [x] T03 [P0] 独立临时代理目录及安全清理
- [x] T04 [P0] 可序列化拦截器基线与专用负例
- [x] T05 [P1] 原生 Illuminate Container 对照 helper
- [x] T06 [P1] 子进程、超时、退出码及 stderr 捕获
- [x] T07 [P1] PHP 最低版本与已安装依赖的 composer.lock 一致性

### C：Container 依赖注入与状态恢复

对应文件：C01–C11、C13–C18 位于 `tests/Integration/ContainerAopTest.php`；C12、C15 的危险循环场景位于 `tests/Process/ContainerAopProcessTest.php` 及 `tests/Process/Fixtures/c12_cycles.php`、`c15_native_self.php`。

- [x] C01 [P0/I/契约] 未启用 AOP 时保持父容器行为
- [x] C02 [P0/I/契约] 非目标类及未标记方法保持普通构建
- [x] C03 [P0/I/契约] 无构造及有构造 AOP 类正常实例化
- [x] C04 [P0/I/契约] 具体类、接口、多层及 AOP 依赖注入
- [x] C05 [P0/I/契约] 原始目标类上下文绑定及嵌套隔离
- [x] C06 [P0/I/契约] makeWith 参数覆盖及状态清理
- [x] C07 [P1/I/契约] 默认值、nullable 及不可解析依赖
- [x] C08 [P1/I/风险] union、intersection、self/parent 与 variadic 构造参数
- [x] C09 [P0/I/契约] bind、alias、singleton 与重复解析
- [x] C10 [P1/I/契约] instance 与 Closure 工厂的代理边界
- [x] C11 [P0/I/契约] 构造、Provider 及拦截器失败后的恢复
- [x] C12 [P0/I/风险] 直接、间接及拦截器循环依赖
- [x] C13 [P1/I/契约] resolving、afterResolving 与 Attribute 回调
- [x] C14 [P0/I/契约] SelfBuilding 父容器语义
- [x] C15 [P1/I/待定] 构造期间容器解析及构造栈语义
- [x] C16 [P1/I/契约] 重复 enableAop 配置切换
- [x] C17 [P1/I/风险] 多容器共享代理目录的绑定隔离
- [x] C18 [P1/I/契约] 自定义 Container 子类保留扩展能力及 AOP 行为

### R：ClassResolver 扫描与合法性

对应文件：R01、R04–R08 位于 `tests/Unit/Aop/ClassResolverTest.php`；R02–R03、R09–R14 位于 `tests/Integration/ClassResolverTest.php`；R15–R16 位于 `tests/Process/ClassResolverProcessTest.php` 及其 Process fixtures。

- [x] R01 [P0/U/契约] 空、无效及不存在扫描目录
- [x] R02 [P1/I/契约] 多目录、重复及重叠目录去重
- [x] R03 [P1/I/风险] 当前平台原生路径、空格、中文、尾分隔符及点路径段
- [x] R04 [P0/U/契约] public Provider Attribute 识别
- [x] R05 [P0/U/契约] 多方法、多 Attribute 与声明顺序
- [x] R06 [P0/U/契约] final、abstract 与不可实例化类拒绝
- [x] R07 [P0/U/契约] static、final、构造及析构方法拒绝
- [x] R08 [P1/U/待定] private/protected 标记方法忽略
- [x] R09 [P1/I/契约] 父类方法与覆盖方法继承边界
- [x] R10 [P1/I/风险] trait、别名及接口 Attribute 边界
- [x] R11 [P1/I/契约] 延迟加载、单次加载及加载失败重试
- [x] R12 [P1/I/待定] Attribute 构造参数不参与静态绑定
- [x] R13 [P1/I/待定] 非法 Attribute 使用延迟报错
- [x] R14 [P1/I/待定] class map 精确键、大小写与 alias
- [x] R15 [P2/X/契约] 扫描快照及新增文件发现
- [x] R16 [P2/X/风险] 重复 FQCN、非 class、缺失依赖及语法错误

### W：Weaver 绑定、顺序与生命周期

对应文件：W01–W11 均位于 `tests/Integration/WeaverTest.php`，fixture 位于 `tests/Fixtures/Weaver/`。

- [x] W01 [P0/I/契约] weave 与 newInstance 基础行为
- [x] W02 [P0/I/契约] 类名、实例及混合拦截器列表
- [x] W03 [P0/I/契约] 多 Attribute 嵌套顺序及方法隔离
- [x] W04 [P1/I/契约] 空 Provider 保持原方法行为
- [x] W05 [P0/I/契约] 非法拦截器值及解析异常
- [x] W06 [P1/I/契约] Provider 异常与失败后重试
- [x] W07 [P1/I/契约] transient、singleton 与 Provider 生命周期
- [x] W08 [P0/I/契约] 构建不序列化拦截器状态
- [x] W09 [P2/I/契约] 拦截器状态不改变生成类或泄漏实例
- [x] W10 [P1/I/契约] 非目标类拒绝及独立 Weaver
- [x] W11 [P1/I/待定] weave-only 实例与绑定安装边界

### A：Invocation 与 Adapter 调用语义

对应文件：A01–A02 位于 `tests/Unit/Aop/InvocationAdapterTest.php`；A03–A10 位于 `tests/Integration/InvocationTest.php`。

- [x] A01 [P0/U/契约] MethodInterceptor Adapter 包装与异常传播
- [x] A02 [P0/U/契约] Invocation Adapter 方法转发与身份
- [x] A03 [P0/I/契约] proceed、短路及业务异常替代
- [x] A04 [P0/I/契约] 位置参数修改与 ArrayObject 身份
- [x] A05 [P1/I/待定] 命名参数只读快照
- [ ] A06 [P1/I/风险] 命名调用、默认值、null、空参数与 variadic
- [x] A07 [P1/I/契约] 原业务 ReflectionMethod 信息
- [x] A08 [P1/I/待定] 同一 invocation 重复 proceed
- [x] A09 [P1/I/风险] 异常、finally 与后续调用恢复
- [x] A10 [P1/I/风险] 非法参数及返回类型错误

### S：PHP 代理方法边界

对应文件：常规集成场景位于 `tests/Integration/ProxySignatureTest.php`；致命错误、类重定义及其他危险场景位于 `tests/Process/ProxySignatureProcessTest.php` 和 `tests/Process/Fixtures/signature_hazards.php`；fixture 位于 `tests/Fixtures/Signature/`。

- [x] S01 [P0/I/风险] 基础返回类型、假值及 void
- [ ] S02 [P1/I/风险] nullable、union、intersection、DNF 与 never
- [ ] S03 [P1/I/风险] self、static、parent 及链式返回
- [ ] S04 [P1/I/风险] 默认常量、默认 new、命名参数与 variadic
- [ ] S05 [P1/I/风险] 引用参数与引用返回
- [x] S06 [P1/I/待定] Generator 创建、迭代、异常及 finally
- [x] S07 [P1/I/风险] readonly、提升属性及 final 构造
- [ ] S08 [P2/X/风险] 魔术方法及 Ray 内部成员冲突
- [x] S09 [P1/I/待定] 内部调用、递归与 parent 调用
- [ ] S10 [P0/I/风险] 构造函数调用标记方法
- [x] S11 [P2/I/待定] clone 与 serialize/unserialize

### F：自定义 Reflection

对应文件：F01–F06 均位于 `tests/Unit/Aop/ReflectionTest.php`，fixture 位于 `tests/Fixtures/Reflection/`。

- [x] F01 [P1/U/契约] getAnnotations 顺序与实例化
- [x] F02 [P1/U/契约] getAnnotation 匹配与 flags
- [x] F03 [P1/U/契约] Attribute 实例化错误传播
- [x] F04 [P1/U/契约] declaring class、constructor 与 parent class
- [x] F05 [P1/U/待定] getMethods 原生过滤语义
- [x] F06 [P2/U/风险] 继承、trait、覆盖及反射信息完整性

### G：生成目录、缓存与进程

对应文件：G01–G03、G08 位于 `tests/Integration/GeneratedClassTest.php`；G04–G07 位于 `tests/Process/GeneratedClassProcessTest.php` 和 `tests/Process/Fixtures/generated_classes.php`；fixture 位于 `tests/Fixtures/Generated/`。

- [x] G01 [P0/I/契约] 可写、不存在及不可写生成目录
- [x] G02 [P1/I/风险] 绝对路径要求、文件路径、空格及中文
- [x] G03 [P1/I/契约] 同绑定同目录代理复用
- [x] G04 [P2/X/风险] 新进程加载磁盘代理缓存
- [x] G05 [P2/X/契约] mtime 和目录参与缓存键，拦截器状态不参与
- [x] G06 [P2/X/风险] 截断、错误、不可读缓存及目录消失
- [ ] G07 [P2/X/风险] 多进程并发生成（Windows 上游兼容性问题）
- [x] G08 [P2/I/风险] 生成目录与扫描树隔离及 symlink 防绕过

### L：Lazy 延迟注入与 AOP 组合

对应文件：L01–L12 位于 `tests/Integration/LazyInjectionTest.php`，编号同时记录在对应测试方法的注释中。

- [x] L01 [P0/I/契约] 类绑定延迟构建与容器生命周期：`testClassBindingIsActuallyDelayedAndUsesNormalContainerLifecycle`
- [x] L02 [P0/I/契约] 显式参数覆盖优先级与 factory 策略：`testExplicitParameterOverrideWinsAndFactoryStrategiesAreAppliedAtConsumerResolution`
- [x] L03 [P1/I/契约] factory 的 Eager 回退：`testEagerFallbackRunsFactoryNormally`
- [x] L04 [P1/I/契约] 内部类的 unsupported 策略：`testInternalClassUsesUnsupportedStrategyBeforeCreatingNativeProxy`
- [x] L05 [P1/I/契约] variadic 与相对类型统一拒绝：`testVariadicAndRelativeTypesAreUnresolvableRegardlessOfUnsupportedStrategy`
- [x] L06 [P1/I/契约] alias 与 contextual binding：`testAliasAndContextualBindingsRetainTheirResolutionSemantics`
- [x] L07 [P0/I/契约] 已缓存 singleton 与多个代理共享 actual：`testExistingSingletonIsInjectedDirectlyAndPendingProxiesShareTheActualSingleton`
- [x] L08 [P1/I/契约] 绑定变化后的原生类型兼容性检查：`testIncompatibleBindingChangeIsRejectedByNativeProxy`
- [x] L09 [P0/I/契约] 无状态织入方法初始化与拦截：`testStatelessAopMethodInitializesAndEntersAop`
- [x] L10 [P0/I/契约] 有状态织入方法、重复调用与瞬态类名稳定：`testStatefulMethodCombinesLazyInitializationWithAopSubclass`
- [x] L11 [P0/I/契约] AOP singleton 共享与缓存直接注入：`testLazyAopProxiesShareSingletonAndCachedInstanceIsInjectedDirectly`
- [x] L12 [P0/I/契约] 继承构造函数的实际消费者上下文与初始化后隔离：`testInheritedConstructorRetainsActualConsumerContextDuringInitialization`

L12 覆盖继承构造函数的构造延迟、显式初始化采用子类 contextual binding，以及初始化后全局和父类解析不受污染。测试通过不代表已覆盖全部 Lazy 边界：异常重试副作用、身份敏感拦截器、跨 scoped 生命周期等尚不能由以上测试给出完整保证。

### V：全套验收

对应文件：`phpunit.xml`、`phpstan.neon`、`tests/bootstrap.php` 及上述全部测试文件。

- [x] V01 最小真实 AOP 链及基础设施
- [ ] V02 全部 P0 通过
- [ ] V03 全部 P1 及待定契约完成
- [ ] V04 P2 与平台矩阵完成
- [x] V05 支持范围 Unit、Integration、Process 分套件及默认发布门禁执行
- [x] V06 PHPStan 静态检查执行
- [x] V07 默认及逆序隔离验证
- [x] V08 编号到测试及缺陷追踪
- [x] V09 行为断言优先于覆盖率数字

## 第二部分：发布验收分组

默认发布门禁使用 `composer test`，排除 PHPUnit `upstream` group；它验证当前公开承诺支持的行为，并须保持全绿。`composer test:upstream` 保留已知上游限制的原始成功契约，用于依赖升级后的定向复查，因此当前预期非零。`composer test:all` 运行两个范围，用于完整诊断。

| 范围 | 命令 | 当前结果 | 用途 |
| --- | --- | --- | --- |
| 支持范围 | `composer test` | 142 tests / 653 assertions，通过 | 发布和 CI 门禁 |
| Unit | `composer test:unit` | 45 tests / 169 assertions，通过 | 支持范围单元回归 |
| Integration | `composer test:integration` | 79 tests / 373 assertions，通过 | 支持范围集成回归 |
| Process | `composer test:process` | 18 tests / 111 assertions，通过 | 支持范围进程回归 |
| 上游探针 | `composer test:upstream` | 10 tests / 56 assertions，1 error、8 failures、1 warning | 复现登记的 Ray AOP 限制 |
| 完整诊断 | `composer test:all` | 151 tests / 674 assertions，1 error、9 failures、1 warning | 确认无登记外失败 |

L12 修复前的本日补充执行：`php vendor/bin/phpunit --exclude-group upstream --order-by=reverse` 为 141 tests / 646 assertions，通过；单独运行 `LazyInjectionTest.php` 为 11 tests / 53 assertions，通过。`composer analysis`、`composer format:check`（147 个文件）及 `composer validate --strict` 均通过。

L12 修复后补充验证：`composer test` 为 142 tests / 653 assertions；`composer test:integration` 为 79 tests / 373 assertions；`php vendor/bin/phpunit tests/Integration/LazyInjectionTest.php` 为 12 tests / 60 assertions，均通过。`composer analysis` 和 `composer format:check` 均通过。上表 Unit、Process、上游探针和完整诊断保留修复前的本日执行结果。

G07 在两种诊断运行中出现不同结果，因此断言数不能简单相加；失败时会提前结束部分断言。定向压力实验中，100 次执行有 68 次失败。

## 第三部分：失败测试列表

### 当前未通过

| 编号/场景 | 预期结果 | 实际结果 | 原因 | 是否处理 |
| --- | --- | --- | --- | --- |
| A06 命名 variadic | `variadic(prefix: 'p', labels: 'a', b: 'b')` 得到 `['p', ['labels' => 'a', 'b' => 'b']]` | 得到 `['p', []]`，参数静默丢失 | Ray AOP 2.20.2 代理通过 `func_get_args()` 采参，命名 variadic 未进入参数数组 | 搁置；上游问题 |
| S02 never 返回类型 | 代理合法加载，目标抛出的 `RuntimeException('never target')` 原样传播 | 生成代码在 `never` 方法中包含 `return`，子进程退出码 255 | Ray AOP 代码生成器只特殊处理 `void` | 搁置；上游问题 |
| S03 parent 返回类型 | 保持原声明上下文的父类类型并返回同一实例 | 生成非法 `\parent` 类型，子进程退出码 255 | Ray AOP 类型字符串生成未解析原声明上下文 | 搁置；上游问题 |
| S04 默认 new | 省略参数时合法创建默认对象并返回 `default-object` | 代理加载失败：`Constant expression contains invalid operations` | Ray AOP 用 `var_export()` 重建默认对象表达式 | 搁置；上游问题 |
| S04 命名 variadic | 得到 `['named', ['rest' => 'a', 'extra' => 'b']]` | 得到 `['named', []]` | 与 A06 相同，Ray AOP 使用 `func_get_args()` 丢失命名 variadic | 搁置；上游问题 |
| S05 引用参数 | 目标修改后调用者变量变为 `input:target` | 调用者变量仍为 `input` | Ray AOP 在参数数组/invocation 边界切断引用身份 | 搁置；上游问题 |
| S05 引用返回 | 代理保留 `&`，修改返回引用后目标状态变为 `changed` | 代理覆盖签名遗漏 `&`，加载时兼容性致命错误，退出码 255 | Ray AOP 签名生成及调用链按值返回 | 搁置；上游问题 |
| S08 `_setBindings` 冲突 | 生成前以明确 `InvalidArgumentException` 拒绝并指出冲突成员 | 产生 `Undefined array key` warning，随后抛 `TypeError` | Ray AOP 未检查代理内部保留成员冲突 | 搁置；上游问题 |
| S10 构造期间调用标记方法 | 构造阶段安全执行，构造后正常拦截 | 未安装绑定时读取空项，`ReflectiveMethodInvocation` 收到 null 并抛 `TypeError` | Ray AOP 在目标构造完成后才调用 `_setBindings()` | 搁置；上游问题 |
| G07 Windows 多进程并发生成 | 并发进程发布并复用同一个完整代理文件 | 完整诊断抛出同类异常，单独 upstream 运行通过；定向压力复测 100 次中 68 次抛出 `Ray\Aop\Exception\NotWritableException`；单次运行可能通过 | Ray AOP 先写临时文件，再以 `rename()` 发布到同一目标；Windows 不提供其所依赖的 POSIX 式覆盖语义，竞争失败被报告为不可写 | 搁置；Windows 优先暴露的上游跨平台兼容性问题 |

#### 当前 upstream 失败定位

- A06：`tests/Integration/InvocationTest.php::testA06NamedVariadicArgumentsPreserveNamesAndValues`
- S02、S03、S04、S05、S08：`tests/Process/ProxySignatureProcessTest.php::testUpstreamIsolatedProxyEdgesMeetTheirRuntimeContracts`，具体复现入口为 `tests/Process/Fixtures/signature_hazards.php`
- S10：`tests/Integration/ProxySignatureTest.php::testS10ConstructorCallRunsUninterceptedBeforeBindingsThenLaterCallsAreIntercepted`
- G07：`tests/Process/GeneratedClassProcessTest.php::testG07ConcurrentProcessesPublishOneCompleteReusableProxy`，Windows PHP 8.4.24 ZTS 下定向压力复测可稳定复现

以上测试均带有 PHPUnit `#[Group('upstream')]`，并保留原始成功契约断言。

详细的影响范围、规避方式和上游修复条件见[aop 不支持能力报告](unsupported%20capabilities%20(zh).md)。
