<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Query;

use InvalidArgumentException;

final readonly class Pagination
{
    public function __construct(
        public int $number,
        public int $size,
    ) {
        if ($number < 1) {
            throw new InvalidArgumentException(sprintf('Page number must be positive, %d given.', $number));
        }
        if ($size < 1) {
            throw new InvalidArgumentException(sprintf('Page size must be positive, %d given.', $size));
        }
    }

    public function offset(): int
    {
        return ($this->number - 1) * $this->size;
    }
}
