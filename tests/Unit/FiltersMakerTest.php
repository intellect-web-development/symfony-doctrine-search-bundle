<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit;

use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;
use IWD\SymfonyDoctrineSearch\Service\Filter\FilterMode;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations\FiltersMaker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class FiltersMakerTest extends TestCase
{
    public function testLegacyNestedFilter(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filter' => [
                'emails.email' => ['like' => '26d@'],
                'status' => ['in' => ['active', 'blocked']],
            ],
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::Like, $filters['emails.email']->mode);
        self::assertSame('26d@', $filters['emails.email']->value);
        self::assertSame(FilterMode::In, $filters['status']->mode);
        self::assertSame(['active', 'blocked'], $filters['status']->value);
    }

    public function testCompactJsonString(): void
    {
        $json = '{"email":{"like":"26d@"},"status":{"in":["active","blocked"]}}';
        $request = Request::create('/clients?filters=' . rawurlencode($json));

        $filters = $this->byProperty($request);

        self::assertCount(2, $filters);
        self::assertSame(FilterMode::Like, $filters['email']->mode);
        self::assertSame('26d@', $filters['email']->value);
        self::assertSame(FilterMode::In, $filters['status']->mode);
        self::assertSame(['active', 'blocked'], $filters['status']->value);
    }

    public function testCompactJsonOverridesSamePropertyAndKeepsOtherLegacy(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filter' => [
                'email' => ['like' => 'old@'],
                'name' => ['eq' => 'Tesla'],
            ],
            'filters' => '{"email":{"like":"new@"}}',
        ]);

        $filters = $this->byProperty($request);

        self::assertCount(2, $filters);
        self::assertSame('new@', $filters['email']->value);
        self::assertSame(FilterMode::Like, $filters['email']->mode);
        self::assertSame(FilterMode::EqualsAlias1, $filters['name']->mode);
        self::assertSame('Tesla', $filters['name']->value);
    }

    public function testInvalidJsonIgnoresCompactAndKeepsLegacy(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filter' => [
                'email' => ['like' => '26d@'],
            ],
            'filters' => '{not-json',
        ]);

        $filters = $this->byProperty($request);

        self::assertCount(1, $filters);
        self::assertSame('26d@', $filters['email']->value);
        self::assertSame(FilterMode::Like, $filters['email']->mode);
    }

    public function testCompactInListEqAliasAndScalarCast(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '{"userId":{"eq":"ccf92b7a-8e05-4f4b-9f0a-e4360dbacb23"},"rating":{"gt":94},"status":{"in":[1,true]},"deletedAt":{"is-null":true}}',
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::EqualsAlias1, $filters['userId']->mode);
        self::assertSame('ccf92b7a-8e05-4f4b-9f0a-e4360dbacb23', $filters['userId']->value);
        self::assertSame(FilterMode::GreaterThanAlias2, $filters['rating']->mode);
        self::assertSame('94', $filters['rating']->value);
        self::assertSame(FilterMode::In, $filters['status']->mode);
        self::assertSame(['1', '1'], $filters['status']->value);
        self::assertSame(FilterMode::IsNull, $filters['deletedAt']->mode);
        self::assertSame('1', $filters['deletedAt']->value);
    }

    public function testCompactAlreadyDecodedArray(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => [
                'status' => ['in' => ['active']],
            ],
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::In, $filters['status']->mode);
        self::assertSame(['active'], $filters['status']->value);
    }

    /**
     * @dataProvider compactIgnoredProvider
     */
    public function testCompactIgnoredKeepsLegacy(mixed $filtersRaw): void
    {
        $request = Request::create('/clients', 'GET', [
            'filter' => [
                'email' => ['like' => '26d@'],
            ],
            'filters' => $filtersRaw,
        ]);

        $filters = FiltersMaker::make($request)->toArray();

        self::assertCount(1, $filters);
        self::assertSame('email', $filters[0]->property);
        self::assertSame(FilterMode::Like, $filters[0]->mode);
        self::assertSame('26d@', $filters[0]->value);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function compactIgnoredProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace' => ['   '],
            'json null' => ['null'],
            'json list' => ['[]'],
            'json number' => ['123'],
            'json string' => ['"foo"'],
            'non-string scalar' => [42],
        ];
    }

    public function testCompactWhitespaceAroundJsonIsParsed(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '  {"email":{"like":"26d@"}}  ',
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::Like, $filters['email']->mode);
        self::assertSame('26d@', $filters['email']->value);
    }

    public function testFirstModeWinsWhenExpressionHasSeveralModes(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '{"email":{"like":"a@","eq":"b@"}}',
        ]);

        $filters = FiltersMaker::make($request)->toArray();

        self::assertCount(1, $filters);
        self::assertSame(FilterMode::Like, $filters[0]->mode);
        self::assertSame('a@', $filters[0]->value);
    }

    public function testUnknownModeFallsBackToEquals(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '{"email":{"nope":"26d@"}}',
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::Equals, $filters['email']->mode);
        self::assertSame('26d@', $filters['email']->value);
    }

    public function testDottedPropertyAndRangeString(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '{"emails.email":{"like":"26d@"},"rating":{"range":"17,42"}}',
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::Like, $filters['emails.email']->mode);
        self::assertSame('26d@', $filters['emails.email']->value);
        self::assertSame(FilterMode::Range, $filters['rating']->mode);
        self::assertSame('17,42', $filters['rating']->value);
    }

    public function testFalseAndFloatAreCastToString(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '{"flag":{"eq":false},"rating":{"gt":1.5}}',
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::EqualsAlias1, $filters['flag']->mode);
        self::assertSame('0', $filters['flag']->value);
        self::assertSame(FilterMode::GreaterThanAlias2, $filters['rating']->mode);
        self::assertSame('1.5', $filters['rating']->value);
    }

    public function testObjectAndNestedArrayValuesAreSkipped(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '{"email":{"like":{"bad":true}},"status":{"in":[["nested"]]},"name":{"eq":"Tesla"}}',
        ]);

        $filters = $this->byProperty($request);

        self::assertCount(1, $filters);
        self::assertSame(FilterMode::EqualsAlias1, $filters['name']->mode);
        self::assertSame('Tesla', $filters['name']->value);
    }

    public function testLegacySamePropertyWithTwoModesKeepsFirstOnly(): void
    {
        $request = Request::create('/clients?filter[email][like]=a%40&filter[email][eq]=b%40&filter[name][eq]=Tesla');

        $filters = FiltersMaker::make($request)->toArray();

        self::assertCount(2, $filters);
        self::assertSame('email', $filters[0]->property);
        self::assertSame(FilterMode::Like, $filters[0]->mode);
        self::assertSame('a@', $filters[0]->value);
        self::assertSame('name', $filters[1]->property);
        self::assertSame('Tesla', $filters[1]->value);
    }

    public function testAmpersandAndEqualsInsideJsonValueStayIntact(): void
    {
        $request = Request::create('/clients', 'GET', [
            'filters' => '{"email":{"like":"a&b=c"}}',
        ]);

        $filters = $this->byProperty($request);

        self::assertSame(FilterMode::Like, $filters['email']->mode);
        self::assertSame('a&b=c', $filters['email']->value);
    }

    /**
     * @return array<string, Filter>
     */
    private function byProperty(Request $request): array
    {
        $indexed = [];
        foreach (FiltersMaker::make($request)->toArray() as $filter) {
            $indexed[$filter->property] = $filter;
        }

        return $indexed;
    }
}
