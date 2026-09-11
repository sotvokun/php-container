<?php

declare(strict_types=1);

namespace Tests\Fixtures\Signature;

use Attribute;
use Sotvokun\Container\Aop\InterceptorProvider;
use Sotvokun\Container\Aop\MethodInterceptor;
use Sotvokun\Container\Aop\MethodInvocation;

#[Attribute(Attribute::TARGET_METHOD)]
final class SignatureProvider implements InterceptorProvider
{
    /** @var list<MethodInterceptor> */
    public static array $interceptors = [];

    public static function interceptors(): array
    {
        return self::$interceptors;
    }
}

final class ProceedInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}

final class TraceInterceptor implements MethodInterceptor
{
    /**
     * @param list<string> $events
     */
    public function __construct(
        private array &$events,
        private string $name = 'aspect'
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $this->events[] = $this->name . ':before:' . $invocation->getMethod()->getName();
        try {
            return $invocation->proceed();
        } finally {
            $this->events[] = $this->name . ':after:' . $invocation->getMethod()->getName();
        }
    }
}

class ValueObject
{
    public function __construct(
        public string $value
    ) {}
}

interface Left {}
interface Right {}

class Both implements Left, Right {}
class TypeParent {}

class ParentMethodBase
{
    /** @var list<string> */
    public array $events = [];

    #[SignatureProvider]
    public function inherited(): string
    {
        $this->events[] = 'target:parent';
        return 'parent';
    }
}

enum DefaultMode: string
{
    case Fast = 'fast';
}
