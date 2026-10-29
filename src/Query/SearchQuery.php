<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Query;

/**
 * Что искать: пользовательские фильтры, ограничения, сортировки и страница.
 *
 * Итоговое условие: `(constraint₁ AND constraint₂ ...) AND (filter₁ <logic> filter₂ ...)`.
 * Фильтры приходят от клиента: неизвестные свойства и несовместимые значения не ломают запрос.
 * Ограничения задаёт сервер (например, «только записи текущего владельца»): они применяются всегда,
 * а ограничение, которое нельзя применить, приводит к исключению, а не к его молчаливому пропуску.
 */
final readonly class SearchQuery
{
    /**
     * @param list<Filter>    $filters
     * @param list<Sort>      $sorts
     * @param Pagination|null $pagination  null — первая страница с размером по умолчанию из конфигурации бандла
     * @param list<Filter>    $constraints
     */
    public function __construct(
        public array $filters = [],
        public Logic $logic = Logic::And,
        public array $sorts = [],
        public ?Pagination $pagination = null,
        public array $constraints = [],
    ) {
    }

    public function withFilter(Filter ...$filters): self
    {
        return $this->with(filters: [...$this->filters, ...array_values($filters)]);
    }

    /**
     * Убирает пользовательские фильтры по свойствам (без учёта регистра). Обычно перед `withConstraint()` на те же свойства.
     */
    public function withoutFilters(string ...$properties): self
    {
        $blocked = array_map(mb_strtolower(...), $properties);

        return $this->with(filters: array_values(array_filter(
            $this->filters,
            static fn (Filter $filter): bool => !in_array(mb_strtolower($filter->property), $blocked, true),
        )));
    }

    public function withConstraint(Filter ...$constraints): self
    {
        return $this->with(constraints: [...$this->constraints, ...array_values($constraints)]);
    }

    public function withLogic(Logic $logic): self
    {
        return $this->with(logic: $logic);
    }

    public function withSort(Sort ...$sorts): self
    {
        return $this->with(sorts: array_values($sorts));
    }

    public function withPagination(?Pagination $pagination): self
    {
        return new self($this->filters, $this->logic, $this->sorts, $pagination, $this->constraints);
    }

    /**
     * @return list<Filter>
     */
    public function filtersFor(string $property): array
    {
        return array_values(array_filter($this->filters, static fn (Filter $filter): bool => $filter->property === $property));
    }

    /**
     * @param list<Filter>|null $filters
     * @param list<Sort>|null   $sorts
     * @param list<Filter>|null $constraints
     */
    private function with(?array $filters = null, ?Logic $logic = null, ?array $sorts = null, ?array $constraints = null): self
    {
        return new self(
            $filters ?? $this->filters,
            $logic ?? $this->logic,
            $sorts ?? $this->sorts,
            $this->pagination,
            $constraints ?? $this->constraints,
        );
    }
}
