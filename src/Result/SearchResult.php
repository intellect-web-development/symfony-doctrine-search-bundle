<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Result;

/**
 * @template T of object
 */
final readonly class SearchResult
{
    /**
     * @param list<T> $entities
     */
    public function __construct(
        public array $entities,
        public OutputPagination $pagination,
    ) {
    }
}
