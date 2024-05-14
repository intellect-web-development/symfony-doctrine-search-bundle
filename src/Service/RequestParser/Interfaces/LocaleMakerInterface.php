<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use IWD\SymfonyDoctrineSearch\Dto\Input\Locale;

interface LocaleMakerInterface
{
    public static function make(Request $request): Locale;
}
