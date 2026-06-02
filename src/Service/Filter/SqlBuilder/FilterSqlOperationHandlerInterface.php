<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('iwd.symfony_doctrine_search.sql_operation_handler')]
interface FilterSqlOperationHandlerInterface
{
    public function supports(FilterSqlOperation $operation): bool;

    public function buildCondition(
        FilterSqlBuilder $builder,
        FilterSqlOperation $operation,
        string $field,
        mixed ...$values
    ): ?string;
}
