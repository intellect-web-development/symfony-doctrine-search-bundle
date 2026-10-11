<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations;

use Symfony\Component\HttpFoundation\Request;
use IWD\SymfonyDoctrineSearch\Dto\Input\Pagination;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\PaginationMakerInterface;

class PaginationMaker implements PaginationMakerInterface
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_SIZE = 20;

    public static function make(Request $request): Pagination
    {
        $all = $request->query->all();
        $paginationRaw = $all['page'] ?? null;

        if (is_string($paginationRaw)) {
            $decoded = json_decode(trim($paginationRaw), true);
            $paginationRaw = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($paginationRaw)) {
            return self::createDefaultPagination();
        }

        return new Pagination(
            (int) ($paginationRaw['number'] ?? self::DEFAULT_PAGE),
            (int) ($paginationRaw['size'] ?? self::DEFAULT_SIZE)
        );
    }

    protected static function createDefaultPagination(): Pagination
    {
        return new Pagination(self::DEFAULT_PAGE, self::DEFAULT_SIZE);
    }
}
