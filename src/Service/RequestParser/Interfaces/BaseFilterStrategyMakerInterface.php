<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterStrategy;
use Symfony\Component\HttpFoundation\Request;

interface BaseFilterStrategyMakerInterface
{
    public static function make(Request $request): FilterStrategy;
}
