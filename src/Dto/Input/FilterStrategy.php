<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Dto\Input;

enum FilterStrategy: string
{
    case And = 'and';
    case Or = 'or';
}
