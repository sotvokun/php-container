<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Sotvokun\Container\Aop\ClassResolver;
use Sotvokun\Container\Aop\Weaver;
use Tests\Fixtures\Generated\GeneratedLog;
use Tests\Fixtures\Generated\GeneratedProvider;
use Tests\Fixtures\Generated\GeneratedTarget;

require dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/Fixtures/Generated/GeneratedFixtures.php';

$scenario = $argv[1] ?? '';
$root = $argv[2] ?? '';
$fixtures = dirname(__DIR__, 2) . '/Fixtures/Generated';

$create = static function (string $scan, string $generated, ?GeneratedLog $log = null): array {
    $log ??= new GeneratedLog();
    $container = new Container();
    $container->instance(GeneratedLog::class, $log);
    $weaver = new Weaver($container, new ClassResolver([$scan]), $generated);
    $target = $weaver->newInstance(GeneratedTarget::class, []);

    return [$target, $log];
};

if ($scenario === 'G04' || $scenario === 'G07') {
    [$target, $log] = $create($fixtures, $root);
    $value = $target->run($scenario);
    $files = glob($root . '/*.php') ?: [];
    echo json_encode([
        'value' => $value,
        'class' => $target::class,
        'events' => $log->events,
        'files' => count($files),
        'hash' => isset($files[0]) ? hash_file('sha256', $files[0]) : null,
        'mtime' => isset($files[0]) ? filemtime($files[0]) : null,
    ], JSON_THROW_ON_ERROR);
    return;
}

if ($scenario === 'G05') {
    $value = $argv[3] ?? 'value';
    $mtime = (int) ($argv[4] ?? time());
    $generated = $argv[5] ?? $root . '/generated';
    $bindingMarker = $argv[6] ?? '';
    if (!is_dir($generated)) {
        mkdir($generated, 0777, true);
    }
    $source = $root . '/CacheTarget.php';
    $code = '<?php namespace Tests\ProcessFixture; class CacheTarget { #[\Tests\Fixtures\Generated\GeneratedProvider] public function run(): string { return ' . var_export($value, true) . '; } }';
    file_put_contents($source, $code);
    touch($source, $mtime);
    clearstatcache(true, $source);
    $log = new GeneratedLog();
    if ($bindingMarker !== '') {
        $log->events[] = $bindingMarker;
    }
    GeneratedProvider::interceptors();  // Ensure the provider is loaded before scanning.
    $container = new Container();
    $container->instance(GeneratedLog::class, $log);
    $weaver = new Weaver($container, new ClassResolver([$root]), $generated);
    $target = $weaver->newInstance(Tests\ProcessFixture\CacheTarget::class, []);
    echo json_encode(['class' => $target::class, 'value' => $target->run(), 'files' => count(glob($generated . '/*.php') ?: [])], JSON_THROW_ON_ERROR);
    return;
}

if ($scenario === 'G06-load') {
    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });
    try {
        [$target] = $create($fixtures, $root);
        echo json_encode(['result' => $target->run()], JSON_THROW_ON_ERROR);
    } catch (Throwable $throwable) {
        echo json_encode(['error' => $throwable::class, 'message' => $throwable->getMessage()], JSON_THROW_ON_ERROR);
    } finally {
        restore_error_handler();
    }
    return;
}

if ($scenario === 'G06-disappearing') {
    $lost = $root . '/lost';
    $clean = $root . '/clean';
    mkdir($lost);
    mkdir($clean);
    $container = new Container();
    $container->instance(GeneratedLog::class, new GeneratedLog());
    $weaver = new Weaver($container, new ClassResolver([$fixtures]), $lost);
    rmdir($lost);
    try {
        $weaver->newInstance(GeneratedTarget::class, []);
        $error = null;
    } catch (Throwable $throwable) {
        $error = $throwable::class;
    }
    [$recovered] = $create($fixtures, $clean);
    echo json_encode(['error' => $error, 'recovered' => $recovered->run()], JSON_THROW_ON_ERROR);
}
