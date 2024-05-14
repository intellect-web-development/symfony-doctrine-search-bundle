<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use IWD\SymfonyDoctrineSearch\Dto\Input\Pagination;

interface PaginationMakerInterface
{
    public static function make(Request $request): Pagination;
}
