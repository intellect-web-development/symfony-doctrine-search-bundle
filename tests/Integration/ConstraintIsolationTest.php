<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntityReader;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntitySearch;
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
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Publisher;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Region;
use IWD\SymfonyDoctrineSearch\Tests\Support\Library;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Изоляция «владельцев»: серверное ограничение (constraint) не должно пропускать чужие записи ни при какой форме
 * клиентского запроса. Владелец здесь — страна, и до книги от неё четыре связи:
 * `Book.author → Author.publisher → Publisher.region → Region.country`.
 * Именно глубокие пути и сущности с собственным репозиторием в 1.x теряли условие молча.
 */
#[CoversClass(DoctrineEntitySearch::class)]
#[CoversClass(DoctrineEntityReader::class)]
final class ConstraintIsolationTest extends TestCase
{
    private const string BOOK_COUNTRY = 'author.publisher.region.country.code';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = Library::entityManager();
    }

    public function testDeepConstraintReturnsOnlyOwnRecords(): void
    {
        $this->assertBooks(['Anna Karenina', 'War and Peace'], new SearchQuery(constraints: [$this->ownedBy('RU')]));
        $this->assertBooks(['The Seagull'], new SearchQuery(constraints: [$this->ownedBy('US')]));
    }

    public function testRecordWithoutPublisherIsNotOwnedByAnyone(): void
    {
        // Гоголь без издателя: цепочка до страны обрывается, значит его книга не принадлежит ни RU, ни US.
        foreach (['RU', 'US'] as $country) {
            $result = Library::search($this->em)->search(Book::class, new SearchQuery(constraints: [$this->ownedBy($country)]));

            self::assertNotContains(Library::B4, array_map(static fn (Book $book): string => $book->id, $result->entities));
        }
    }

    public function testClientFilterMatchingOnlyForeignRecordsFindsNothing(): void
    {
        $query = new SearchQuery(
            filters: [new Filter('title', Op::Like, 'seagull')],
            constraints: [$this->ownedBy('RU')],
        );

        $this->assertBooks([], $query);
    }

    public function testClientOrFilterCannotWidenTheOwnerScope(): void
    {
        $query = new SearchQuery(
            filters: [new Filter('title', Op::Like, 'seagull'), new Filter('author.name', Op::Equals, 'Tolstoy'), new Filter('author.name', Op::Equals, 'Gogol')],
            logic: Logic::Or,
            constraints: [$this->ownedBy('RU')],
        );

        // Под OR подходят «Чайка», Толстой и Гоголь, но принадлежат RU только книги Толстого.
        $this->assertBooks(['Anna Karenina', 'War and Peace'], $query);
    }

    public function testClientFilterOnAnotherOwnerFindsNothing(): void
    {
        // Клиентский фильтр допустимой глубины, который указывает на чужую цепочку.
        $query = new SearchQuery(
            filters: [new Filter('author.publisher.name', Op::Equals, 'Beta')],
            constraints: [$this->ownedBy('RU')],
        );

        $this->assertBooks([], $query);
    }

    public function testClientNegationAndNullFiltersStayInsideTheScope(): void
    {
        $negation = new SearchQuery(
            filters: [new Filter('author.publisher.name', Op::NotEquals, 'Alpha')],
            constraints: [$this->ownedBy('RU')],
        );
        $this->assertBooks([], $negation);

        $nullable = new SearchQuery(
            filters: [new Filter('pages', Op::IsNull)],
            constraints: [$this->ownedBy('RU')],
        );
        $this->assertBooks([], $nullable);
    }

    public function testTooDeepClientFilterNeverWidensTheScope(): void
    {
        // Глубже filters.max_relation_depth клиентский фильтр не применяется; область при этом остаётся только своей.
        foreach (['US', 'RU'] as $country) {
            $query = new SearchQuery(
                filters: [new Filter(self::BOOK_COUNTRY, Op::Equals, $country)],
                logic: Logic::Or,
                constraints: [$this->ownedBy('RU')],
            );

            $this->assertBooks(['Anna Karenina', 'War and Peace'], $query);
        }
    }

    public function testConstraintsAreCombinedWithAnd(): void
    {
        $query = new SearchQuery(constraints: [$this->ownedBy('RU'), new Filter('title', Op::Like, 'anna')]);

        $this->assertBooks(['Anna Karenina'], $query);
    }

    public function testScopeHoldsOnEveryPageAndCountIgnoresForeignRecords(): void
    {
        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $query = new SearchQuery(
                sorts: [new Sort('pages', SortDirection::Desc)],
                pagination: new Pagination($page, 1),
                constraints: [$this->ownedBy('RU')],
            );
            $result = Library::search($this->em)->search(Book::class, $query);

            self::assertSame(2, $result->pagination->count, "page $page");
            self::assertSame(2, $result->pagination->totalPages, "page $page");
            $seen = [...$seen, ...array_map(static fn (Book $book): string => $book->id, $result->entities)];
            $this->em->clear();
        }

        self::assertSame([Library::B1, Library::B2], $seen);
    }

    public function testEagerAndLazyLoadingRespectTheScope(): void
    {
        foreach ([true, false] as $eager) {
            $result = Library::search($this->em)->search(Book::class, new SearchQuery(constraints: [$this->ownedBy('RU')]), $eager);

            self::assertSame([Library::B1, Library::B2], $this->sortedIds($result->entities), $eager ? 'eager' : 'lazy');
            self::assertSame(2, $result->pagination->count);
            $this->em->clear();
        }
    }

    public function testConstraintThroughEntityWithOwnRepositoryIsApplied(): void
    {
        $regions = Library::search($this->em)->search(Region::class, new SearchQuery(constraints: [new Filter('country.code', Op::Equals, 'US')]));
        self::assertSame(['West'], array_map(static fn (Region $region): string => $region->name, $regions->entities));
        self::assertSame(1, $regions->pagination->count);

        $publishers = Library::search($this->em)->search(Publisher::class, new SearchQuery(constraints: [new Filter('region.country.code', Op::Equals, 'RU')]));
        self::assertSame(['Alpha'], array_map(static fn (Publisher $publisher): string => $publisher->name, $publishers->entities));
        self::assertSame(1, $publishers->pagination->count);
    }

    public function testToManyConstraintDoesNotDuplicateOrLeak(): void
    {
        // Авторы с книгой от 60 страниц: у Толстого таких две, но автор должен быть в выдаче и в count один раз.
        $query = new SearchQuery(constraints: [new Filter('books.pages', Op::GreaterOrEquals, 60)]);
        $result = Library::search($this->em)->search(Author::class, $query);

        self::assertSame(['Chekhov', 'Tolstoy'], $this->sortedNames($result->entities));
        self::assertSame(2, $result->pagination->count);
    }

    public function testToManyConstraintHoldsAgainstClientOrFilters(): void
    {
        $query = new SearchQuery(
            filters: [new Filter('name', Op::Equals, 'Gogol'), new Filter('books.title', Op::Like, 'war')],
            logic: Logic::Or,
            constraints: [new Filter('books.pages', Op::GreaterOrEquals, 60)],
        );
        $result = Library::search($this->em)->search(Author::class, $query);

        // Гоголь подходит клиентскому фильтру, но у его единственной книги нет страниц.
        self::assertSame(['Tolstoy'], $this->sortedNames($result->entities));
        self::assertSame(1, $result->pagination->count);
    }

    public function testReaderHidesForeignEntityAtDepth(): void
    {
        $reader = Library::reader($this->em);
        $owned = [$this->ownedBy('RU')];

        foreach ([false, true] as $eager) {
            self::assertNotNull($reader->find(Book::class, Library::B1, $owned, $eager));
            self::assertNull($reader->find(Book::class, Library::B3, $owned, $eager));
            self::assertNull($reader->find(Book::class, Library::B4, $owned, $eager));
        }
    }

    public function testBrokenConstraintPathFailsInsteadOfExposingEveryone(): void
    {
        $this->expectException(UnresolvableConstraintException::class);

        Library::search($this->em)->search(Book::class, new SearchQuery(constraints: [new Filter('author.publisher.region.country.nope', Op::Equals, 'RU')]));
    }

    public function testBrokenConstraintPathFailsForReaderToo(): void
    {
        $this->expectException(UnresolvableConstraintException::class);

        Library::reader($this->em)->find(Book::class, Library::B1, [new Filter('author.publisher.nope.country.code', Op::Equals, 'RU')]);
    }

    private function ownedBy(string $country): Filter
    {
        return new Filter(self::BOOK_COUNTRY, Op::Equals, $country);
    }

    /**
     * @param list<string> $expectedTitles
     */
    private function assertBooks(array $expectedTitles, SearchQuery $query): void
    {
        $result = Library::search($this->em)->search(Book::class, $query);
        $titles = array_map(static fn (Book $book): string => $book->title, $result->entities);
        sort($titles);

        self::assertSame($expectedTitles, $titles);
        self::assertSame(count($expectedTitles), $result->pagination->count);
    }

    /**
     * @param list<Book> $books
     *
     * @return list<string>
     */
    private function sortedIds(array $books): array
    {
        $ids = array_map(static fn (Book $book): string => $book->id, $books);
        sort($ids);

        return $ids;
    }

    /**
     * @param list<Author> $authors
     *
     * @return list<string>
     */
    private function sortedNames(array $authors): array
    {
        $names = array_map(static fn (Author $author): string => $author->name, $authors);
        sort($names);

        return $names;
    }
}
