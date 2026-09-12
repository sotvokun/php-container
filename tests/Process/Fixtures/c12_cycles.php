<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\CircularDependencyException;
use Sotvokun\Container\Container;
use Tests\Fixtures\Aop\CycleA;
use Tests\Fixtures\Aop\CycleInterceptorTarget;
use Tests\Fixtures\Aop\CycleSelf;
use Tests\Fixtures\Aop\IndependentAfterFailure;
use Tests\Support\TemporaryDirectory;

require dirname(__DIR__, 2) . '/bootstrap.php';

$temporaryDirectory = new TemporaryDirectory();

try {
    $container = new Container();
    $container->enableAop(
        [dirname(__DIR__, 2) . '/Fixtures/Aop'],
        $temporaryDirectory->generatedClasses(),
    );

    $results = [];
    foreach ([CycleSelf::class, CycleA::class, CycleInterceptorTarget::class] as $target) {
        try {
            $container->make($target);
            $results[$target] = 'no-exception';
        } catch (CircularDependencyException) {
            $results[$target] = 'circular';
        }
    }

    $results['recovery'] = $container->make(IndependentAfterFailure::class)->run();
    echo json_encode($results, JSON_THROW_ON_ERROR);
} finally {
    $temporaryDirectory->remove();
}
