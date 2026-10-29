# Changelog

## 2.0.0

Переписан с нуля. HTTP-контракт (`filter`, `filters`, `sort`, `page`, `strategy`, формат `pagination`) не изменился,
PHP API — изменился полностью; переход описан в [UPGRADE-2.0.md](UPGRADE-2.0.md).

### Безопасность

- Ограничение (раньше — `filters->add()` после `blockByProperties()`) больше не отбрасывается молча, если путь глубже двух
  связей или проходит через сущность с собственным репозиторием. Из-за этого в 1.x поиск мог отдавать записи других
  владельцев. Теперь ограничение либо применяется, либо бросает `UnresolvableConstraintException`.
- Ограничения всегда объединяются через AND с пользовательскими фильтрами: `strategy=or` вместе с фильтром по связи
  больше не расширяет выборку и не завышает `pagination.count`.
- Идентификаторы при загрузке страницы передаются параметром, а не подставляются строкой в DQL.

### Новое

- `Query\SearchQuery` — неизменяемый: `withoutFilters()`, `withConstraint()`, `withLogic()`, `withSort()`, `withPagination()`.
- `Search\EntitySearch` и `Search\EntityReader` вместо `Search\Bus` и `Aggregate\Bus`; ограничения работают и при чтении по id.
- Конфигурация `symfony_doctrine_search` (размеры страниц, глубина связей для клиентских фильтров, реакция на неизвестные
  свойства, глубина eager-загрузки, имена GET-параметров), все значения принимают `%env()%`.
- Фильтры по связям любой глубины и типа (включая to-many) выполняются подзапросом: без дублей и `DISTINCT`, `count`
  совпадает с выдачей.
- Значение, несовместимое с типом столбца, не вызывает ошибку базы данных: условие ложно (для отрицаний — истинно).
- Детерминированный порядок страниц: в конец сортировки добавляется идентификатор.
- `OpenApi\SearchQueryParameters` — базовый класс для документирования параметров поиска.

### Исправлено

- `page[size]=0` приводил к делению на ноль (500).
- `filter[x][range]=значение` без запятой давал предупреждение PHP.
- `strategy=or` вместе с фильтром по связи возвращал меньше записей, чем показывал `count`.

### Удалено

- `Dto\*`, `Service\*`, `ArgumentResolver\*` и статические `*MakerInterface`; синонимы-кейсы `FilterMode` (теперь `Query\FilterOperator::fromName()`).
- `Aggregate\Bus::grantedQuery()`, `getRelationPlan()`, `Filters::applyAlias()`, `hints`.
- Зависимости от `symfony-entry-contract-bundle`, `symfony-bundles/bundle-dependency`, `symfony/validator`, `symfony/serializer`.

Требования: PHP 8.4+, Doctrine ORM 3.3+, DBAL 4, Symfony 7.3 / 8.
