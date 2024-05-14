<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Attribute;

use Attribute;
use IWD\SymfonyDoctrineSearch\Interfaces\InputContractInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class CliContract
{
    public function __construct(
        /** @var class-string<InputContractInterface> */
        public string $class
    ) {
    }
}
