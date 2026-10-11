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
        $sortRaw = $request->query->get('sort', '');
        if (!is_string($sortRaw) || '' === trim($sortRaw)) {
            return new Sorts();
        }

        $sortRaw = trim($sortRaw);
        if (str_starts_with($sortRaw, '{')) {
            return self::fromCompactJson($sortRaw);
        }

        $sortParams = explode(
            ',',
            str_replace(' ', '', $sortRaw)
        );

        $sorts = [];
        foreach ($sortParams as $sortParam) {
            if ('' === $sortParam) {
                continue;
            }
            $field = trim($sortParam, '-');
            if ('' === $field) {
                continue;
            }

            $direction = str_starts_with($sortParam, '-') ? 'DESC' : 'ASC';
            $sorts[] = new Sort($field, $direction);
        }

        return new Sorts($sorts);
    }

    private static function fromCompactJson(string $sortRaw): Sorts
    {
        $decoded = json_decode($sortRaw, true);
        if (!is_array($decoded)) {
            return new Sorts();
        }

        $sorts = [];
        foreach ($decoded as $field => $direction) {
            if (!is_string($field) || '' === $field || !is_string($direction)) {
                continue;
            }
            $normalized = strtoupper($direction);
            if (!in_array($normalized, ['ASC', 'DESC'], true)) {
                continue;
            }
            $sorts[] = new Sort($field, $normalized);
        }

        return new Sorts($sorts);
    }
}
