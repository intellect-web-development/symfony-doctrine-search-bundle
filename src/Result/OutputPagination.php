<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Result;

use OpenApi\Attributes as OA;

final readonly class OutputPagination
{
    public function __construct(
        #[OA\Property(description: 'Всего найдено', example: 425)]
        public int $count,
        #[OA\Property(description: 'Всего страниц', example: 22)]
        public int $totalPages,
        #[OA\Property(description: 'Номер текущей страницы', example: 1)]
        public int $page,
        #[OA\Property(description: 'Записей на текущей странице', example: 20)]
        public int $size,
    ) {
    }
}
