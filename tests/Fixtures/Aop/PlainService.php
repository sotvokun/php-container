<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

final class PlainService
{
    public function value(): string
    {
        return 'plain';
    }
}
