<?php

namespace Tests\Fixtures\Weaver;

class ProviderTarget
{
    #[ClassProvider]
    public function byClass(): string
    {
        return 'class';
    }

    #[InstanceProvider]
    public function byInstance(): string
    {
        return 'instance';
    }

    #[MixedProvider]
    public function mixed(): string
    {
        return 'mixed';
    }
}
