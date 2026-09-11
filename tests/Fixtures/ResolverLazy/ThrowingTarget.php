<?php

declare(strict_types=1);

namespace Tests\Fixtures\ResolverLazy;

$GLOBALS['resolver_throwing_loads'] = ($GLOBALS['resolver_throwing_loads'] ?? 0) + 1;

throw new \RuntimeException('controlled resolver load failure');

class ThrowingTarget {}
