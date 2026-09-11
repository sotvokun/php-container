<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

class TypedTarget extends TypedParent
{
    public function __construct(
        public string|int $union = 'union',
        public TypedLeft&TypedRight $intersection = new TypedBoth(),
        public self|null $selfValue = null,
        public parent|null $parentValue = null,
    ) {}

    #[Record]
    public function describe(): array
    {
        return [$this->union, $this->intersection::class, $this->selfValue, $this->parentValue];
    }
}
