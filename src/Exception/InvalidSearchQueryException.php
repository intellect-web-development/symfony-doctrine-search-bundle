<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Exception;

/**
 * Клиент сослался на свойство, по которому нельзя фильтровать или сортировать
 * (только при `reject_unknown_properties: true` в конфигурации бандла).
 */
final class InvalidSearchQueryException extends SymfonyDoctrineSearchException
{
    public static function unknownFilterProperty(string $property): self
    {
        return new self(sprintf('Filtering by "%s" is not supported.', $property));
    }

    public static function unknownSortProperty(string $property): self
    {
        return new self(sprintf('Sorting by "%s" is not supported.', $property));
    }
}
