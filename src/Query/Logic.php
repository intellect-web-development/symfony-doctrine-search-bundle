<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Query;

/**
 * Как объединяются пользовательские фильтры между собой. На ограничения (constraints) не влияет.
 */
enum Logic: string
{
    case And = 'and';
    case Or = 'or';
}
