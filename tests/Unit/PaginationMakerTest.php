<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit;

use IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations\PaginationMaker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PaginationMakerTest extends TestCase
{
    public function testLegacyPageBag(): void
    {
        $request = Request::create('/clients', 'GET', [
            'page' => ['number' => '2', 'size' => '50'],
        ]);

        $pagination = PaginationMaker::make($request);

        self::assertSame(2, $pagination->getPageNumber());
        self::assertSame(50, $pagination->getPageSize());
        self::assertSame(50, $pagination->getOffset());
    }

    public function testCompactJsonString(): void
    {
        $request = Request::create('/clients?page=' . rawurlencode('{"number":3,"size":10}'));

        $pagination = PaginationMaker::make($request);

        self::assertSame(3, $pagination->getPageNumber());
        self::assertSame(10, $pagination->getPageSize());
        self::assertSame(20, $pagination->getOffset());
    }

    public function testMissingPageUsesDefaults(): void
    {
        $pagination = PaginationMaker::make(Request::create('/clients'));

        self::assertSame(PaginationMaker::DEFAULT_PAGE, $pagination->getPageNumber());
        self::assertSame(PaginationMaker::DEFAULT_SIZE, $pagination->getPageSize());
    }

    public function testInvalidJsonUsesDefaults(): void
    {
        $request = Request::create('/clients', 'GET', [
            'page' => '{not-json',
        ]);

        $pagination = PaginationMaker::make($request);

        self::assertSame(PaginationMaker::DEFAULT_PAGE, $pagination->getPageNumber());
        self::assertSame(PaginationMaker::DEFAULT_SIZE, $pagination->getPageSize());
    }

    public function testPartialJsonKeepsMissingSideAsDefault(): void
    {
        $request = Request::create('/clients?page=' . rawurlencode('{"number":4}'));

        $pagination = PaginationMaker::make($request);

        self::assertSame(4, $pagination->getPageNumber());
        self::assertSame(PaginationMaker::DEFAULT_SIZE, $pagination->getPageSize());
    }
}
