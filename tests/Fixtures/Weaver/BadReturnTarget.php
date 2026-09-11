<?php

namespace Tests\Fixtures\Weaver;

class BadReturnTarget
{
    #[BadReturnProvider]
    public function run(): string
    {
        return 'never';
    }
}
