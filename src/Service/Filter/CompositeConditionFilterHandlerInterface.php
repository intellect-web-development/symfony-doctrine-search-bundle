<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;

interface CompositeConditionFilterHandlerInterface extends FilterHandlerInterface
{
    public function buildCondition(
        Filter $filter,
        FilterSqlBuilder $appSqlBuilder,
        string $fieldPrefix,
        bool $isRelation
    ): ?string;
}
