<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;
use IWD\SymfonyDoctrineSearch\Dto\Input\Filters;
use IWD\SymfonyDoctrineSearch\Dto\Input\Pagination;
use IWD\SymfonyDoctrineSearch\Dto\Input\Sort;
use IWD\SymfonyDoctrineSearch\Dto\Input\Sorts;

class FetcherContext
{
    public QueryBuilder $queryBuilder;
    public EntityManagerInterface $entityManager;
    /** @var class-string */
    public string $entityClass;
    public ClassMetadata $entityClassMetadata;
    public FilterSqlBuilder $filterSqlBuilder;
    public array $entityWhiteList = [];
    public array $entityAssociationWhiteList = [];
    public string $aggregateAlias;

    /**
     * FetcherContext constructor.
     *
     * @param class-string $entityClass
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        QueryBuilder $queryBuilder,
        string $entityClass,
        string $aggregateAlias,
        ClassMetadata $entityClassMetadata,
        FilterSqlBuilder $filterSqlBuilder
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->entityClass = $entityClass;
        $this->entityClassMetadata = $entityClassMetadata;
        $this->filterSqlBuilder = $filterSqlBuilder;
        $this->entityManager = $entityManager;
        $this->aggregateAlias = $aggregateAlias;

        $this->calcWhiteLists();
    }

    /**
     * @return array<string>
     */
    public function fetchJoinList(Filters $filtersForRelations): array
    {
        $joinList = [];

        $entityJoinList = array_unique(
            array_map(static function ($property) {
                $explodeProperty = explode('.', $property);
                array_pop($explodeProperty);

                return implode('.', $explodeProperty);
            }, $this->entityAssociationWhiteList)
        );

        foreach ($filtersForRelations->toArray() as $filter) {
            $explodeProperty = explode('.', $filter->property);
            array_pop($explodeProperty);
            $assocProperty = implode('.', $explodeProperty);

            if (in_array($assocProperty, $entityJoinList, true)) {
                $joinList[] = $assocProperty;
            }
        }

        return $joinList;
    }

    public function fetchFiltersForEntity(Filters $filters): Filters
    {
        return new Filters(
            array_filter($filters->toArray(), function (Filter $filter) {
                return in_array($filter->property, $this->entityWhiteList, true);
            })
        );
    }

    public function filterAllowSorts(Sorts $sorts): Sorts
    {
        return new Sorts(
            array_filter($sorts->toArray(), function (Sort $sort) {
                return in_array($sort->getField(), $this->entityWhiteList, true);
            })
        );
    }

    public function fetchFiltersForRelations(Filters $filters): Filters
    {
        $filtersForRelations = [];
        foreach ($filters->toArray() as $filter) {
            if (in_array($filter->property, $this->entityAssociationWhiteList, true)) {
                $filtersForRelations[] = $filter;
            }
        }

        return new Filters($filtersForRelations);
    }

    private function calcWhiteLists(): void
    {
        $this->entityWhiteList = $this->fetchEntityWhiteList();
        $this->entityAssociationWhiteList = $this->fetchEntityAssociationWhiteList();
    }

    /**
     * @return array<string>
     */
    private function fetchEntityWhiteList(): array
    {
        $whiteList = [];
        foreach ($this->entityClassMetadata->fieldNames as $property) {
            $whiteList[] = $property;
        }

        return $whiteList;
    }

    /**
     * @return array<string>
     */
    private function fetchEntityAssociationWhiteList(): array
    {
        $whiteList = [];
        foreach ($this->entityClassMetadata->associationMappings as $property => $data) {
            $subEntityMeta = $this->entityManager->getClassMetadata($data['targetEntity']);
            foreach ($subEntityMeta->fieldNames as $subProperty) {
                $whiteList[] = "{$property}.{$subProperty}";
            }

            foreach ($this->getChildAssocWhiteList($subEntityMeta->associationMappings, $property) as $item) {
                $whiteList[] = $item;
            }
        }

        return $whiteList;
    }

    /**
     * @param array<string, array> $associationMappings
     *
     * @return array<int, string>
     */
    private function getChildAssocWhiteList(array $associationMappings, string $prefix): array
    {
        $whiteList = [];
        foreach ($associationMappings as $property => $data) {
            $subEntityMeta = $this->entityManager->getClassMetadata($data['targetEntity']);
            if (isset($subEntityMeta->customRepositoryClassName)) {
                continue;
            }
            foreach ($subEntityMeta->fieldNames as $subProperty) {
                $whiteList[] = "{$prefix}.{$property}.{$subProperty}";
            }
        }

        return $whiteList;
    }

    /**
     * @return array<string>
     */
    public function getEntityAssociationWhiteList(): array
    {
        return $this->entityAssociationWhiteList;
    }

    public function addSorts(Sorts $sorts): self
    {
        $this->filterSqlBuilder->addSorts(
            $this->filterAllowSorts($sorts)
        );

        return $this;
    }

    public function addFilters(Filters $filters, bool $withRelations = true): self
    {
        FiltersApplicator::applyMany(
            $this->fetchFiltersForEntity($filters),
            $this->filterSqlBuilder,
            $this->aggregateAlias,
            false
        );

        if ($withRelations) {
            $filtersForRelations = $this->fetchFiltersForRelations($filters);
            foreach ($this->fetchJoinList($filtersForRelations) as $propertyPath) {
                $explodePropertyPath = explode('.', $propertyPath);
                for ($level = 1, $levelMax = count($explodePropertyPath); $level <= $levelMax; ++$level) {
                    $relationPath = Helper::makeRelationPath($explodePropertyPath, $level);
                    $path = Helper::makeAliasPathFromPropertyPath("$this->aggregateAlias.$relationPath");
                    $alias = Helper::pathToAlias($path);

                    $this->queryBuilder->leftJoin($path, $alias);
                }
            }
            if (!empty($filtersForRelations->toArray())) {
                $this->queryBuilder->distinct(true);
            }

            FiltersApplicator::applyMany(
                $filtersForRelations,
                $this->filterSqlBuilder,
                $this->aggregateAlias,
                true
            );
        }

        return $this;
    }

    public function paginate(Pagination $pagination): self
    {
        $this->filterSqlBuilder->setPagination($pagination);

        return $this;
    }
}
