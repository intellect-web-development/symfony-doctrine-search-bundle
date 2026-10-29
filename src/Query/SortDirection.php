<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Query;

enum SortDirection: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
