<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Tests\Fixtures\Aop\CycleSelf;

require dirname(__DIR__, 2) . '/bootstrap.php';

(new Container())->make(CycleSelf::class);
