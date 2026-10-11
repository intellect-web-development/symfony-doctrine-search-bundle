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
