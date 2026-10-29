<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;

/**
 * @internal
 */
final class SingleIdentifier
{
    /**
     * @param ClassMetadata<object> $metadata
     */
    public static function fieldOf(ClassMetadata $metadata): string
    {
        if ($metadata->isIdentifierComposite) {
            throw new LogicException(sprintf('%s has a composite identifier, which doctrine-search does not support.', $metadata->getName()));
        }

        return $metadata->getSingleIdentifierFieldName();
    }
}
