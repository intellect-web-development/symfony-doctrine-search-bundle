<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Http;

use IWD\SymfonyDoctrineSearch\Http\Parser\FilterParser;
use IWD\SymfonyDoctrineSearch\Http\Parser\PaginationParser;
use IWD\SymfonyDoctrineSearch\Http\Parser\SortParser;
use IWD\SymfonyDoctrineSearch\Query\Logic;
use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use Symfony\Component\HttpFoundation\Request;

/**
 * Формат параметров:
 * - `filter[свойство][оператор]=значение` и/или `filters={"свойство":{"оператор":значение}}` (JSON важнее для того же свойства);
 * - `sort=-createdAt,title` или `sort={"createdAt":"desc"}`;
 * - `page[number]=2&page[size]=20` или `page={"number":2,"size":20}`;
 * - `strategy=and|or` — как объединять фильтры.
 */
final readonly class RequestSearchQueryFactory implements SearchQueryFactory
{
    public function __construct(
        private QueryParameterNames $names,
        private FilterParser $filters,
        private SortParser $sorts,
        private PaginationParser $pagination,
    ) {
    }

    public function fromRequest(Request $request): SearchQuery
    {
        $query = $request->query->all();
        $logic = $query[$this->names->logic] ?? null;

        return new SearchQuery(
            filters: $this->filters->parse($query[$this->names->filter] ?? null, $query[$this->names->compactFilter] ?? null),
            logic: is_string($logic) ? Logic::tryFrom(mb_strtolower(trim($logic))) ?? Logic::And : Logic::And,
            sorts: $this->sorts->parse($query[$this->names->sort] ?? null),
            pagination: $this->pagination->parse($query[$this->names->page] ?? null),
        );
    }
}
