<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperation;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperationHandlerInterface;

class BetweenOperationHandler implements FilterSqlOperationHandlerInterface
{
    public function supports(FilterSqlOperation $operation): bool
    {
        return FilterSqlOperation::Between === $operation;
    }

    public function buildCondition(
        FilterSqlBuilder $builder,
        FilterSqlOperation $operation,
        string $field,
        mixed ...$values
    ): ?string {
        $gte = $values[0] ?? null;
        $lte = $values[1] ?? null;

        if (null === $gte || null === $lte) {
            return null;
        }

        $gteBind = $builder->bindValue($gte);
        $lteBind = $builder->bindValue($lte);

        return "{$field} BETWEEN :{$gteBind} AND :{$lteBind}";
    }
}
