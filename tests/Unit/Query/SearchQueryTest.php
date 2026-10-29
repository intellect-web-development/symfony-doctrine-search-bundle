<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit\Query;

use DateTimeImmutable;
use InvalidArgumentException;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\FilterOperator;
use IWD\SymfonyDoctrineSearch\Query\Logic;
use IWD\SymfonyDoctrineSearch\Query\Pagination;
use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use IWD\SymfonyDoctrineSearch\Query\SortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchQuery::class)]
#[CoversClass(Filter::class)]
#[CoversClass(FilterOperator::class)]
#[CoversClass(Pagination::class)]
final class SearchQueryTest extends TestCase
{
    public function testScopingIsImmutable(): void
    {
        $original = new SearchQuery(filters: [new Filter('Owner.Id', FilterOperator::Equals, 'x'), new Filter('title', FilterOperator::Like, 'a')]);

        $scoped = $original
            ->withoutFilters('owner.id')
            ->withConstraint(new Filter('owner.id', FilterOperator::Equals, 'me'));

        self::assertCount(2, $original->filters);
        self::assertSame([], $original->constraints);
        self::assertSame(['title'], array_map(static fn (Filter $filter): string => $filter->property, $scoped->filters));
        self::assertSame('me', $scoped->constraints[0]->value);
        self::assertSame(Logic::And, $scoped->logic);
        self::assertCount(1, $scoped->filtersFor('title'));
    }

    public function testFilterNormalizesValues(): void
    {
        self::assertSame('ASC', new Filter('a', FilterOperator::Equals, SortDirection::Asc)->value);
        self::assertSame('2024-01-02 03:04:05', new Filter('a', FilterOperator::Equals, new DateTimeImmutable('2024-01-02 03:04:05'))->value);
        self::assertSame(['x'], new Filter('a', FilterOperator::In, 'x')->value);
        self::assertNull(new Filter('a', FilterOperator::IsNull, 'ignored')->value);
        self::assertSame([null, 5], new Filter('a', FilterOperator::Range, [null, 5])->value);
    }

    public function testFilterRejectsMalformedValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Filter('a', FilterOperator::Range, [null, null]);
    }

    public function testOperatorNames(): void
    {
        self::assertSame(FilterOperator::GreaterOrEquals, FilterOperator::fromName(' >= '));
        self::assertSame(FilterOperator::NotIn, FilterOperator::fromName('NOT-IN'));
        self::assertNull(FilterOperator::fromName('between'));
    }

    public function testPaginationOffset(): void
    {
        self::assertSame(40, new Pagination(3, 20)->offset());

        $this->expectException(InvalidArgumentException::class);
        new Pagination(1, 0);
    }
}
