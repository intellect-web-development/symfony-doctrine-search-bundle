<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Path;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Свойство, проверенное по маппингу: цепочка связей от корня и поле сущности, в которой путь заканчивается.
 */
final readonly class ResolvedProperty
{
    /**
     * @param list<string>          $associations
     * @param ClassMetadata<object> $owner
     */
    public function __construct(
        public array $associations,
        public string $field,
        public ClassMetadata $owner,
    ) {
    }

    public function needsJoin(): bool
    {
        return [] !== $this->associations;
    }
}
