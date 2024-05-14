<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations;

use Symfony\Component\HttpFoundation\Request;
use IWD\SymfonyDoctrineSearch\Dto\Input\Sort;
use IWD\SymfonyDoctrineSearch\Dto\Input\Sorts;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\SortsMakerInterface;

class SortsMaker implements SortsMakerInterface
{
    public static function make(Request $request): Sorts
    {
        /** @var string $sortRaw */
        $sortRaw = $request->query->get('sort', '');
        if (empty($sortRaw)) {
            return new Sorts();
        }

        $sortParams = explode(
            ',',
            str_replace(' ', '', $sortRaw)
        );

        $sorts = [];
        foreach ($sortParams as $sortParam) {
            $field = trim($sortParam, '-');

            $direction = '-' === $sortParam[0] ? 'DESC' : 'ASC';
            $sorts[] = new Sort($field, $direction);
        }

        return new Sorts($sorts);
    }
}
