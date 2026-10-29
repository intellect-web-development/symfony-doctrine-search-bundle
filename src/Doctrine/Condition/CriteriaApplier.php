<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Condition;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use IWD\SymfonyDoctrineSearch\Doctrine\Path\PropertyPathResolver;
use IWD\SymfonyDoctrineSearch\Doctrine\Path\ResolvedProperty;
use IWD\SymfonyDoctrineSearch\Doctrine\SingleIdentifier;
use IWD\SymfonyDoctrineSearch\Doctrine\Value\ValueKindResolver;
use IWD\SymfonyDoctrineSearch\Exception\InvalidSearchQueryException;
use IWD\SymfonyDoctrineSearch\Exception\UnresolvableConstraintException;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\Logic;

/**
 * Добавляет в запрос условие `(ограничения AND ...) AND (фильтры через logic)`.
 *
 * Если хоть одному условию нужны связи, всё условие уходит в подзапрос `root.id IN (SELECT ...)`:
 * основной запрос остаётся без join-ов, поэтому to-many связи не размножают строки, а count,
 * пагинация и сортировка работают с одним и тем же набором корневых сущностей.
 *
 * @internal
 */
final readonly class CriteriaApplier
{
    public function __construct(
        private PropertyPathResolver $paths,
        private ValueKindResolver $kinds,
        private ConditionFactory $conditions,
        private int $maxFilterDepth,
        private bool $rejectUnknownProperties,
    ) {
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<Filter>          $constraints
     * @param list<Filter>          $filters
     */
    public function apply(
        EntityManagerInterface $em,
        QueryBuilder $query,
        string $rootAlias,
        ClassMetadata $metadata,
        array $constraints,
        array $filters = [],
        Logic $logic = Logic::And,
    ): void {
        $resolvedConstraints = $this->resolveConstraints($em, $metadata, $constraints);
        $resolvedFilters = $this->resolveFilters($em, $metadata, $filters);
        if ([] === $resolvedConstraints && [] === $resolvedFilters) {
            return;
        }

        $needsJoin = [] !== array_filter(
            [...$resolvedConstraints, ...$resolvedFilters],
            static fn (array $pair): bool => $pair[1]->needsJoin(),
        );
        $subquery = null;
        if ($needsJoin) {
            $subquery = $em->createQueryBuilder()
                ->select('sub.' . SingleIdentifier::fieldOf($metadata))
                ->from($metadata->getName(), 'sub');
            $scope = new QueryScope($subquery, 'sub', $query);
        } else {
            $scope = new QueryScope($query, $rootAlias, $query);
        }

        $parts = $this->build($em, $scope, $resolvedConstraints, true);
        $filterParts = $this->build($em, $scope, $resolvedFilters, false);
        if ([] !== $filterParts) {
            $parts[] = Logic::Or === $logic ? $query->expr()->orX(...$filterParts) : $query->expr()->andX(...$filterParts);
        }
        if ([] === $parts) {
            return;
        }

        $condition = $query->expr()->andX(...$parts);
        if (null === $subquery) {
            $query->andWhere($condition);

            return;
        }

        $subquery->where($condition);
        $query->andWhere($query->expr()->in($rootAlias . '.' . SingleIdentifier::fieldOf($metadata), $subquery->getDQL()));
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<Filter>          $constraints
     *
     * @return list<array{Filter, ResolvedProperty}>
     */
    private function resolveConstraints(EntityManagerInterface $em, ClassMetadata $metadata, array $constraints): array
    {
        return array_map(
            fn (Filter $constraint): array => [
                $constraint,
                $this->paths->resolve($em, $metadata, $constraint->property, null)
                    ?? throw UnresolvableConstraintException::create($metadata->getName(), $constraint->property),
            ],
            $constraints,
        );
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<Filter>          $filters
     *
     * @return list<array{Filter, ResolvedProperty}>
     */
    private function resolveFilters(EntityManagerInterface $em, ClassMetadata $metadata, array $filters): array
    {
        $resolved = [];
        foreach ($filters as $filter) {
            $property = $this->paths->resolve($em, $metadata, $filter->property, $this->maxFilterDepth);
            if (null !== $property) {
                $resolved[] = [$filter, $property];
            } elseif ($this->rejectUnknownProperties) {
                throw InvalidSearchQueryException::unknownFilterProperty($filter->property);
            }
        }

        return $resolved;
    }

    /**
     * @param list<array{Filter, ResolvedProperty}> $pairs
     *
     * @return list<string>
     */
    private function build(EntityManagerInterface $em, QueryScope $scope, array $pairs, bool $isConstraint): array
    {
        $parts = [];
        foreach ($pairs as [$filter, $property]) {
            $condition = $this->conditions->create(
                $filter,
                $scope->field($property),
                $this->kinds->resolve($em, $property->owner, $property->field),
                $scope,
                $isConstraint,
            );
            if (null !== $condition) {
                $parts[] = $condition;
            }
        }

        return $parts;
    }
}
