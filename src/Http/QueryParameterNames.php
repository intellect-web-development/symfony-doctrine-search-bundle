<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Http;

/**
 * Имена GET-параметров поискового запроса (настраиваются в `query_parameters`).
 */
final readonly class QueryParameterNames
{
    public function __construct(
        public string $filter = 'filter',
        public string $compactFilter = 'filters',
        public string $sort = 'sort',
        public string $page = 'page',
        public string $logic = 'strategy',
    ) {
    }
}
