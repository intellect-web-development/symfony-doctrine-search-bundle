<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntitySearch;
use IWD\SymfonyDoctrineSearch\Exception\InvalidSearchQueryException;
use IWD\SymfonyDoctrineSearch\Exception\UnresolvableConstraintException;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\FilterOperator as Op;
use IWD\SymfonyDoctrineSearch\Query\Logic;
use IWD\SymfonyDoctrineSearch\Query\Pagination;
use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use IWD\SymfonyDoctrineSearch\Query\Sort;
use IWD\SymfonyDoctrineSearch\Query\SortDirection;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Author;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Book;
use IWD\SymfonyDoctrineSearch\Tests\Support\Library;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineEntitySearch::class)]
final class EntitySearchTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = Library::entityManager();
    }

    /**
     * @param list<Filter> $filters
     * @param list<string> $expectedTitles
     */
    #[DataProvider('filterCases')]
    public function testFilters(array $filters, Logic $logic, array $expectedTitles): void
    {
        $this->assertBooks($expectedTitles, new SearchQuery(filters: $filters, logic: $logic));
    }

    /**
     * @return iterable<string, array{list<Filter>, Logic, list<string>}>
     */
    public static function filterCases(): iterable
    {
        $and = Logic::And;
        $or = Logic::Or;

        yield 'eq' => [[new Filter('title', Op::Equals, 'The Seagull')], $and, ['The Seagull']];
        yield 'like is case-insensitive' => [[new Filter('title', Op::Like, 'ANNA')], $and, ['Anna Karenina']];
        yield 'like is literal' => [[new Filter('title', Op::Like, '%_')], $and, ['100%_Dead Souls']];
        yield 'not-like' => [[new Filter('title', Op::NotLike, 'n')], $and, ['100%_Dead Souls', 'The Seagull']];
        yield 'in' => [[new Filter('pages', Op::In, [64, 864])], $and, ['Anna Karenina', 'The Seagull']];
        yield 'not-in' => [[new Filter('pages', Op::NotIn, ['64'])], $and, ['Anna Karenina', 'War and Peace']];
        yield 'empty in is not a filter' => [[new Filter('pages', Op::In, [])], $and, ['100%_Dead Souls', 'Anna Karenina', 'The Seagull', 'War and Peace']];
        yield 'range' => [[new Filter('pages', Op::Range, ['100', '1000'])], $and, ['Anna Karenina']];
        yield 'open range' => [[new Filter('pages', Op::Range, [null, '864'])], $and, ['Anna Karenina', 'The Seagull']];
        yield 'date range' => [[new Filter('publishedAt', Op::Range, ['1870-01-01', '1900-01-01'])], $and, ['Anna Karenina', 'The Seagull']];
        yield 'is-null' => [[new Filter('pages', Op::IsNull)], $and, ['100%_Dead Souls']];
        yield 'relation' => [[new Filter('author.name', Op::Equals, 'Tolstoy')], $and, ['Anna Karenina', 'War and Peace']];
        yield 'bool on relation' => [[new Filter('author.active', Op::Equals, 'false')], $and, ['The Seagull']];
        yield 'nested relation' => [[new Filter('author.publisher.name', Op::Equals, 'Beta')], $and, ['The Seagull']];
        yield 'or across root and relation' => [[new Filter('title', Op::Like, 'peace'), new Filter('author.name', Op::Equals, 'Gogol')], $or, ['100%_Dead Souls', 'War and Peace']];
        yield 'unknown property is ignored' => [[new Filter('nope', Op::Equals, 'x'), new Filter('pages', Op::Equals, '64')], $and, ['The Seagull']];
        yield 'text in integer column matches nothing' => [[new Filter('pages', Op::Equals, 'abc')], $and, []];
        yield 'text in integer column under or' => [[new Filter('pages', Op::Equals, 'abc'), new Filter('title', Op::Like, 'seagull')], $or, ['The Seagull']];
        yield 'negated incompatible value matches everything' => [[new Filter('pages', Op::NotEquals, 'abc')], $and, ['100%_Dead Souls', 'Anna Karenina', 'The Seagull', 'War and Peace']];
        yield 'malformed uuid matches nothing' => [[new Filter('id', Op::Equals, 'not-a-uuid')], $and, []];
        yield 'like on integer column matches nothing' => [[new Filter('pages', Op::Like, '6')], $and, []];
    }

    public function testFilterDepthIsLimitedForClientsOnly(): void
    {
        $search = Library::search($this->em, Library::criteria(maxFilterDepth: 1));
        $query = new SearchQuery(filters: [new Filter('author.publisher.name', Op::Equals, 'Beta')]);

        self::assertSame(4, $search->search(Book::class, $query)->pagination->count);
        self::assertSame(1, $search->search(Book::class, $query->withoutFilters('author.publisher.name')->withConstraint(...$query->filters))->pagination->count);
    }

    public function testUnknownPropertyCanBeRejected(): void
    {
        $search = Library::search($this->em, Library::criteria(rejectUnknownProperties: true));

        $this->expectException(InvalidSearchQueryException::class);
        $search->search(Book::class, new SearchQuery(filters: [new Filter('nope', Op::Equals, 'x')]));
    }

    public function testUnknownSortCanBeRejected(): void
    {
        $search = Library::search($this->em, rejectUnknownProperties: true);

        $this->expectException(InvalidSearchQueryException::class);
        $search->search(Book::class, new SearchQuery(sorts: [new Sort('nope')]));
    }

    public function testConstraintIsAppliedOnTopOfOrFilters(): void
    {
        $query = new SearchQuery(
            filters: [new Filter('title', Op::Like, 'seagull'), new Filter('author.name', Op::Equals, 'Gogol')],
            logic: Logic::Or,
            constraints: [new Filter('author.publisher.name', Op::Equals, 'Alpha')],
        );

        $this->assertBooks([], $query);
    }

    public function testUnresolvableConstraintFailsInsteadOfBeingSkipped(): void
    {
        $this->expectException(UnresolvableConstraintException::class);
        Library::search($this->em)->search(Book::class, new SearchQuery(constraints: [new Filter('author.owner.id', Op::Equals, '1')]));
    }

    public function testEmptyInConstraintMatchesNothing(): void
    {
        $this->assertBooks([], new SearchQuery(constraints: [new Filter('author.id', Op::In, [])]));
    }

    public function testToManyFilterDoesNotDuplicateRoots(): void
    {
        $result = Library::search($this->em)->search(
            Author::class,
            new SearchQuery(filters: [new Filter('books.title', Op::Like, 'n')]),
        );

        // Обе книги Толстого подходят, но автор должен попасть в выдачу и в count один раз.
        self::assertSame(['Tolstoy'], array_map(static fn (Author $author): string => $author->name, $result->entities));
        self::assertSame(1, $result->pagination->count);
    }

    public function testSortingAndPaginationKeepOrderWithEagerLoading(): void
    {
        $query = new SearchQuery(sorts: [new Sort('pages', SortDirection::Desc)], pagination: new Pagination(1, 3));

        foreach ([true, false] as $eager) {
            $result = Library::search($this->em)->search(Book::class, $query, $eager);
            self::assertSame([Library::B1, Library::B2, Library::B3], array_map(static fn (Book $book): string => $book->id, $result->entities));
            self::assertSame(['count' => 4, 'totalPages' => 2, 'page' => 1, 'size' => 3], (array) $result->pagination);
            $this->em->clear();
        }
    }

    public function testEqualSortValuesAreOrderedByIdentifier(): void
    {
        $result = Library::search($this->em)->search(Author::class, new SearchQuery(sorts: [new Sort('active', SortDirection::Desc)]));

        self::assertSame([1, 3, 2], array_map(static fn (Author $author): int => $author->id, $result->entities));
    }

    public function testPageBeyondTheEndIsEmpty(): void
    {
        $result = Library::search($this->em)->search(Book::class, new SearchQuery(pagination: new Pagination(3, 2)));

        self::assertSame([], $result->entities);
        self::assertSame(['count' => 4, 'totalPages' => 2, 'page' => 3, 'size' => 0], (array) $result->pagination);
    }

    public function testEagerLoadingFetchesRelations(): void
    {
        $query = new SearchQuery(filters: [new Filter('id', Op::Equals, Library::B3)]);

        $eager = Library::search($this->em)->search(Book::class, $query, eager: true)->entities[0];
        self::assertFalse($this->em->getUnitOfWork()->isUninitializedObject($eager->author));

        $this->em->clear();
        $lazy = Library::search($this->em)->search(Book::class, $query, eager: false)->entities[0];
        self::assertTrue($this->em->getUnitOfWork()->isUninitializedObject($lazy->author));
    }

    /**
     * @param list<string> $expectedTitles
     */
    private function assertBooks(array $expectedTitles, SearchQuery $query): void
    {
        $result = Library::search($this->em)->search(Book::class, $query);
        $titles = array_map(static fn (Book $book): string => $book->title, $result->entities);

        self::assertSame($expectedTitles, $this->sorted($titles));
        self::assertSame(count($expectedTitles), $result->pagination->count);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
