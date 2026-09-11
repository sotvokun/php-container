<?php

declare(strict_types=1);

namespace Tests\Fixtures\Generated;

class GeneratedTarget
{
    public int $calls = 0;

    #[GeneratedProvider]
    public function run(string $value = 'ok'): string
    {
        ++$this->calls;

        return $value;
    }
}
