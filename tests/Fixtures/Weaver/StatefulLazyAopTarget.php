<?php

declare(strict_types=1);

namespace Tests\Fixtures\Weaver;

class StatefulLazyAopTarget
{
    public string $value = 'ok';

    public function __construct(Counts $counts)
    {
        $counts->values['constructed'] = ($counts->values['constructed'] ?? 0) + 1;
    }

    #[CountingProvider]
    public function run(): string
    {
        return $this->value;
    }
}
