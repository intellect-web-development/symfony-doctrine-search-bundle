<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;
use IWD\SymfonyDoctrineSearch\Dto\Input\FilterStrategy;
use IWD\SymfonyDoctrineSearch\Dto\Input\Filters;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class FiltersApplicator
{
    /**
     * @param iterable<FilterHandlerInterface> $handlers
     */
    public function __construct(
        #[TaggedIterator('iwd.symfony_doctrine_search.filter_handler')]
        private iterable $handlers,
    ) {
    }

    public function applyMany(
        Filters $filters,
        FilterSqlBuilder $appSqlBuilder,
        string $fieldPrefix,
        bool $isRelation,
        ?FilterStrategy $compositeStrategy = null,
        FilterStrategy $strategy = FilterStrategy::And
    ): void {
        if (null !== $compositeStrategy) {
            $this->applyComposite($filters, $appSqlBuilder, $fieldPrefix, $isRelation, $strategy, $compositeStrategy);

            return;
        }

        foreach ($filters->toArray() as $filter) {
            $this->apply($filter, $appSqlBuilder, $fieldPrefix, $isRelation);
        }
    }

    public function apply(
        Filter $filter,
        FilterSqlBuilder $appSqlBuilder,
        string $fieldPrefix,
        bool $isRelation
    ): void {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($filter)) {
                $handler->apply($filter, $appSqlBuilder, $fieldPrefix, $isRelation);

                return;
            }
        }
    }

    private function applyComposite(
        Filters $filters,
        FilterSqlBuilder $appSqlBuilder,
        string $fieldPrefix,
        bool $isRelation,
        FilterStrategy $strategy,
        FilterStrategy $compositeStrategy
    ): void {
        $conditions = [];
        foreach ($filters->toArray() as $filter) {
            foreach ($this->handlers as $handler) {
                if (!$handler instanceof CompositeConditionFilterHandlerInterface) {
                    continue;
                }
                if (!$handler->supports($filter)) {
                    continue;
                }
                $condition = $handler->buildCondition($filter, $appSqlBuilder, $fieldPrefix, $isRelation);
                if (null !== $condition) {
                    $conditions[] = $condition;
                }
                break;
            }
        }

        if (empty($conditions)) {
            return;
        }

        $condition = implode(' ' . $compositeStrategy->value . ' ', $conditions);
        $appSqlBuilder->applyCondition("($condition)", $strategy);
    }
}
