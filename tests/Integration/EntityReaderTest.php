<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Integration;

use Doctrine\ORM\PersistentCollection;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntityReader;
use IWD\SymfonyDoctrineSearch\Exception\EntityNotFoundException;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\FilterOperator;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Author;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Book;
use IWD\SymfonyDoctrineSearch\Tests\Support\Library;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineEntityReader::class)]
final class EntityReaderTest extends TestCase
{
    public function testGetsById(): void
    {
        $reader = Library::reader(Library::entityManager());

        self::assertSame('The Seagull', $reader->get(Book::class, Library::B3)->title);
        self::assertSame('Gogol', $reader->get(Author::class, 3)->name);
        self::assertSame('Gogol', $reader->get(Author::class, '3')->name);
    }

    public function testConstraintHidesForeignEntity(): void
    {
        $reader = Library::reader(Library::entityManager());
        $ownedByAlpha = [new Filter('author.publisher.name', FilterOperator::Equals, 'Alpha')];

        self::assertNotNull($reader->find(Book::class, Library::B1, $ownedByAlpha));
        self::assertNull($reader->find(Book::class, Library::B3, $ownedByAlpha));
    }

    public function testMalformedIdentifierIsNotFound(): void
    {
        $reader = Library::reader(Library::entityManager());

        self::assertNull($reader->find(Book::class, 'not-a-uuid'));
        self::assertNull($reader->find(Author::class, 'abc'));
    }

    public function testGetThrowsReadableException(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage("Book with id 'not-a-uuid' does not exist");

        Library::reader(Library::entityManager())->get(Book::class, 'not-a-uuid');
    }

    public function testEagerReadLoadsCollections(): void
    {
        $em = Library::entityManager();
        $author = Library::reader($em)->get(Author::class, 1, eager: true);

        self::assertInstanceOf(PersistentCollection::class, $author->books);
        self::assertTrue($author->books->isInitialized());
        self::assertCount(2, $author->books);
    }
}
