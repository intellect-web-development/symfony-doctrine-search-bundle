<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Exception;

use DomainException;
use Throwable;

class SymfonyDoctrineSearchException extends DomainException
{
    public function __construct(
        string $message = '',
        ?int $code = 500,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, (int) $code, $previous);
    }
}
