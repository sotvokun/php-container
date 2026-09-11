<?php

declare(strict_types=1);

namespace Tests\Fixtures\Aop;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ClassCallbackMarker {}
