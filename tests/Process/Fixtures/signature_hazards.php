<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Sotvokun\Container\Aop\ClassResolver;
use Sotvokun\Container\Aop\Weaver;
use Tests\Fixtures\Signature\ProceedInterceptor;
use Tests\Fixtures\Signature\SignatureProvider;
use Tests\Support\TemporaryDirectory;

require dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/Fixtures/Signature/SignatureFixtures.php';

$scenario = $argv[1] ?? '';
$fixtures = dirname(__DIR__, 2) . '/Fixtures/Signature';
$temporary = new TemporaryDirectory();

try {
    SignatureProvider::$interceptors = [new ProceedInterceptor()];
    $weaver = new Weaver(new Container(), new ClassResolver([$fixtures]), $temporary->generatedClasses());

    if (str_starts_with($scenario, 'default_')) {
        $class = match ($scenario) {
            'default_constant' => Tests\Fixtures\Signature\DefaultConstantTarget::class,
            'default_enum' => Tests\Fixtures\Signature\DefaultEnumTarget::class,
            'default_new' => Tests\Fixtures\Signature\DefaultNewTarget::class,
            'default_variadic' => Tests\Fixtures\Signature\NamedVariadicTarget::class,
        };
        $target = $weaver->newInstance($class, []);
        $result = match ($scenario) {
            'default_constant' => [$target->values(), $target->values(label: 'named')],
            'default_enum' => $target->value()->value,
            'default_new' => $target->value(),
            'default_variadic' => $target->values(first: 'named', rest: 'a', extra: 'b'),
        };
        echo json_encode($result, JSON_THROW_ON_ERROR);
    } elseif (in_array($scenario, ['self', 'parent', 'static'], true)) {
        $class = match ($scenario) {
            'self' => Tests\Fixtures\Signature\SelfReturnTarget::class,
            'parent' => Tests\Fixtures\Signature\ParentReturnTarget::class,
            'static' => Tests\Fixtures\Signature\StaticReturnTarget::class,
        };
        $target = $weaver->newInstance($class, []);
        echo $target->fluent() === $target ? 'same' : 'different';
    } elseif ($scenario === 'never') {
        require_once $fixtures . '/NeverTarget.php';
        $target = $weaver->newInstance(Tests\Fixtures\Signature\NeverTarget::class, []);
        try {
            $target->neverReturns();
        } catch (RuntimeException $exception) {
            echo $exception->getMessage();
        }
    } elseif ($scenario === 'reference_parameter') {
        SignatureProvider::$interceptors = [new ProceedInterceptor(), new ProceedInterceptor()];
        $target = $weaver->newInstance(Tests\Fixtures\Signature\ReferenceParameterTarget::class, []);
        $argument = 'input';
        $target->mutate($argument);
        echo $argument;
    } elseif ($scenario === 'reference_return') {
        SignatureProvider::$interceptors = [new ProceedInterceptor(), new ProceedInterceptor()];
        require_once $fixtures . '/ReferenceTarget.php';
        $target = $weaver->newInstance(Tests\Fixtures\Signature\ReferenceTarget::class, []);
        $reference = &$target->reference();
        $reference = 'changed';
        echo $target->reference();
    } elseif ($scenario === 'readonly') {
        require_once $fixtures . '/ReadonlyTarget.php';
        $target = $weaver->newInstance(Tests\Fixtures\Signature\ReadonlyTarget::class, ['ready']);
        echo $target->read();
    } elseif ($scenario === 'magic') {
        require_once $fixtures . '/MagicTarget.php';
        $target = $weaver->newInstance(Tests\Fixtures\Signature\MagicTarget::class, []);
        echo json_encode(['invoke' => $target('x'), 'call' => $target->missing('a')], JSON_THROW_ON_ERROR);
    } elseif ($scenario === 'collision') {
        require_once $fixtures . '/BindingMemberCollisionTarget.php';
        try {
            $weaver->newInstance(Tests\Fixtures\Signature\BindingMemberCollisionTarget::class, []);
            echo 'created';
        } catch (Throwable $throwable) {
            echo $throwable::class;
        }
    }
} finally {
    $temporary->remove();
}
