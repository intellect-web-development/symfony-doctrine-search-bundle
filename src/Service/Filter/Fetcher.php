<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;

class Fetcher
{
    public const AGGREGATE_ALIAS = 'entity';
    private FiltersApplicator $filtersApplicator;
    private RelationLoader $relationLoader;
    private GetById $getByIdUseCase;
    private GetByIds $getByIdsUseCase;

    public function __construct(
        private EntityManagerInterface $entityManager,
        ?FiltersApplicator $filtersApplicator = null,
        ?RelationLoader $relationLoader = null,
        ?GetById $getByIdUseCase = null,
        ?GetByIds $getByIdsUseCase = null,
    ) {
        $this->filtersApplicator = $filtersApplicator ?? new FiltersApplicator([
            new DefaultFilterHandler(),
        ]);
        $this->relationLoader = $relationLoader ?? new RelationLoader($entityManager);
        $this->getByIdUseCase = $getByIdUseCase ?? new GetById($this->relationLoader);
        $this->getByIdsUseCase = $getByIdsUseCase ?? new GetByIds($this->relationLoader);
    }

    /**
     * @param class-string $entityClass
     */
    public function createContext(string $entityClass): FetcherContext
    {
        $entityRepository = $this->entityManager->getRepository($entityClass);
        $queryBuilder = $entityRepository->createQueryBuilder(self::AGGREGATE_ALIAS);
        $filterSqlBuilder = new FilterSqlBuilder($queryBuilder);

        return new FetcherContext(
            $this->entityManager,
            $queryBuilder,
            $entityClass,
            self::AGGREGATE_ALIAS,
            $this->entityManager->getClassMetadata($entityClass),
            $filterSqlBuilder,
            $this->filtersApplicator
        );
    }

    public function count(FetcherContext $context): int
    {
        $idPropertyName = current($context->entityClassMetadata->identifier);
        $aggregateAlias = self::AGGREGATE_ALIAS;

        return (int) (clone $context->queryBuilder)
            ->select("count(distinct {$aggregateAlias}.{$idPropertyName})")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getById(
        FetcherContext $context,
        string $id,
        bool $eager = true,
        array $hints = []
    ): object {
        return $this->getByIdUseCase->fetch($context, $id, $eager, $hints);
    }

    public function getByIds(FetcherContext $context, array $ids, bool $eager = true, array $hints = []): array
    {
        return $this->getByIdsUseCase->fetch($context, $ids, $eager, $hints);
    }

    public function createAssocRelationMap(FetcherContext $context, array $hints): array
    {
        return $this->relationLoader->createAssocRelationMap($context, $hints);
    }

    /**
     * @return array<string>
     */
    public function searchEntityIds(FetcherContext $context): array
    {
        $idPropertyName = current($context->entityClassMetadata->identifier);

        return array_map(
            static function (array $entity) use ($idPropertyName) {
                return $entity[$idPropertyName];
            },
            $context->queryBuilder
//                ->select("distinct {$context->aggregateAlias}.{$idPropertyName}")
                ->getQuery()
                ->getResult(AbstractQuery::HYDRATE_ARRAY)
        );
    }
}
