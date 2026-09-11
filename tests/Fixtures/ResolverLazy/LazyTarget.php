<?php

declare(strict_types=1);

namespace Tests\Fixtures\ResolverLazy;

require_once dirname(__DIR__) . '/Resolver/Providers.php';

$GLOBALS['resolver_lazy_loads'] = ($GLOBALS['resolver_lazy_loads'] ?? 0) + 1;

class LazyTarget
{
    #[\Tests\Fixtures\Resolver\FirstProvider]
    public function marked(): void {}
}
