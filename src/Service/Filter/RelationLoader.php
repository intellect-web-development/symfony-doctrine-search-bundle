<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

readonly class RelationLoader
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function createAssocRelationMap(FetcherContext $context, array $hints): array
    {
        $maxNestedLevel = $hints['maxNestedLevel'] ?? 1;
        if ($maxNestedLevel < 1) {
            return [];
        }
        $em = $this->entityManager;

        $assocRelations = [
            $context->entityClass => [
                'entity' => $context->entityClass,
                'pathPrefix' => null,
                'paths' => (static function () use ($em, $context) {
                    $meta = $em->getClassMetadata($context->entityClass);
                    $fields = [];
                    foreach ($meta->associationMappings as $mapping) {
                        $fields[] = $mapping['fieldName'];
                    }

                    return $fields;
                })(),
            ],
        ];
        if (1 === $maxNestedLevel) {
            return $assocRelations;
        }

        foreach ($em->getClassMetadata($context->entityClass)->associationMappings as $mapping) {
            $this->fetchNestedAssocRelation(
                assocRelations: $assocRelations,
                metaPath: $mapping['fieldName'],
                targetEntity: $mapping['targetEntity'],
                maxNestedLevel: $maxNestedLevel,
                currentNestedLevel: 1
            );
        }

        foreach ($hints['pathPrefixMap'] ?? [] as $entityClass => $pathPrefix) {
            $assocRelations[$entityClass]['entity'] = $entityClass;
            $assocRelations[$entityClass]['pathPrefix'] = $pathPrefix;
            $assocRelations[$entityClass]['paths'] ??= [];
        }

        return $assocRelations;
    }

    public function addEagerQueryToRelations(FetcherContext $context, array $hints, QueryBuilder $qb): void
    {
        $aggregateAlias = Fetcher::AGGREGATE_ALIAS;

        $assocRelations = $this->createAssocRelationMap($context, $hints);

        $joins = [];
        foreach ($assocRelations as $relation) {
            foreach ($relation['paths'] as $path) {
                $qb->getQuery()->setFetchMode($relation['entity'], $path, ClassMetadata::FETCH_EAGER);

                $propertyPath = implode('.', array_filter([
                    $relation['pathPrefix'],
                    $path,
                ], static function (?string $path) {
                    return null !== $path;
                }));

                $explodePropertyPath = explode('.', $propertyPath);
                for ($level = 1, $levelMax = count($explodePropertyPath); $level <= $levelMax; ++$level) {
                    $relationPath = Helper::makeRelationPath($explodePropertyPath, $level);
                    $absolutePath = Helper::makeAliasPathFromPropertyPath("$aggregateAlias.$relationPath");

                    $alias = Helper::pathToAlias($absolutePath);
                    if (in_array($alias, $joins, true)) {
                        continue;
                    }

                    $qb->leftJoin($absolutePath, $alias)->addSelect($alias);
                    $joins[] = $alias;
                }
            }
        }
    }

    private function fetchNestedAssocRelation(
        array &$assocRelations,
        string $metaPath,
        string $targetEntity,
        int $maxNestedLevel,
        int $currentNestedLevel
    ): void {
        if ($maxNestedLevel < $currentNestedLevel) {
            return;
        }
        $em = $this->entityManager;
        $meta = $em->getClassMetadata($targetEntity);

        $assocRelations[$targetEntity] = [
            'entity' => $targetEntity,
            'pathPrefix' => $metaPath,
            'paths' => (static function () use ($em, $targetEntity) {
                $meta = $em->getClassMetadata($targetEntity);
                $fields = [];
                foreach ($meta->associationMappings as $mapping) {
                    $fields[] = $mapping['fieldName'];
                }

                return $fields;
            })(),
        ];

        foreach ($meta->associationMappings as $mapping) {
            if (array_key_exists($mapping['targetEntity'], $assocRelations)) {
                continue;
            }
            $this->fetchNestedAssocRelation(
                assocRelations: $assocRelations,
                metaPath: "$metaPath.{$mapping['fieldName']}",
                targetEntity: $mapping['targetEntity'],
                maxNestedLevel: $maxNestedLevel,
                currentNestedLevel: ++$currentNestedLevel
            );
        }
    }
}
