<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Описание GET-параметров поиска для OpenAPI (Nelmio `Model`). В рантайме не используется.
 *
 * Чтобы показать в документации свои свойства, унаследуйтесь и переопределите `$filter` с примером:
 *
 *     class QueryParams extends SearchQueryParameters
 *     {
 *         #[OA\Property(property: 'filter', type: 'object', example: ['title' => ['like' => 'foo']])]
 *         public array $filter = [];
 *     }
 */
class SearchQueryParameters
{
    /** @var array<string, array<string, mixed>> */
    #[OA\Property(
        property: 'filter',
        description: 'filter[свойство][оператор]=значение. Операторы: eq, neq, in, not-in, like, not-like, is-null, not-null, lt, lte, gt, gte, range ("от,до").',
        type: 'object',
        example: ['title' => ['like' => 'foo'], 'createdAt' => ['range' => '2024-01-01,2024-12-31']],
    )]
    public array $filter = [];

    #[OA\Property(
        property: 'filters',
        description: 'Те же фильтры одним JSON-объектом; для совпадающих свойств важнее, чем filter.',
        type: 'string',
        example: '{"title":{"like":"foo"}}',
    )]
    public ?string $filters = null;

    #[OA\Property(property: 'sort', description: 'Через запятую, минус — по убыванию; или JSON {"свойство":"asc|desc"}.', type: 'string', example: '-createdAt,title')]
    public ?string $sort = null;

    /** @var array{number?: int, size?: int} */
    #[OA\Property(property: 'page', type: 'object', example: ['number' => 1, 'size' => 20])]
    public array $page = [];

    #[OA\Property(property: 'strategy', description: 'Как объединять фильтры между собой.', type: 'string', enum: ['and', 'or'], example: 'and')]
    public ?string $strategy = null;
}
