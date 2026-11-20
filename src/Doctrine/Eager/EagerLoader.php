<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Eager;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use IWD\SymfonyDoctrineSearch\Doctrine\SingleIdentifier;
use Stringable;

/**
 * Подгружает связи без декартова произведения: в одном SELECT не больше одной to-many.
 * To-one остаются в запросе владельца. Каждая коллекция — отдельный SELECT по id владельцев.
 * Глубже — только в классы, которые ещё не подгружены (защита от циклов).
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
        $joined = 0;
        $this->joinToOne($em, $query, $rootAlias, $metadata, 1, [$metadata->getName() => true], $joined);
    }

    /**
     * Догружает to-many уже выбранных корней. Пустой список ничего не делает.
     *
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     */
    public function loadCollections(EntityManagerInterface $em, ClassMetadata $metadata, array $entities): void
    {
        $this->loadCollectionsOf($em, $metadata, $entities, 1, [$metadata->getName() => true]);
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param array<string, true>   $visited
     */
    private function joinToOne(
        EntityManagerInterface $em,
        QueryBuilder $query,
        string $ownerAlias,
        ClassMetadata $metadata,
        int $level,
        array $visited,
        int &$joined,
    ): void {
        if ($level > $this->depth) {
            return;
        }

        foreach ($metadata->getAssociationNames() as $association) {
            if ($metadata->isCollectionValuedAssociation($association)) {
                continue;
            }

            $target = $metadata->getAssociationTargetClass($association);
            if ($level > 1 && isset($visited[$target])) {
                continue;
            }

            $alias = $ownerAlias . '_e' . ++$joined;
            $query->leftJoin($ownerAlias . '.' . $association, $alias)->addSelect($alias);
            $next = $visited;
            $next[$target] = true;
            $this->joinToOne($em, $query, $alias, $em->getClassMetadata($target), $level + 1, $next, $joined);
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     * @param array<string, true>   $visited
     */
    private function loadCollectionsOf(
        EntityManagerInterface $em,
        ClassMetadata $metadata,
        array $entities,
        int $level,
        array $visited,
    ): void {
        if ($level > $this->depth || [] === $entities) {
            return;
        }

        $entities = $this->unique($metadata, $entities);

        foreach ($metadata->getAssociationNames() as $association) {
            if (!$metadata->isCollectionValuedAssociation($association)) {
                continue;
            }

            $target = $metadata->getAssociationTargetClass($association);
            if ($level > 1 && isset($visited[$target])) {
                continue;
            }

            $targetMetadata = $em->getClassMetadata($target);
            $next = $visited;
            $next[$target] = true;
            $this->fetchCollection($em, $metadata, $entities, $association, $targetMetadata, $level, $next);
            $this->loadCollectionsOf($em, $targetMetadata, $this->items($metadata, $entities, $association), $level + 1, $next);
        }

        if ($level >= $this->depth) {
            return;
        }

        foreach ($metadata->getAssociationNames() as $association) {
            if ($metadata->isCollectionValuedAssociation($association)) {
                continue;
            }

            $target = $metadata->getAssociationTargetClass($association);
            if (isset($visited[$target])) {
                continue;
            }

            $related = [];
            foreach ($entities as $entity) {
                $value = $metadata->getFieldValue($entity, $association);
                if (is_object($value)) {
                    $related[] = $value;
                }
            }

            $next = $visited;
            $next[$target] = true;
            $this->loadCollectionsOf($em, $em->getClassMetadata($target), $related, $level + 1, $next);
        }
    }

    /**
     * @param ClassMetadata<object> $owner
     * @param list<object>          $entities
     * @param ClassMetadata<object> $target
     * @param array<string, true>   $visited
     */
    private function fetchCollection(
        EntityManagerInterface $em,
        ClassMetadata $owner,
        array $entities,
        string $association,
        ClassMetadata $target,
        int $level,
        array $visited,
    ): void {
        $idField = SingleIdentifier::fieldOf($owner);
        $ids = $this->databaseIds($em, $owner, $entities);
        if ([] === $ids) {
            return;
        }

        $query = $em->createQueryBuilder()
            ->select('root', 'item')
            ->from($owner->getName(), 'root')
            ->leftJoin('root.' . $association, 'item')
            ->where(sprintf('root.%s IN (:dsb_ids)', $idField))
            ->setParameter(
                'dsb_ids',
                $ids,
                [] === array_filter($ids, static fn (mixed $id): bool => !is_int($id)) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING,
            );
        $joined = 0;
        $this->joinToOne($em, $query, 'item', $target, $level + 1, $visited, $joined);
        $query->getQuery()->getResult();
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     *
     * @return list<object>
     */
    private function items(ClassMetadata $metadata, array $entities, string $association): array
    {
        $items = [];
        foreach ($entities as $entity) {
            $value = $metadata->getFieldValue($entity, $association);
            if (!is_iterable($value)) {
                continue;
            }
            foreach ($value as $item) {
                if (is_object($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     *
     * @return list<mixed>
     */
    private function databaseIds(EntityManagerInterface $em, ClassMetadata $metadata, array $entities): array
    {
        $idField = SingleIdentifier::fieldOf($metadata);
        $type = Type::getType((string) $metadata->getTypeOfField($idField));
        $platform = $em->getConnection()->getDatabasePlatform();
        $ids = [];
        foreach ($this->unique($metadata, $entities) as $entity) {
            $id = $metadata->getIdentifierValues($entity)[$idField];
            $ids[] = is_object($id) ? $type->convertToDatabaseValue($id, $platform) : $id;
        }

        return $ids;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     *
     * @return list<object>
     */
    private function unique(ClassMetadata $metadata, array $entities): array
    {
        $idField = SingleIdentifier::fieldOf($metadata);
        $unique = [];
        foreach ($entities as $entity) {
            $id = $metadata->getIdentifierValues($entity)[$idField];
            $key = is_scalar($id) || $id instanceof Stringable ? (string) $id : spl_object_id($entity);
            $unique[$key] = $entity;
        }

        return array_values($unique);
    }
}
