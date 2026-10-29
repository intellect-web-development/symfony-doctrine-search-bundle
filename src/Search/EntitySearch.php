<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Search;

use IWD\SymfonyDoctrineSearch\Exception\InvalidSearchQueryException;
use IWD\SymfonyDoctrineSearch\Exception\UnresolvableConstraintException;
use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use IWD\SymfonyDoctrineSearch\Result\SearchResult;

interface EntitySearch
{
    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     * @param bool            $eager       подгрузить связи сразу (глубина — `eager_load.depth` в конфигурации)
     *
     * @return SearchResult<T>
     *
     * @throws InvalidSearchQueryException     неизвестное свойство при `reject_unknown_properties: true`
     * @throws UnresolvableConstraintException ограничение ссылается на несуществующий путь
     */
    public function search(string $entityClass, SearchQuery $query, bool $eager = true): SearchResult;
}
