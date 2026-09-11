<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

final class EventRecorder
{
    /** @var list<string> */
    public array $events = [];

    public function record(string $event): void
    {
        $this->events[] = $event;
    }
}
