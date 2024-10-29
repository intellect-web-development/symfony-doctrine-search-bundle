<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\QueryBus\Search;

use IWD\SymfonyDoctrineSearch\Dto\Output\OutputPagination;
use IWD\SymfonyDoctrineSearch\Dto\Output\SearchResult;
use IWD\SymfonyDoctrineSearch\Service\Filter\Fetcher;

class Bus
{
    public function __construct(
        private Fetcher $fetcher
    ) {
    }

    public function query(Query $actionContext): SearchResult
    {
        $count = $this->fetcher->count(
            $this->fetcher->createContext($actionContext->targetEntityClass)
                ->addFilters($actionContext->filters),
        );

        // todo: можно попробовать решить задачу в 2 запроса:
        // 1 - получить count
        // 2 - сразу получить сущности без получения промужуточного "ids" путем подставления в итоговый запрос
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
