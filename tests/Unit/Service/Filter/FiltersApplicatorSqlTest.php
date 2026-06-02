<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit\Service\Filter;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;
use IWD\SymfonyDoctrineSearch\Dto\Input\Filters;
use IWD\SymfonyDoctrineSearch\Dto\Input\FilterStrategy;
use IWD\SymfonyDoctrineSearch\Service\Filter\DefaultFilterHandler;
use IWD\SymfonyDoctrineSearch\Service\Filter\FilterMode;
use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use IWD\SymfonyDoctrineSearch\Service\Filter\FiltersApplicator;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\TestEntity;
use PHPUnit\Framework\TestCase;

class FiltersApplicatorSqlTest extends TestCase
{
    public function testApplyManyBuildsExpectedSqlConditions(): void
    {
        $builder = $this->createFilterSqlBuilder();
        $applicator = new FiltersApplicator([new DefaultFilterHandler()]);

        $filters = new Filters([
            new Filter(property: 'name', mode: FilterMode::Like, value: 'bond'),
            new Filter(property: 'rating', mode: FilterMode::GreaterOrEquals, value: 50),
        ]);

        $applicator->applyMany($filters, $builder, 'e', false);

        $sql = $builder->queryBuilder->getQuery()->getSQL();
        $expectedSql = 'SELECT t0_.id AS id_0, t0_.name AS name_1, t0_.rating AS rating_2 FROM test_entity t0_ WHERE LOWER(t0_.name) LIKE ? AND t0_.rating >= ?';

        self::assertSame($expectedSql, $sql);
    }

    public function testApplyManySupportsCompositeStrategyGrouping(): void
    {
        $builder = $this->createFilterSqlBuilder();
        $applicator = new FiltersApplicator([new DefaultFilterHandler()]);

        $filters = new Filters([
            new Filter(property: 'rating', mode: FilterMode::Equals, value: 10),
            new Filter(property: 'name', mode: FilterMode::Like, value: 'corp'),
        ]);

        $applicator->applyMany(
            filters: $filters,
            appSqlBuilder: $builder,
            fieldPrefix: 'e',
            isRelation: false,
            compositeStrategy: FilterStrategy::Or,
            strategy: FilterStrategy::And
        );

        $sql = $builder->queryBuilder->getQuery()->getSQL();
        $expectedSql = 'SELECT t0_.id AS id_0, t0_.name AS name_1, t0_.rating AS rating_2 FROM test_entity t0_ WHERE (t0_.rating = ? OR LOWER(t0_.name) LIKE ?)';

        self::assertSame($expectedSql, $sql);
    }

    private function createFilterSqlBuilder(): FilterSqlBuilder
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 4) . '/Support/Entity'],
            isDevMode: true
        );

        $entityManager = EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema([
            $entityManager->getClassMetadata(TestEntity::class),
        ]);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('e')
            ->from(TestEntity::class, 'e');

        return new FilterSqlBuilder($queryBuilder);
    }
}
