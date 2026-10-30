<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use IWD\SymfonyDoctrineSearch\Doctrine\Condition\ConditionFactory;
use IWD\SymfonyDoctrineSearch\Doctrine\Condition\CriteriaApplier;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntityReader;
use IWD\SymfonyDoctrineSearch\Doctrine\DoctrineEntitySearch;
use IWD\SymfonyDoctrineSearch\Doctrine\Eager\EagerLoader;
use IWD\SymfonyDoctrineSearch\Doctrine\Path\PropertyPathResolver;
use IWD\SymfonyDoctrineSearch\Doctrine\Value\ValueKindResolver;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Author;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Book;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Country;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Publisher;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Region;

/**
 * Библиотека в SQLite в памяти — две независимые цепочки «владельцев» (RU и US):
 *
 *     Country RU ← Region 1 «North» ← Publisher 1 «Alpha» ← Author 1 Tolstoy (active)   ← Book b1 «War and Peace» 1225 стр., 1869
 *                                                                                       ← Book b2 «Anna Karenina» 864 стр., 1878
 *     Country US ← Region 2 «West»  ← Publisher 2 «Beta»  ← Author 2 Chekhov (inactive) ← Book b3 «The Seagull» 64 стр., 1896
 *                                     (без издателя)      ← Author 3 Gogol (active)     ← Book b4 «100%_Dead Souls» без страниц и даты
 */
final class Library
{
    public const string B1 = '00000000-0000-4000-8000-000000000001';
    public const string B2 = '00000000-0000-4000-8000-000000000002';
    public const string B3 = '00000000-0000-4000-8000-000000000003';
    public const string B4 = '00000000-0000-4000-8000-000000000004';

    public static function entityManager(): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($em)->createSchema($em->getMetadataFactory()->getAllMetadata());

        $russia = new Country('RU');
        $usa = new Country('US');
        $north = new Region(1, 'North', $russia);
        $west = new Region(2, 'West', $usa);
        $alpha = new Publisher(1, 'Alpha', $north);
        $beta = new Publisher(2, 'Beta', $west);
        $tolstoy = new Author(1, 'Tolstoy', true, $alpha);
        $chekhov = new Author(2, 'Chekhov', false, $beta);
        $gogol = new Author(3, 'Gogol', true);
        $books = [
            new Book(self::B1, 'War and Peace', $tolstoy, 1225, new DateTimeImmutable('1869-01-01')),
            new Book(self::B2, 'Anna Karenina', $tolstoy, 864, new DateTimeImmutable('1878-01-01')),
            new Book(self::B3, 'The Seagull', $chekhov, 64, new DateTimeImmutable('1896-01-01')),
            new Book(self::B4, '100%_Dead Souls', $gogol),
        ];
        foreach ([$russia, $usa, $north, $west, $alpha, $beta, $tolstoy, $chekhov, $gogol, ...$books] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();

        return $em;
    }

    public static function criteria(int $maxFilterDepth = 2, bool $rejectUnknownProperties = false): CriteriaApplier
    {
        return new CriteriaApplier(new PropertyPathResolver(), new ValueKindResolver(), new ConditionFactory(), $maxFilterDepth, $rejectUnknownProperties);
    }

    public static function search(EntityManagerInterface $em, ?CriteriaApplier $criteria = null, bool $rejectUnknownProperties = false): DoctrineEntitySearch
    {
        return new DoctrineEntitySearch(new ManagerRegistry($em), $criteria ?? self::criteria(), new EagerLoader(1), 20, $rejectUnknownProperties);
    }

    public static function reader(EntityManagerInterface $em): DoctrineEntityReader
    {
        return new DoctrineEntityReader(new ManagerRegistry($em), self::criteria(), new ValueKindResolver(), new EagerLoader(1));
    }
}
