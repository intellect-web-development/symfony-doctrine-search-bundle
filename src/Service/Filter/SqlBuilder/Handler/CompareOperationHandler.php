<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperation;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperationHandlerInterface;

class CompareOperationHandler implements FilterSqlOperationHandlerInterface
{
    public function supports(FilterSqlOperation $operation): bool
    {
        return in_array($operation, [
            FilterSqlOperation::LessThan,
            FilterSqlOperation::GreaterThan,
            FilterSqlOperation::LessOrEquals,
            FilterSqlOperation::GreaterOrEquals,
        ], true);
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

        $operator = match ($operation) {
            FilterSqlOperation::LessThan => '<',
            FilterSqlOperation::GreaterThan => '>',
            FilterSqlOperation::LessOrEquals => '<=',
            FilterSqlOperation::GreaterOrEquals => '>=',
            default => throw new \LogicException('Unsupported compare operation.'),
        };

        return "{$field} {$operator} :{$builder->bindValue($builder->normalizeScalar($value))}";
    }
}
