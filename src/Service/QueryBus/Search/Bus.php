<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\QueryBus\Search;

use Doctrine\ORM\EntityManagerInterface;
use IWD\SymfonyDoctrineSearch\Dto\Input\FilterStrategy;
use IWD\SymfonyDoctrineSearch\Dto\Output\OutputPagination;
use IWD\SymfonyDoctrineSearch\Dto\Output\SearchResult;
use IWD\SymfonyDoctrineSearch\Service\Filter\Fetcher;

readonly class Bus
{
    public function __construct(
        private Fetcher $fetcher,
        private EntityManagerInterface $em,
    ) {
    }

    public function query(Query $actionContext): SearchResult
    {
        if (FilterStrategy::Or === $actionContext->filterStrategy) {
            $classMetadata = $this->em->getClassMetadata($actionContext->targetEntityClass);
            foreach ($actionContext->filters->toArray() as $filter) {
                if (!isset($classMetadata->fieldMappings[$filter->property])) {
                    $actionContext->filters->block($filter);
                    continue;
                }
                $propertyMeta = $classMetadata->fieldMappings[$filter->property];
                /**
                 * В некоторых базах данных возникают проблемы, если по полям где хранятся ID начинать искать текстовые данные,
                 * по этому в этом случае такой фильтр не будет применен.
                 */
                if (isset($propertyMeta['id']) && true === $propertyMeta['id'] && (string) $filter->value !== (string) (int) $filter->value) {
                    // todo: это условие сработает только на int-id, на uuid-id не сработает, нужно добавлять доп. проверку,
                    //  а еще лучше внедрить что-то типо "AutoDisableFilterRule", чтобы при определенных ситуациях фильтр отключался.
                    //  И это можно было конфигурировать на стороне приложения, а не бандла, по паттерну стратегии + цепочка обязанностей (isSupport)
                    $actionContext->filters->block($filter);
                    continue;
                }
            }
        }

        $count = $this->fetcher->count(
            $this->fetcher->createContext($actionContext->targetEntityClass)
                ->addFilters($actionContext->filters),
        );

        // todo: можно попробовать решить задачу в 2 запроса:
        // 1 - получить count
        // 2 - сразу получить сущности без получения промужуточного "ids" путем подстановки в итоговый запрос
        // with target_entities as (select {запрос на получение ids}) ... select ... in(select ... where in (target_entities))
        $ids = $this->fetcher->searchEntityIds(
            $this->fetcher->createContext($actionContext->targetEntityClass)
                ->addFilters($actionContext->filters)
                ->addSorts($actionContext->sorts)
                ->paginate($actionContext->pagination)
        );

        $entities = $this->fetcher->getByIds(
            context: ($this->fetcher->createContext($actionContext->targetEntityClass))->addSorts($actionContext->sorts),
            ids: $ids,
            eager: $actionContext->eager,
            hints: $actionContext->hints
        );

        $paginationDto = new OutputPagination(
            count: $count,
            totalPages: (int) ceil($count / $actionContext->pagination->getPageSize()),
            page: $actionContext->pagination->getPageNumber(),
            size: count($entities)
        );

        return new SearchResult(entities: $entities, pagination: $paginationDto);
    }

    public function getRelationPlan(Query $actionContext): array
    {
        return $this->fetcher->createAssocRelationMap(
            context: ($this->fetcher->createContext($actionContext->targetEntityClass))->addFilters($actionContext->filters),
            hints: $actionContext->hints
        );
    }
}
