<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Http;

use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use Symfony\Component\HttpFoundation\Request;

/**
 * Собирает SearchQuery из HTTP-запроса. Свой формат параметров — своя реализация под этим интерфейсом.
 */
interface SearchQueryFactory
{
    public function fromRequest(Request $request): SearchQuery;
}
