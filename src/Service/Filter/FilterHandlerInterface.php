<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('iwd.symfony_doctrine_search.filter_handler')]
interface FilterHandlerInterface
{
    public function supports(Filter $filter): bool;

    public function apply(
        Filter $filter,
        FilterSqlBuilder $appSqlBuilder,
        string $fieldPrefix,
        bool $isRelation
    ): void;
}
