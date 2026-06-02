<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperation;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperationHandlerInterface;

class LikeOperationHandler implements FilterSqlOperationHandlerInterface
{
    public function supports(FilterSqlOperation $operation): bool
    {
        return in_array($operation, [FilterSqlOperation::Like, FilterSqlOperation::NotLike], true);
    }

    public function buildCondition(
        FilterSqlBuilder $builder,
        FilterSqlOperation $operation,
        string $field,
        mixed ...$values
    ): ?string {
        $value = $values[0] ?? null;
        if (empty($value)) {
            return null;
        }

        $operator = FilterSqlOperation::Like === $operation ? 'LIKE' : 'NOT LIKE';
        $prepared = mb_strtolower((string) $value);

        return "LOWER({$field}) {$operator} :{$builder->bindValue("%{$prepared}%")}";
    }
}
