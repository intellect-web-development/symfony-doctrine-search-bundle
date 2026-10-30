# Переход на symfony-doctrine-search-bundle 2.0

Документ написан как промпт для ИИ-агента: его можно целиком передать агенту, открытому в репозитории сервиса,
с фразой «Переведи этот сервис на doctrine-search-bundle 2.0 по инструкции». Человеку он тоже подходит как чеклист.

---

## Роль и цель

Ты переводишь Symfony-сервис с `intellect-web-development/symfony-doctrine-search-bundle` 1.x на 2.0.
HTTP-контракт с фронтом **не меняется**: параметры `filter`, `filters`, `sort`, `page`, `strategy` и формат ответа
(`data` + `pagination: {count, totalPages, page, size}`) остаются прежними. Меняется только PHP API бандла.

Готово, когда: в `src/` и `tests/` нет ни одного импорта старых классов бандла, тесты сервиса, phpstan/psalm/deptrac
проходят, и на каждый поиск с ограничением владельцем есть тест, который доказывает, что чужие записи не видны.

## Правила работы

- Сначала `git status`. Чужие незакоммиченные изменения не трогай и не откатывай. В репозитории может параллельно работать
  другая сессия: не запускай деструктивных команд, не меняй ветку, не коммить без явной просьбы пользователя.
- Следуй правилам самого репозитория (`CLAUDE.md`, `docs/ai/rules/*`): стиль кода, команды тестов, docker.
- Меняй код механически по таблице ниже. Если место не подходит ни под одно правило — остановись и опиши его пользователю,
  не придумывай.
- Не меняй поведение Action сверх замены API: тексты, OpenAPI-описания, порядок полей ответа остаются как были.

## Шаг 1. Обновить пакет

```bash
composer require intellect-web-development/symfony-doctrine-search-bundle:^2.0
```

Бандл больше не тянет за собой `symfony-entry-contract-bundle`: если сервис его использует (`Presenter`, `ApiFormatter`,
`OutputFormat`), он должен быть в `composer.json` и `config/bundles.php` самого сервиса — проверь.

## Шаг 2. Найти все места

```bash
grep -rln 'IWD\\SymfonyDoctrineSearch' src tests config
```

Удалённые классы (после миграции grep по ним должен быть пустым):
`Dto\Input\*`, `Dto\Output\*`, `Service\QueryBus\*`, `Service\Filter\*`, `Service\RequestParser\*`, `ArgumentResolver\*`.

## Шаг 3. Замены

### Пространства имён

| Было (`IWD\SymfonyDoctrineSearch\…`) | Стало |
|---|---|
| `Dto\Input\SearchQuery` | `Query\SearchQuery` |
| `Dto\Input\Filter` | `Query\Filter` |
| `Service\Filter\FilterMode` | `Query\FilterOperator` |
| `Dto\Input\FilterStrategy` | `Query\Logic` (`And`, `Or`) |
| `Dto\Input\Pagination` | `Query\Pagination` |
| `Dto\Input\Sort` / `Sorts` | `Query\Sort` / обычный `list<Sort>` |
| `Dto\Input\Filters` | обычный `list<Filter>` (см. ниже) |
| `Dto\Output\OutputPagination` | `Result\OutputPagination` |
| `Dto\Output\SearchResult` | `Result\SearchResult` |
| `Service\QueryBus\Search\Bus` + `Query` | `Search\EntitySearch` |
| `Service\QueryBus\Aggregate\Bus` + `Query` | `Search\EntityReader` |
| `Exception\SymfonyDoctrineSearchException` | без изменений |

### `FilterMode` → `FilterOperator`

Синонимы-кейсы удалены, остались канонические:

| Было | Стало |
|---|---|
| `Equals`, `EqualsAlias1`, `EqualsAlias2` | `FilterOperator::Equals` |
| `NotEquals`, `NotEqualsAlias1..3` | `FilterOperator::NotEquals` |
| `LessThan*` / `LessOrEquals*` | `FilterOperator::LessThan` / `LessOrEquals` |
| `GreaterThan*` / `GreaterOrEquals*` | `FilterOperator::GreaterThan` / `GreaterOrEquals` |
| `In`, `NotIn`, `Like`, `NotLike`, `IsNull`, `NotNull`, `Range` | то же имя |

`FilterMode::tryFrom($s)` → `FilterOperator::fromName($s)` (понимает и синонимы). `->value` у `FilterOperator`
каноничный: `eq`, `neq`, `lt`, `lte`, `gt`, `gte`, `in`, `not-in`, `like`, `not-like`, `is-null`, `not-null`, `range`.

`new Filter(...)` принимает enum, дату, Stringable и VO с `__toString` — `->getValue()` / `(string)` больше не обязательны,
но оставлять их можно. Для `Range` значение — пара `[от, до]` вместо строки `"от,до"`.

### Поисковый Action

Было:

```php
public function action(SearchQuery $searchQuery, Bus $bus, ...): Response
{
    $query = new SearchBusQuery(
        targetEntityClass: Item::class,
        pagination: $searchQuery->pagination,
        filters: $searchQuery->filters,
        sorts: $searchQuery->sorts,
        eager: false,            // если было
    );
    $searchResult = $bus->query($query);
```

Стало:

```php
use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use IWD\SymfonyDoctrineSearch\Search\EntitySearch;

public function action(SearchQuery $searchQuery, EntitySearch $search, ...): Response
{
    $searchResult = $search->search(Item::class, $searchQuery, eager: false); // eager по умолчанию true, как раньше
```

`$searchResult->entities` и `$searchResult->pagination` — как раньше; `entities` теперь `list<T>`, поэтому
`array_values($searchResult->entities)` и `/** @var list<Item> */` над ним больше не нужны.

Параметры `hints` и `filterStrategy` у старого `Search\Query` удалены. `filterStrategy` теперь задаётся
`$searchQuery->withLogic(Logic::Or)`; `hints['maxNestedLevel']` — глобально, `eager_load.depth` в конфигурации.

### Ограничение владельцем (самое важное)

Было:

```php
$searchQuery->filters->blockByProperties(['workspace.id', 'workspace.ownerId']);
$searchQuery->filters->add(new Filter('workspace.id', FilterMode::Equals, $workspaceId));
```

Стало — `SearchQuery` неизменяемый, результат присваивается:

```php
$searchQuery = $searchQuery
    ->withoutFilters('workspace.id', 'workspace.ownerId')
    ->withConstraint(new Filter('workspace.id', FilterOperator::Equals, $workspaceId));
```

Правила:
- `filters->add(X)` на серверной стороне — это **всегда** `withConstraint(X)` (даже без `blockByProperties` рядом).
  Пользовательские фильтры объединяются по `strategy` клиента, ограничения — всегда через AND.
- `filters->blockByProperties([...])` без `add` → `withoutFilters(...)`.
- `filters->getByProperty('x')` → `filtersFor('x')`.
- `filters->applyAlias()` / `applyAliases()` / `block()` удалены — если встретились, опиши место пользователю.

### Свой поиск без HTTP (MCP-инструменты, сервисы)

Было:

```php
$filters = new Filters($extraFilters);
$filters->blockByProperties($blocked);
$filters->add(new Filter($ownerProperty, FilterMode::In, $ownerIds));
$result = $bus->query(new Query(
    targetEntityClass: $class,
    pagination: new Pagination($page, $size),
    filters: $filters,
    sorts: new Sorts(),
    eager: false,
));
```

Стало:

```php
$query = (new SearchQuery(filters: $extraFilters, pagination: new Pagination($page, $size)))
    ->withoutFilters(...$blocked)
    ->withConstraint(new Filter($ownerProperty, FilterOperator::In, $ownerIds));
$result = $search->search($class, $query, eager: false);
```

`new SearchQuery()` без пагинации — первая страница размера `pagination.default_size`. `Pagination` теперь бросает
`InvalidArgumentException` на номер/размер меньше 1 — зажимать значения (`max(1, …)`) нужно до конструктора, как и раньше.
Пустой список в ограничении `In` означает «ничего не найдено» — проверки «если список пуст, вернуть пусто» можно оставить.

### Чтение одной сущности (Aggregate Bus)

Было:

```php
$user = $bus->query(new Query($userIdentity->id, User::class));                       // Aggregate\Bus
$item = $bus->query(new Query($id, Item::class, filters: $filters, eager: true));
```

Стало:

```php
use IWD\SymfonyDoctrineSearch\Search\EntityReader;

$user = $reader->get(User::class, $userIdentity->id);
$item = $reader->get(Item::class, $id, [new Filter('owner.id', FilterOperator::Equals, $ownerId)], eager: true);
```

`get()` бросает `EntityNotFoundException` (наследник `SymfonyDoctrineSearchException`, сообщение прежнее),
`find()` возвращает `null`. `/** @var User $user */` больше не нужен. `grantedQuery()` и `getRelationPlan()` удалены:
проверку прав делай voter-ом после `get()`.

### OpenAPI-классы `QueryParams`

Было:

```php
use IWD\SymfonyDoctrineSearch\Dto\Input\Filters;
use IWD\SymfonyDoctrineSearch\Dto\Input\SearchQuery;

class QueryParams extends SearchQuery
{
    #[OA\Property(property: 'filter', type: 'object', example: [...])]
    public Filters $filters;
}
```

Стало (атрибут `#[OA\Property]` с примером не трогай):

```php
use IWD\SymfonyDoctrineSearch\OpenApi\SearchQueryParameters;

class QueryParams extends SearchQueryParameters
{
    #[OA\Property(property: 'filter', type: 'object', example: [...])]
    public array $filter = [];
}
```

### Конфигурация

Если в сервисе были свои реализации `*MakerInterface` или переопределения сервисов бандла в `services.yaml` —
замени их на реализацию `IWD\SymfonyDoctrineSearch\Http\SearchQueryFactory` или настройки
`symfony_doctrine_search` (см. README). Аргументы контроллера типов `Filters`, `Sorts`, `Pagination` больше не
резолвятся — используй `SearchQuery` и его поля.

### Код, который сам строит SQL из DTO бандла (скрининг, отчёты)

Если сервис читает `SearchQuery` и сам собирает из него SQL, правь по таблице:

| Было | Стало |
|---|---|
| `$searchQuery->filters->toArray()` | `$searchQuery->filters` (это `list<Filter>`) |
| `new Filters($list)` в сигнатурах | `list<Filter>` + `@param list<Filter>` |
| `$filter->mode` | `$filter->operator`; кейсов-синонимов нет (`GreaterThanAlias1` и т. п. убери из `switch`) |
| `new Filter($p, $mode, $v, $strategy)` | `new Filter($p, $operator, $v)` — стратегии у фильтра больше нет |
| `FilterStrategy` | `Query\Logic` (`And` / `Or`, значения `and` / `or` те же) |
| `Sorts`, `$sorts->toArray()` | `list<Sort>` |
| `$sort->getField()`, `getDirection()` (строка) | `$sort->property`, `$sort->direction` (`SortDirection`; строка — `->value`) |
| `new Sort($f, 'DESC')` | `new Sort($f, SortDirection::Desc)` |
| `$pagination->getPageNumber()`, `getPageSize()`, `getOffset()` | `->number`, `->size`, `->offset()`; пагинация в `SearchQuery` может быть `null` — задай размер по умолчанию сам |
| `new Pagination(pageNumber:, pageSize:, maxSize:)` как DTO ответа | `Result\OutputPagination(count:, totalPages:, page:, size:)` |
| значение `Range` — строка `"от,до"` | пара `[от, до]`; границы могут быть `null` (открытый диапазон) |
| `$searchQuery->filters = new Filters($rest)` (мутация) | `$searchQuery = $searchQuery->withoutFilters('prop')` |
| `FilterMode::from($str)` / `tryFrom` для значений из БД | `FilterOperator::fromName($str)` — `from()` знает только канонические значения и упадёт на `greater-than`, `equals` и т. п. |

Если раньше при пустом списке прав ответ собирали вручную («нет доступных id — вернуть пустую страницу»), достаточно
`withConstraint(new Filter('x.id', FilterOperator::In, []))`: пустой `In` даёт пустой результат с корректной пагинацией.

## Шаг 4. Поведение, которое изменилось (проверь, а не просто замени)

1. **Ограничения больше не пропускаются молча.** В 1.x фильтр по пути глубже двух связей или через сущность с
   собственным репозиторием тихо отбрасывался — и поиск возвращал **чужие записи**. В 2.0 ограничение, которое нельзя
   разрешить по маппингу, бросает `UnresolvableConstraintException` (500). Если после миграции тест упал с этим
   исключением — путь в ограничении неверный (опечатка, лишний префикс вроде `entity.`): исправь путь по маппингу сущности,
   а не удаляй ограничение.
2. **`strategy=or` больше не ломает ограничения и счётчик.** Раньше OR со связью давал `count`, не совпадающий с
   выдачей, и мог расширить выборку за пределы владельца.
3. Фильтр клиента по связям глубиной до `filters.max_relation_depth` (по умолчанию 2) теперь применяется всегда;
   раньше часть таких фильтров тихо игнорировалась.
4. Несовместимое значение (`abc` для числа, не-uuid для uuid-столбца, `like` по числу) больше не даёт 500 —
   условие просто ложно (для `neq` / `not-in` / `not-like` — истинно).
5. `page[size]=0` больше не даёт 500 — используется размер по умолчанию.
6. В конец сортировки добавляется сортировка по идентификатору — порядок страниц стал детерминированным.

## Шаг 5. Тест на изоляцию владельцев (обязательно)

Для каждого Action/инструмента, где есть `withConstraint` по владельцу, нужен функциональный тест:
фикстура с двумя владельцами (свои и чужие записи по всей цепочке связей), запрос от имени первого,
проверка `id` в `data` **и** `pagination.count`. Дополнительно — тот же запрос с `strategy=or` и клиентским
фильтром по связи, совпадающим с чужой записью. Пример — `tests/Functional/Api/PersonalCabinet/Http/Spreader/OwnerScope`
в репозитории spreader.

## Шаг 6. Проверка

```bash
grep -rn 'SymfonyDoctrineSearch\\\(Dto\|Service\|ArgumentResolver\)' src tests   # пусто
grep -rn 'blockByProperties\|filters->add(\|FilterMode\|QueryBus' src tests       # пусто
```

Затем — сброс кэша Symfony и полный набор проверок сервиса (`make test`, `php-stan`, `psalm`, `deptrac-lint`,
`php-cs-fixer` — по `Makefile` репозитория). Если правила репозитория (`docs/ai/rules/*`, skills) описывают старый API
бандла (`Search Bus`, `Query`, `QueryParams extends SearchQuery`, «сырой `Filters` пакета») — обнови их по этой
инструкции.

В итоговом сообщении пользователю перечисли: сколько файлов изменено, какие места не подошли под правила,
какие ограничения пришлось исправить из-за `UnresolvableConstraintException`, и результаты проверок.
