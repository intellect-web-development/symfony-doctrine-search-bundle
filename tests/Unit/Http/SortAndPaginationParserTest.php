<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit\Http;

use IWD\SymfonyDoctrineSearch\Http\Parser\PaginationParser;
use IWD\SymfonyDoctrineSearch\Http\Parser\SortParser;
use IWD\SymfonyDoctrineSearch\Query\Pagination;
use IWD\SymfonyDoctrineSearch\Query\Sort;
use IWD\SymfonyDoctrineSearch\Query\SortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SortParser::class)]
#[CoversClass(PaginationParser::class)]
final class SortAndPaginationParserTest extends TestCase
{
    /**
     * @param list<Sort> $expected
     */
    #[DataProvider('sorts')]
    public function testSort(mixed $raw, array $expected): void
    {
        self::assertEquals($expected, new SortParser()->parse($raw));
    }

    /**
     * @return iterable<string, array{mixed, list<Sort>}>
     */
    public static function sorts(): iterable
    {
        yield 'empty' => ['', []];
        yield 'not a string' => [['a'], []];
        yield 'list' => ['-a, b,,-', [new Sort('a', SortDirection::Desc), new Sort('b')]];
        yield 'json' => ['{"a":"desc","b":"ASC","c":"sideways","d":1}', [new Sort('a', SortDirection::Desc), new Sort('b')]];
        yield 'broken json' => ['{oops', []];
    }

    #[DataProvider('pages')]
    public function testPagination(mixed $raw, ?Pagination $expected): void
    {
        self::assertEquals($expected, new PaginationParser(defaultSize: 20, maxSize: 100)->parse($raw));
    }

    /**
     * @return iterable<string, array{mixed, ?Pagination}>
     */
    public static function pages(): iterable
    {
        yield 'absent' => [null, null];
        yield 'array' => [['number' => '2', 'size' => '10'], new Pagination(2, 10)];
        yield 'json' => ['{"number":3,"size":5}', new Pagination(3, 5)];
        yield 'defaults' => [[], new Pagination(1, 20)];
        yield 'zero size falls back to default' => [['size' => '0'], new Pagination(1, 20)];
        yield 'negative number is the first page' => [['number' => '-4'], new Pagination(1, 20)];
        yield 'too large size is capped' => [['size' => '1000'], new Pagination(1, 100)];
        yield 'garbage' => [['number' => 'x', 'size' => 'y'], new Pagination(1, 20)];
    }
}
