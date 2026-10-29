<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit\Http;

use IWD\SymfonyDoctrineSearch\Http\Parser\FilterParser;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\FilterOperator as Op;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterParser::class)]
final class FilterParserTest extends TestCase
{
    /**
     * @param list<Filter> $expected
     */
    #[DataProvider('cases')]
    public function testParse(mixed $legacy, mixed $compact, array $expected): void
    {
        self::assertEquals($expected, new FilterParser()->parse($legacy, $compact));
    }

    /**
     * @return iterable<string, array{mixed, mixed, list<Filter>}>
     */
    public static function cases(): iterable
    {
        yield 'nothing' => [null, null, []];
        yield 'operator aliases' => [
            ['a' => ['=' => 'x', 'GTE' => '1', 'not-equals' => 'y']],
            null,
            [new Filter('a', Op::Equals, 'x'), new Filter('a', Op::GreaterOrEquals, '1'), new Filter('a', Op::NotEquals, 'y')],
        ];
        yield 'unknown operator is equality' => [['a' => ['what' => 'x']], null, [new Filter('a', Op::Equals, 'x')]];
        yield 'scalar for in becomes a list' => [['a' => ['in' => 'x']], null, [new Filter('a', Op::In, ['x'])]];
        yield 'range forms' => [
            ['a' => ['range' => '1,5'], 'b' => ['range' => '1'], 'c' => ['range' => ',5'], 'd' => ['range' => ',']],
            null,
            [new Filter('a', Op::Range, ['1', '5']), new Filter('b', Op::Range, ['1', null]), new Filter('c', Op::Range, [null, '5'])],
        ];
        yield 'is-null ignores the value' => [['a' => ['is-null' => '']], null, [new Filter('a', Op::IsNull)]];
        yield 'array for a scalar operator is dropped' => [['a' => ['eq' => ['x']]], null, []];
        yield 'numeric keys and non-arrays are dropped' => [[0 => ['eq' => 'x'], 'a' => 'x'], null, []];
        yield 'compact json with typed values' => [
            null,
            '{"a":{"eq":true},"b":{"in":[1,2]},"c":{"gte":1.5}}',
            [new Filter('a', Op::Equals, '1'), new Filter('b', Op::In, ['1', '2']), new Filter('c', Op::GreaterOrEquals, '1.5')],
        ];
        yield 'compact overrides legacy per property' => [
            ['a' => ['eq' => 'legacy'], 'b' => ['eq' => 'kept']],
            '{"a":{"like":"compact"}}',
            [new Filter('b', Op::Equals, 'kept'), new Filter('a', Op::Like, 'compact')],
        ];
        yield 'broken json is ignored' => [['a' => ['eq' => 'x']], '{oops', [new Filter('a', Op::Equals, 'x')]];
        yield 'compact as array' => [null, ['a' => ['eq' => 'x']], [new Filter('a', Op::Equals, 'x')]];
    }
}
