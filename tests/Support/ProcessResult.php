<?php

declare(strict_types=1);

namespace Tests\Support;

final readonly class ProcessResult
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public bool $timedOut,
    ) {}
}
