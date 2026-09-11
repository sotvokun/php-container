<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Sotvokun\Container\Aop\ClassResolver;

$directory = sys_get_temp_dir() . '/resolver-r15-' . bin2hex(random_bytes(8));
mkdir($directory);
$provider = <<<'PHP'
    <?php
    namespace ResolverProcessR15;
    #[\Attribute(\Attribute::TARGET_METHOD)]
    class Provider implements \Sotvokun\Container\Aop\InterceptorProvider { public static function interceptors(): array { return []; } }
    PHP;
$target = <<<'PHP'
    <?php
    namespace ResolverProcessR15;
    require_once __DIR__ . '/Provider.php';
    class AddedTarget { #[Provider] public function marked(): void {} }
    PHP;
file_put_contents($directory . '/Provider.php', $provider);
$old = new ClassResolver([$directory]);
$before = $old->shouldWeave(ResolverProcessR15\AddedTarget::class);
file_put_contents($directory . '/AddedTarget.php', $target);
$oldAfterAdd = $old->shouldWeave(ResolverProcessR15\AddedTarget::class);
$freshAfterAdd = (new ClassResolver([$directory]))->shouldWeave(ResolverProcessR15\AddedTarget::class);
unlink($directory . '/AddedTarget.php');
unlink($directory . '/Provider.php');
rmdir($directory);
echo json_encode(compact('before', 'oldAfterAdd', 'freshAfterAdd'), JSON_THROW_ON_ERROR);
