<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Sotvokun\Container\Aop\ClassResolver;

$scenario = $argv[1] ?? '';
$directory = sys_get_temp_dir() . '/resolver-r16-' . bin2hex(random_bytes(8));
mkdir($directory);
$write = static function (string $name, string $contents) use ($directory): void {
    file_put_contents($directory . '/' . $name . '.php', "<?php\n" . $contents);
};
$provider = <<<'PHP'
    namespace ResolverProcessR16;
    #[\Attribute(\Attribute::TARGET_METHOD)]
    class Provider implements \Sotvokun\Container\Aop\InterceptorProvider { public static function interceptors(): array { return []; } }
    PHP;
$good = <<<'PHP'
    namespace ResolverProcessR16;
    require_once __DIR__ . '/00Provider.php';
    class GoodTarget { #[Provider] public function marked(): void {} }
    PHP;
$write('00Provider', $provider);
$write('01Good', $good);
$result = [];
try {
    if ($scenario === 'duplicates') {
        $duplicate = "namespace ResolverProcessR16; require_once __DIR__ . '/00Provider.php'; class DuplicateTarget { #[Provider] public function marked(): void {} }";
        $write('02DuplicateA', $duplicate);
        $write('03DuplicateB', $duplicate);
        $resolver = new ClassResolver([$directory]);
        $result = ['edge' => $resolver->shouldWeave(ResolverProcessR16\DuplicateTarget::class), 'good' => $resolver->shouldWeave(ResolverProcessR16\GoodTarget::class)];
    } elseif ($scenario === 'non_classes') {
        $write('02Kinds', 'namespace ResolverProcessR16; interface AnInterface {} trait ATrait {} enum AnEnum { case One; }');
        $write('03Conditional', 'namespace ResolverProcessR16; if (false) { class ConditionalTarget {} }');
        $resolver = new ClassResolver([$directory]);
        $result = [
            'interface' => $resolver->shouldWeave(ResolverProcessR16\AnInterface::class),
            'trait' => $resolver->shouldWeave(ResolverProcessR16\ATrait::class),
            'enum' => $resolver->shouldWeave(ResolverProcessR16\AnEnum::class),
            'conditional' => $resolver->shouldWeave(ResolverProcessR16\ConditionalTarget::class),
            'good' => $resolver->shouldWeave(ResolverProcessR16\GoodTarget::class),
        ];
    } elseif ($scenario === 'missing_dependency') {
        $write('02Missing', 'namespace ResolverProcessR16; class MissingTarget extends MissingBase {}');
        $resolver = new ClassResolver([$directory]);
        try {
            $resolver->shouldWeave(ResolverProcessR16\MissingTarget::class);
        } catch (Throwable $throwable) {
            $result['error'] = $throwable::class;
        }
        $result['good'] = $resolver->shouldWeave(ResolverProcessR16\GoodTarget::class);
    } elseif ($scenario === 'syntax_error') {
        $write('02Syntax', 'namespace ResolverProcessR16; class SyntaxTarget { public function broken( }');
        try {
            $resolver = new ClassResolver([$directory]);
            $resolver->shouldWeave(ResolverProcessR16\SyntaxTarget::class);
        } catch (Throwable $throwable) {
            $result['error'] = $throwable::class;
        }
        unlink($directory . '/02Syntax.php');
        $result['freshGood'] = (new ClassResolver([$directory]))->shouldWeave(ResolverProcessR16\GoodTarget::class);
    }
} finally {
    foreach (glob($directory . '/*.php') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
