<?php

namespace Tests\Fixtures\Weaver;

class EmptyTarget
{
    public int $calls = 0;

    #[EmptyProvider]
    public function run(): string
    {
        ++$this->calls;
        return 'unchanged';
    }
}
