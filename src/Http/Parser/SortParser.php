<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Http\Parser;

use IWD\SymfonyDoctrineSearch\Query\Sort;
use IWD\SymfonyDoctrineSearch\Query\SortDirection;

/**
 * `sort=-createdAt,title` (минус — по убыванию) или `sort={"createdAt":"desc","title":"asc"}`.
 *
 * @internal
 */
final readonly class SortParser
{
    /**
     * @return list<Sort>
     */
    public function parse(mixed $raw): array
    {
        if (!is_string($raw) || '' === trim($raw)) {
            return [];
        }
        $raw = trim($raw);

        return str_starts_with($raw, '{') ? $this->fromJson($raw) : $this->fromList($raw);
    }

    /**
     * @return list<Sort>
     */
    private function fromList(string $raw): array
    {
        $sorts = [];
        foreach (explode(',', str_replace(' ', '', $raw)) as $item) {
            $property = ltrim($item, '-');
            if ('' !== $property) {
                $sorts[] = new Sort($property, str_starts_with($item, '-') ? SortDirection::Desc : SortDirection::Asc);
            }
        }

        return $sorts;
    }

    /**
     * @return list<Sort>
     */
    private function fromJson(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $sorts = [];
        foreach ($decoded as $property => $direction) {
            if (!is_string($property) || '' === $property || !is_string($direction)) {
                continue;
            }
            $direction = SortDirection::tryFrom(strtoupper($direction));
            if (null !== $direction) {
                $sorts[] = new Sort($property, $direction);
            }
        }

        return $sorts;
    }
}
