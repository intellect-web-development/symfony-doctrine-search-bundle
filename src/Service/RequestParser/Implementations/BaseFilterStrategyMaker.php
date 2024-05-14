<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations;

use IWD\SymfonyDoctrineSearch\Service\Filter\FilterStrategy;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\BaseFilterStrategyMakerInterface;
use Symfony\Component\HttpFoundation\Request;

class BaseFilterStrategyMaker implements BaseFilterStrategyMakerInterface
{
    public static function make(Request $request): FilterStrategy
    {
        $strategy = $request->query->get('strategy', FilterStrategy::And->value);

        return FilterStrategy::tryFrom(mb_strtolower((string) $strategy)) ?? FilterStrategy::And;
    }
}
