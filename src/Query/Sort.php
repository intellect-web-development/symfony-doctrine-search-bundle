<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Query;

final readonly class Sort
{
    public function __construct(
        public string $property,
        public SortDirection $direction = SortDirection::Asc,
    ) {
    }
}
