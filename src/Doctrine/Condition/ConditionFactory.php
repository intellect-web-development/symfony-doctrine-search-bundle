<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Condition;

use IWD\SymfonyDoctrineSearch\Doctrine\Value\ValueKind;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\FilterOperator;

/**
 * DQL-условие для одного фильтра.
 *
 * Значение, которое столбец не может принять (`abc` для числа или uuid), не уходит в базу:
 * условие становится тождественно ложным, а для операторов с отрицанием — тождественно истинным.
 *
 * @internal
 */
final readonly class ConditionFactory
{
    private const string ALWAYS = '1 = 1';
    private const string NEVER = '1 = 0';

    /**
     * @param bool $isConstraint пустой список в ограничении `In` значит «ничего», а в пользовательском фильтре — «фильтр не задан»
     *
     * @return string|null null — фильтр не задаёт условия (пустая строка в `like`, пустой список)
     */
    public function create(Filter $filter, string $field, ValueKind $kind, QueryScope $scope, bool $isConstraint): ?string
    {
        return match ($filter->operator) {
            FilterOperator::IsNull => $field . ' IS NULL',
            FilterOperator::NotNull => $field . ' IS NOT NULL',
            FilterOperator::Like, FilterOperator::NotLike => $this->like($filter, $field, $kind, $scope),
            FilterOperator::In, FilterOperator::NotIn => $this->in($filter, $field, $kind, $scope, $isConstraint),
            FilterOperator::Range => $this->range($filter, $field, $kind, $scope),
            default => $this->compare($filter, $field, $kind, $scope),
        };
    }

    private function compare(Filter $filter, string $field, ValueKind $kind, QueryScope $scope): string
    {
        /** @var string|int|float|bool $value */
        $value = $filter->value;
        if (!$kind->accepts($value)) {
            return $this->unmatchable($filter);
        }

        $operator = match ($filter->operator) {
            FilterOperator::NotEquals => '<>',
            FilterOperator::LessThan => '<',
            FilterOperator::LessOrEquals => '<=',
            FilterOperator::GreaterThan => '>',
            FilterOperator::GreaterOrEquals => '>=',
            default => '=',
        };

        return sprintf('%s %s %s', $field, $operator, $scope->bind($kind->prepare($value)));
    }

    private function like(Filter $filter, string $field, ValueKind $kind, QueryScope $scope): ?string
    {
        /** @var string|int|float|bool $value */
        $value = $filter->value;
        $needle = mb_strtolower((string) $value);
        if ('' === $needle) {
            return null;
        }
        if (!$kind->supportsLike()) {
            return $this->unmatchable($filter);
        }

        // Метасимволы LIKE и сам escape-символ экранируются: пользовательский ввод ищется буквально.
        $pattern = '%' . strtr($needle, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $operator = FilterOperator::NotLike === $filter->operator ? 'NOT LIKE' : 'LIKE';

        return sprintf("LOWER(%s) %s %s ESCAPE '!'", $field, $operator, $scope->bind($pattern));
    }

    private function in(Filter $filter, string $field, ValueKind $kind, QueryScope $scope, bool $isConstraint): ?string
    {
        /** @var list<string|int|float|bool> $values */
        $values = $filter->value;
        if ([] === $values) {
            return $isConstraint && FilterOperator::In === $filter->operator ? self::NEVER : null;
        }

        $accepted = array_values(array_map(
            $kind->prepare(...),
            array_filter($values, $kind->accepts(...)),
        ));
        if ([] === $accepted) {
            return $this->unmatchable($filter);
        }
        $operator = FilterOperator::NotIn === $filter->operator ? 'NOT IN' : 'IN';

        return sprintf('%s %s (%s)', $field, $operator, $scope->bind($accepted));
    }

    private function range(Filter $filter, string $field, ValueKind $kind, QueryScope $scope): string
    {
        /** @var array{0: string|int|float|bool|null, 1: string|int|float|bool|null} $bounds */
        $bounds = $filter->value;
        [$from, $to] = $bounds;
        foreach ([$from, $to] as $bound) {
            if (null !== $bound && !$kind->accepts($bound)) {
                return self::NEVER;
            }
        }

        return match (true) {
            null !== $from && null !== $to => sprintf('%s BETWEEN %s AND %s', $field, $scope->bind($kind->prepare($from)), $scope->bind($kind->prepare($to))),
            null !== $from => sprintf('%s >= %s', $field, $scope->bind($kind->prepare($from))),
            default => sprintf('%s <= %s', $field, $scope->bind($kind->prepare((string) $to))),
        };
    }

    private function unmatchable(Filter $filter): string
    {
        return $filter->operator->isNegation() ? self::ALWAYS : self::NEVER;
    }
}
