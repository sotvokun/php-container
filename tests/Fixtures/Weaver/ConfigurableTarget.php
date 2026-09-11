<?php

namespace Tests\Fixtures\Weaver;

class ConfigurableTarget
{
    #[ConfigurableProvider]
    public function run(): string
    {
        return 'ok';
    }
}
