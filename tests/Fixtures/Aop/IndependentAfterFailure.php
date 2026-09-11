<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class IndependentAfterFailure
{
    #[Record]
    public function run(): string
    {
        return 'ok';
    }
}
