<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Dto\Input;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterMode;

class Filter
{
    public function __construct(
        public string $property,
        public FilterMode $mode,
        public mixed $value = null,
        public FilterStrategy $strategy = FilterStrategy::And,
    ) {
    }
}
