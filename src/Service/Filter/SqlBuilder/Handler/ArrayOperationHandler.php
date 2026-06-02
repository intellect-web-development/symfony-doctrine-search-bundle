<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperation;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperationHandlerInterface;

class ArrayOperationHandler implements FilterSqlOperationHandlerInterface
{
    public function supports(FilterSqlOperation $operation): bool
    {
        return in_array($operation, [FilterSqlOperation::In, FilterSqlOperation::NotIn], true);
    }

    public function buildCondition(
        FilterSqlBuilder $builder,
        FilterSqlOperation $operation,
        string $field,
        mixed ...$values
    ): ?string {
        $value = $values[0] ?? null;
        if (!is_array($value) || [] === $value) {
            return null;
        }

        $operator = FilterSqlOperation::In === $operation ? 'IN' : 'NOT IN';

        return "{$field} {$operator} (:{$builder->bindValue($value)})";
    }
}
