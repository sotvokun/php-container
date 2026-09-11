<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class VariadicLabelsTarget
{
    /** @var list<string> */
    public array $labels;

    public function __construct(string ...$labels)
    {
        $this->labels = $labels;
    }

    #[Record]
    public function run(): string
    {
        return implode(',', $this->labels);
    }
}
