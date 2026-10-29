<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Http;

use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Аргумент контроллера `SearchQuery $searchQuery` собирается из GET-параметров запроса.
 */
final readonly class SearchQueryValueResolver implements ValueResolverInterface
{
    public function __construct(
        private SearchQueryFactory $factory,
    ) {
    }

    /**
     * @return iterable<SearchQuery>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (SearchQuery::class !== $argument->getType()) {
            return [];
        }

        return [$this->factory->fromRequest($request)];
    }
}
