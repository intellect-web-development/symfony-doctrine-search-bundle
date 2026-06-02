<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Unit\Service\RequestParser\Implementations;

use IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations\PaginationMaker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PaginationMakerTest extends TestCase
{
    public function testReturnsDefaultsWhenPageQueryIsMissing(): void
    {
        $request = new Request(query: []);

        $pagination = PaginationMaker::make($request);

        self::assertSame(1, $pagination->getPageNumber());
        self::assertSame(20, $pagination->getPageSize());
        self::assertSame(0, $pagination->getOffset());
    }

    public function testReturnsDefaultsWhenPageQueryIsNotArray(): void
    {
        $request = new Request(query: ['page' => 'invalid']);

        $pagination = PaginationMaker::make($request);

        self::assertSame(1, $pagination->getPageNumber());
        self::assertSame(20, $pagination->getPageSize());
        self::assertSame(0, $pagination->getOffset());
    }

    public function testBuildsPaginationFromQueryArray(): void
    {
        $request = new Request(query: ['page' => ['number' => '3', 'size' => '15']]);

        $pagination = PaginationMaker::make($request);

        self::assertSame(3, $pagination->getPageNumber());
        self::assertSame(15, $pagination->getPageSize());
        self::assertSame(30, $pagination->getOffset());
    }
}
