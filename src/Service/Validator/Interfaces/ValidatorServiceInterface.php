<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Validator\Interfaces;

use IWD\SymfonyDoctrineSearch\Exception\ValidatorException;

interface ValidatorServiceInterface
{
    /**
     * @throws ValidatorException
     */
    public function validate(object $object): void;
}
