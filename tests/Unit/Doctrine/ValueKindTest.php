<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit\Doctrine;

use IWD\SymfonyDoctrineSearch\Doctrine\Value\ValueKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueKind::class)]
final class ValueKindTest extends TestCase
{
    #[DataProvider('cases')]
    public function testAccepts(ValueKind $kind, string|int|float|bool $value, bool $expected): void
    {
        self::assertSame($expected, $kind->accepts($value));
    }

    /**
     * @return iterable<string, array{ValueKind, string|int|float|bool, bool}>
     */
    public static function cases(): iterable
    {
        yield 'numeric string' => [ValueKind::Numeric, '1.5', true];
        yield 'text as number' => [ValueKind::Numeric, 'abc', false];
        yield 'bool as number' => [ValueKind::Numeric, true, false];
        yield 'bool word' => [ValueKind::Boolean, 'false', true];
        yield 'bool garbage' => [ValueKind::Boolean, 'maybe', false];
        yield 'uuid' => [ValueKind::Uuid, '019C3600-0000-7000-8000-000000000001', true];
        yield 'not uuid' => [ValueKind::Uuid, '019c3600', false];
        yield 'date' => [ValueKind::Temporal, '2024-01-01 10:00', true];
        yield 'not date' => [ValueKind::Temporal, 'yesterday-ish', false];
        yield 'string takes anything' => [ValueKind::String, 42, true];
    }

    public function testBooleanIsBoundAsBool(): void
    {
        self::assertFalse(ValueKind::Boolean->prepare('0'));
        self::assertSame('7', ValueKind::Numeric->prepare('7'));
    }
}
