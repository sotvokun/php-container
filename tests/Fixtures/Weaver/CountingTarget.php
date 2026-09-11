<?php

namespace Tests\Fixtures\Weaver;

class CountingTarget
{
    #[CountingProvider]
    public function run(): string
    {
        return 'ok';
    }
}
