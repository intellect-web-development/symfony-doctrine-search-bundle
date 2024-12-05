<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\ArgumentResolver;

use Generator;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\BaseFilterStrategyMakerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use IWD\SymfonyDoctrineSearch\Dto\Input\SearchQuery;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\FiltersMakerInterface;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\PaginationMakerInterface;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\SortsMakerInterface;

readonly class SearchQueryResolver implements ValueResolverInterface
{
    public function __construct(
        private FiltersMakerInterface $filtersMaker,
        private PaginationMakerInterface $paginationMaker,
        private SortsMakerInterface $sortsMaker,
        private BaseFilterStrategyMakerInterface $baseFilterStrategyMaker,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): Generator
    {
        if (!$this->supports($request, $argument)) {
            return [];
        }

        $searchQuery = new SearchQuery(
            pagination: $this->paginationMaker::make($request),
            filters: $this->filtersMaker::make($request),
            sorts: $this->sortsMaker::make($request),
            baseFilterStrategy: $this->baseFilterStrategyMaker::make($request),
        );

        // todo: стратегия должна быть глобальной в рамках запроса, это убрать
        foreach ($searchQuery->filters->toArray() as $filter) {
            $filter->strategy = $searchQuery->baseFilterStrategy;
        }

        yield $searchQuery;
    }

    private function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return SearchQuery::class === $argument->getType();
    }
}
