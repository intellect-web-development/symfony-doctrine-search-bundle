<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

enum FilterStrategy: string
{
    case And = 'and';
    case Or = 'or';
}
