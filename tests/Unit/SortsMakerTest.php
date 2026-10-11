<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit;

use IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations\SortsMaker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SortsMakerTest extends TestCase
{
    public function testLegacyCommaList(): void
    {
        $request = Request::create('/clients', 'GET', [
            'sort' => '-createdAt,updatedAt',
        ]);

        $sorts = SortsMaker::make($request)->toArray();

        self::assertCount(2, $sorts);
        self::assertSame('createdAt', $sorts[0]->getField());
        self::assertSame('DESC', $sorts[0]->getDirection());
        self::assertSame('updatedAt', $sorts[1]->getField());
        self::assertSame('ASC', $sorts[1]->getDirection());
    }

    public function testCompactJsonKeepsKeyOrder(): void
    {
        $request = Request::create('/clients?sort=' . rawurlencode('{"createdAt":"desc","id":"asc"}'));

        $sorts = SortsMaker::make($request)->toArray();

        self::assertCount(2, $sorts);
        self::assertSame('createdAt', $sorts[0]->getField());
        self::assertSame('DESC', $sorts[0]->getDirection());
        self::assertSame('id', $sorts[1]->getField());
        self::assertSame('ASC', $sorts[1]->getDirection());
    }

    public function testInvalidJsonDoesNotBecomeLegacySort(): void
    {
        $request = Request::create('/clients', 'GET', [
            'sort' => '{not-json',
        ]);

        self::assertSame([], SortsMaker::make($request)->toArray());
    }

    public function testUnknownDirectionIsSkipped(): void
    {
        $request = Request::create('/clients?sort=' . rawurlencode('{"id":"sideways","name":"ASC"}'));

        $sorts = SortsMaker::make($request)->toArray();

        self::assertCount(1, $sorts);
        self::assertSame('name', $sorts[0]->getField());
        self::assertSame('ASC', $sorts[0]->getDirection());
    }

    public function testEmptySortParam(): void
    {
        $request = Request::create('/clients', 'GET', [
            'sort' => '',
        ]);

        self::assertSame([], SortsMaker::make($request)->toArray());
    }
}
