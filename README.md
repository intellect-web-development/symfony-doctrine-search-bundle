# Symfony Doctrine Search Bundle

Поиск по Doctrine-сущностям через HTTP API: фильтры, ограничения доступа, сортировка, пагинация и чтение одной сущности.

Требования: PHP 8.4+, Doctrine ORM 3.3+, DBAL 4, Symfony 7.3 / 8.

## Установка

```bash
composer require intellect-web-development/symfony-doctrine-search-bundle
```

```php
// config/bundles.php
IWD\SymfonyDoctrineSearch\SymfonyDoctrineSearchBundle::class => ['all' => true],
```

## Быстрый старт

```php
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\FilterOperator;
use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use IWD\SymfonyDoctrineSearch\Search\EntitySearch;

#[Route('/api/books.{_format}', methods: ['GET'])]
public function action(SearchQuery $searchQuery, EntitySearch $search, CurrentUser $user): Response
{
    // SearchQuery собирается из GET-параметров. Ограничение владельцем задаёт сервер:
    // клиентский фильтр по тому же свойству убираем, ограничение применяется всегда.
    $searchQuery = $searchQuery
        ->withoutFilters('owner.id')
        ->withConstraint(new Filter('owner.id', FilterOperator::Equals, $user->id));

    $result = $search->search(Book::class, $searchQuery);

    return $this->json([
        'data' => array_map(static fn (Book $book) => new BookOutput($book), $result->entities),
        'pagination' => $result->pagination, // {count, totalPages, page, size}
    ]);
}
```

Одна сущность по идентификатору — с теми же ограничениями:

```php
use IWD\SymfonyDoctrineSearch\Search\EntityReader;

$book = $reader->get(Book::class, $id, [new Filter('owner.id', FilterOperator::Equals, $user->id)]);
// не найдена или чужая → EntityNotFoundException; find() вместо get() вернёт null
```

## Фильтры и ограничения

Итоговое условие запроса:

```
(ограничение₁ AND ограничение₂ …) AND (фильтр₁ <strategy> фильтр₂ …)
```

| | Фильтры (`filters`) | Ограничения (`constraints`) |
|---|---|---|
| Кто задаёт | клиент (GET-параметры) | сервер (`withConstraint()`, `EntityReader`) |
| Объединение | `strategy=and` (по умолчанию) или `or` | всегда AND, на них `strategy` не влияет |
| Неизвестное свойство | пропускается (или `InvalidSearchQueryException`, см. `reject_unknown_properties`) | `UnresolvableConstraintException` — запрос не выполняется |
| Глубина связей | не больше `filters.max_relation_depth` | без ограничения |
| Пустой список в `in` | фильтр не задан | ничего не найдено |

Свойство — путь через связи Doctrine: `title`, `author.name`, `author.publisher.country`. Поддерживаются связи
любого типа (в т.ч. to-many) и поля embeddable (`price.amount`). Если условию нужны связи, оно выполняется подзапросом
`root.id IN (…)`: записи не дублируются, `count` и страницы считаются по одному и тому же набору.

Значение, которое столбец не может принять (`abc` для числа, не-uuid для uuid-столбца, `like` по числу),
не приводит к ошибке базы: такое условие ложно, а для операторов с отрицанием (`neq`, `not-in`, `not-like`) — истинно.

## HTTP-параметры

```http request
GET /api/books?filter[title][like]=war&filter[author.name][in][]=Tolstoy&sort=-publishedAt,title&page[number]=1&page[size]=20
```

### Фильтр: `filter[свойство][оператор]=значение`

| Оператор | Синонимы | Значение | Пример |
|---|---|---|---|
| `eq` | `equals`, `=` | скаляр | `filter[status][eq]=active` |
| `neq` | `not-equals`, `!=`, `<>` | скаляр | `filter[status][neq]=blocked` |
| `in` | | список или скаляр | `filter[status][in][]=active&filter[status][in][]=new` |
| `not-in` | | список или скаляр | `filter[status][not-in][]=blocked` |
| `like` | | подстрока, без учёта регистра; `%` и `_` ищутся буквально | `filter[title][like]=war` |
| `not-like` | | то же | `filter[title][not-like]=draft` |
| `is-null` / `not-null` | | не используется | `filter[deletedAt][is-null]=1` |
| `lt` / `lte` | `less-than`, `<` / `less-or-equals`, `<=` | скаляр | `filter[pages][lt]=100` |
| `gt` / `gte` | `greater-than`, `>` / `greater-or-equals`, `>=` | скаляр | `filter[pages][gte]=100` |
| `range` | | `от,до`; любую границу можно опустить | `filter[createdAt][range]=2024-01-01,2024-12-31` |

Неизвестный оператор считается `eq`. Несколько операторов на одно свойство объединяются так же, как остальные фильтры.

Компактная форма — параметр `filters` с JSON; значения могут быть типизированными. Для свойства, указанного в `filters`,
фильтры из `filter[...]` игнорируются:

```
filters={"title":{"like":"war"},"pages":{"gte":100},"available":{"eq":true}}
```

### Объединение фильтров: `strategy=and|or`

По умолчанию `and`. При `or` подходит запись, удовлетворяющая хотя бы одному фильтру (и всем ограничениям).

### Сортировка: `sort`

`sort=-createdAt,title` — минус означает DESC. Или JSON: `sort={"createdAt":"desc","title":"asc"}`.
Сортировать можно по полям самой сущности. Последним всегда добавляется сортировка по идентификатору, чтобы
порядок страниц был однозначным.

### Пагинация: `page`

`page[number]=2&page[size]=20` или `page={"number":2,"size":20}`. Номер меньше 1 — первая страница,
размер меньше 1 — размер по умолчанию, больше максимума — урезается до максимума.

## Конфигурация

Все значения необязательны и принимают `%env()%`:

```yaml
# config/packages/symfony_doctrine_search.yaml
symfony_doctrine_search:
    pagination:
        default_size: 20                 # размер страницы, если клиент его не передал
        max_size: '%env(int:SEARCH_MAX_PAGE_SIZE)%'  # по умолчанию 5000
    filters:
        max_relation_depth: 2            # через сколько связей клиент может фильтровать
        reject_unknown_properties: false # true — фильтр/сортировка по неизвестному свойству даёт ошибку
    eager_load:
        depth: 1                         # сколько уровней связей подгружать при eager
    query_parameters:                    # имена GET-параметров
        filter: filter
        compact_filter: filters
        sort: sort
        page: page
        logic: strategy
```

## Расширение

| Что заменить | Как |
|---|---|
| Формат GET-параметров | своя реализация `IWD\SymfonyDoctrineSearch\Http\SearchQueryFactory` + alias в `services.yaml` |
| Источник данных / хранилище | свои реализации `Search\EntitySearch` и `Search\EntityReader` |

```yaml
# config/services.yaml
IWD\SymfonyDoctrineSearch\Http\SearchQueryFactory: '@App\Search\MySearchQueryFactory'
```

## Ошибки

| Исключение | Когда | Что делать |
|---|---|---|
| `Exception\SymfonyDoctrineSearchException` | базовый класс ошибок, вызванных данными клиента | отвечать 4xx |
| `Exception\EntityNotFoundException` | `EntityReader::get()` не нашёл сущность | наследник базового |
| `Exception\InvalidSearchQueryException` | неизвестное свойство при `reject_unknown_properties: true` | наследник базового |
| `Exception\UnresolvableConstraintException` | ограничение ссылается на несуществующий путь | ошибка в коде, `LogicException` → 500 |

## OpenAPI

Для документирования параметров унаследуйтесь от `OpenApi\SearchQueryParameters` и переопределите пример `filter`:

```php
class QueryParams extends SearchQueryParameters
{
    #[OA\Property(property: 'filter', type: 'object', example: ['title' => ['like' => 'war']])]
    public array $filter = [];
}
```

`Result\OutputPagination` уже размечен атрибутами OpenAPI.

## Разработка

```bash
make build composer-install
make test      # PHPUnit: unit + интеграционные тесты на SQLite
make analyze   # phpstan (level max) + php-cs-fixer
```

Переход с 1.x — [UPGRADE-2.0.md](UPGRADE-2.0.md).
