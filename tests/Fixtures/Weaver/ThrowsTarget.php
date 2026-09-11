<?php

namespace Tests\Fixtures\Weaver;

class ThrowsTarget
{
    #[ThrowsProvider]
    public function run(): string
    {
        return 'never';
    }
}
