<?php

declare(strict_types=1);

namespace Tests\Fixtures\AopAlternate;

use Tests\Fixtures\Aop\Record;

class AlternateAopTarget
{
    #[Record]
    public function run(): string
    {
        return 'alternate';
    }
}
