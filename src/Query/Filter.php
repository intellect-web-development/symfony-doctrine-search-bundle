<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Query;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * Условие на свойство сущности. Свойство — путь через связи: `isin`, `tradingDesk.status`, `a.b.c.id`.
 *
 * Форма значения зависит от оператора:
 * - `In` / `NotIn` — список скаляров;
 * - `Range` — пара `[от, до]`, любая граница может быть `null`, но не обе;
 * - `IsNull` / `NotNull` — значение не используется;
 * - остальные — скаляр.
 *
 * Enum, дата и Stringable приводятся к скаляру сразу, поэтому в ограничение можно передавать VO и enum как есть.
 */
final readonly class Filter
{
    /** @var string|int|float|bool|list<string|int|float|bool>|array{0: string|int|float|bool|null, 1: string|int|float|bool|null}|null */
    public mixed $value;

    public function __construct(
        public string $property,
        public FilterOperator $operator,
        mixed $value = null,
    ) {
        if ('' === trim($property)) {
            throw new InvalidArgumentException('Filter property must not be empty.');
        }

        $this->value = match (true) {
            !$operator->expectsValue() => null,
            $operator->expectsList() => self::normalizeList($value, $operator),
            FilterOperator::Range === $operator => self::normalizeRange($value),
            default => self::normalizeScalar($value, $operator),
        };
    }

    /**
     * @return list<string|int|float|bool>
     */
    private static function normalizeList(mixed $value, FilterOperator $operator): array
    {
        $values = is_array($value) ? $value : [$value];
        if (!array_is_list($values)) {
            throw new InvalidArgumentException(sprintf('Operator "%s" expects a list of values.', $operator->value));
        }

        return array_map(static fn (mixed $item): string|int|float|bool => self::normalizeScalar($item, $operator), $values);
    }

    /**
     * @return array{0: string|int|float|bool|null, 1: string|int|float|bool|null}
     */
    private static function normalizeRange(mixed $value): array
    {
        if (!is_array($value) || [0, 1] !== array_keys($value)) {
            throw new InvalidArgumentException('Operator "range" expects a pair [from, to].');
        }
        if (null === $value[0] && null === $value[1]) {
            throw new InvalidArgumentException('Operator "range" needs at least one bound.');
        }

        return [
            null === $value[0] ? null : self::normalizeScalar($value[0], FilterOperator::Range),
            null === $value[1] ? null : self::normalizeScalar($value[1], FilterOperator::Range),
        ];
    }

    private static function normalizeScalar(mixed $value, FilterOperator $operator): string|int|float|bool
    {
        return match (true) {
            is_scalar($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof Stringable => (string) $value,
            default => throw new InvalidArgumentException(sprintf('Operator "%s" expects a scalar value, %s given.', $operator->value, get_debug_type($value))),
        };
    }
}
