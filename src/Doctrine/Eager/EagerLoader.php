<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Eager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

/**
 * Подгружает связи fetch-join-ами, чтобы не было N+1 при сериализации результата.
 * Первый уровень — все связи корня; глубже — только в классы, которые ещё не подгружены (защита от циклов).
 *
 * @internal
 */
final readonly class EagerLoader
{
    public function __construct(
        private int $depth,
    ) {
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    public function apply(EntityManagerInterface $em, QueryBuilder $query, string $rootAlias, ClassMetadata $metadata): void
    {
        $visited = [$metadata->getName() => true];
        $level = [[$metadata, $rootAlias]];
        $joined = 0;

        for ($depth = 1; $depth <= $this->depth && [] !== $level; ++$depth) {
            $next = [];
            foreach ($level as [$owner, $ownerAlias]) {
                foreach ($owner->getAssociationNames() as $association) {
                    $target = $owner->getAssociationTargetClass($association);
                    if ($depth > 1 && isset($visited[$target])) {
                        continue;
                    }

                    $alias = $rootAlias . '_e' . ++$joined;
                    $query->leftJoin($ownerAlias . '.' . $association, $alias)->addSelect($alias);
                    $next[] = [$em->getClassMetadata($target), $alias];
                }
            }
            foreach ($next as [$joinedMetadata]) {
                $visited[$joinedMetadata->getName()] = true;
            }
            $level = $next;
        }
    }
}
