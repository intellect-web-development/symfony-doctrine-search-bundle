<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperation;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperationHandlerInterface;

class EqualityOperationHandler implements FilterSqlOperationHandlerInterface
{
    public function supports(FilterSqlOperation $operation): bool
    {
        return in_array($operation, [FilterSqlOperation::Equals, FilterSqlOperation::NotEquals], true);
    }

    public function buildCondition(
        FilterSqlBuilder $builder,
        FilterSqlOperation $operation,
        string $field,
        mixed ...$values
    ): ?string {
        $value = $values[0] ?? null;
        if (null === $value) {
            return null;
        }

        $normalized = $builder->normalizeScalar($value);
        $operator = FilterSqlOperation::Equals === $operation ? '=' : '!=';

        return "{$field} {$operator} :{$builder->bindValue($normalized)}";
    }
}
