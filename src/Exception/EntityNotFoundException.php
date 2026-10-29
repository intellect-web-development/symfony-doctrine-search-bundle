<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Exception;

final class EntityNotFoundException extends SymfonyDoctrineSearchException
{
    /**
     * @param class-string $entityClass
     */
    public static function create(string $entityClass, string $idProperty, string $id): self
    {
        $shortName = substr($entityClass, (int) strrpos('\\' . $entityClass, '\\'));

        return new self(sprintf("%s with %s '%s' does not exist", $shortName, $idProperty, $id));
    }
}
