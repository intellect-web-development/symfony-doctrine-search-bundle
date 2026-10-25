<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use IWD\SymfonyDoctrineSearch\Dto\Input\FilterStrategy;
use IWD\SymfonyDoctrineSearch\Service\Filter\FilterSqlBuilder;
use PHPUnit\Framework\TestCase;

class FilterSqlBuilderLikeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function patterns(): iterable
    {
        yield 'plain' => ['abc', '%abc%'];
        yield 'lowercased' => ['AbC', '%abc%'];
        yield 'cyrillic lowercased' => ['Привет', '%привет%'];
        yield 'percent' => ['50%', '%50!%%'];
        yield 'underscore' => ['a_b', '%a!_b%'];
        yield 'escape char' => ['a!b', '%a!!b%'];
        yield 'backslash is literal' => ['a\\b', '%a\\b%'];
        yield 'mixed' => ['100%_!', '%100!%!_!!%'];
    }

    /**
     * @dataProvider patterns
     */
    public function testLike(string $input, string $expectedPattern): void
    {
        $builder = $this->builder();

        $builder->like('e.name', $input);

        self::assertStringContainsString("LOWER(e.name) LIKE :bind_", $builder->queryBuilder->getDQL());
        self::assertStringContainsString("ESCAPE '!'", $builder->queryBuilder->getDQL());
        self::assertSame($expectedPattern, $this->singleParameter($builder));
    }

    /**
     * @dataProvider patterns
     */
    public function testNotLike(string $input, string $expectedPattern): void
    {
        $builder = $this->builder();

        $builder->notLike('e.name', $input);

        self::assertStringContainsString("LOWER(e.name) NOT LIKE :bind_", $builder->queryBuilder->getDQL());
        self::assertStringContainsString("ESCAPE '!'", $builder->queryBuilder->getDQL());
        self::assertSame($expectedPattern, $this->singleParameter($builder));
    }

    public function testOrStrategy(): void
    {
        $builder = $this->builder();

        $builder->like('e.name', 'a_b', FilterStrategy::Or);

        self::assertStringContainsString("LOWER(e.name) LIKE :bind_", $builder->queryBuilder->getDQL());
        self::assertSame('%a!_b%', $this->singleParameter($builder));
    }

    public function testEmptyValueAddsNoCondition(): void
    {
        $builder = $this->builder();

        $builder->like('e.name', '');
        $builder->notLike('e.name', null);

        self::assertStringNotContainsString('LIKE', $builder->queryBuilder->getDQL());
        self::assertCount(0, $builder->queryBuilder->getParameters());
    }

    private function builder(): FilterSqlBuilder
    {
        $qb = new QueryBuilder($this->createMock(EntityManagerInterface::class));
        $qb->select('e')->from('Entity', 'e');

        return new FilterSqlBuilder($qb);
    }

    private function singleParameter(FilterSqlBuilder $builder): mixed
    {
        $parameters = $builder->queryBuilder->getParameters();
        self::assertCount(1, $parameters);

        return $parameters->first()->getValue();
    }
}
