<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Http\Parser;

use IWD\SymfonyDoctrineSearch\Query\Pagination;

/**
 * `page[number]=2&page[size]=20` или `page={"number":2,"size":20}`. Некорректный номер — первая страница,
 * некорректный размер — размер по умолчанию, слишком большой — урезается до максимума.
 *
 * @internal
 */
final readonly class PaginationParser
{
    public function __construct(
        private int $defaultSize,
        private int $maxSize,
    ) {
    }

    public function parse(mixed $raw): ?Pagination
    {
        if (is_string($raw)) {
            $raw = json_decode(trim($raw), true);
        }
        if (!is_array($raw)) {
            return null;
        }

        $number = $this->integer($raw['number'] ?? null) ?? 1;
        $size = $this->integer($raw['size'] ?? null) ?? $this->defaultSize;

        return new Pagination(
            max(1, $number),
            $size < 1 ? $this->defaultSize : min($size, $this->maxSize),
        );
    }

    private function integer(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : null;
    }
}
