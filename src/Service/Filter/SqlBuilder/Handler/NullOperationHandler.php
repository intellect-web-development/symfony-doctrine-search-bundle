<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperation;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperationHandlerInterface;

class NullOperationHandler implements FilterSqlOperationHandlerInterface
{
    public function supports(FilterSqlOperation $operation): bool
    {
        return in_array($operation, [FilterSqlOperation::IsNull, FilterSqlOperation::NotNull], true);
    }

    public function buildCondition(
        FilterSqlBuilder $builder,
        FilterSqlOperation $operation,
        string $field,
        mixed ...$values
    ): ?string {
        $suffix = FilterSqlOperation::IsNull === $operation ? 'IS NULL' : 'IS NOT NULL';

        return "{$field} {$suffix}";
    }
}
