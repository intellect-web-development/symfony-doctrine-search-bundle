<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Dto\Input;

class Filters
{
    /** @var Filter[] */
    protected array $filters;

    /** @var Filter[] */
    protected array $blocked;

    /**
     * Filters constructor.
     *
     * @param Filter[] $filters
     */
    public function __construct(array $filters = [])
    {
        foreach ($filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new \TypeError('Variable is not ' . Filter::class);
            }
        }
        $this->filters = $filters;
    }

    public function add(Filter $filter): void
    {
        $this->filters[] = $filter;
    }

    public function applyAlias(string $origin, string $alias): void
    {
        foreach ($this->filters as $filter) {
            if (mb_strtolower($origin) === mb_strtolower($filter->property)) {
                $filter->property = $alias;
            }
        }
    }

    public function blockByProperties(array $properties): void
    {
        $properties = array_map(static function (string $property) {
            return mb_strtolower($property);
        }, $properties);

        foreach ($this->filters as $key => $filter) {
            if (in_array(mb_strtolower($filter->property), $properties, true)) {
                $this->blocked[] = $filter;
                unset($this->filters[$key]);
            }
        }
    }

    public function block(Filter $blockedFilter): void
    {
        foreach ($this->filters as $key => $filter) {
            if ($blockedFilter === $filter) {
                $this->blocked[] = $filter;
                unset($this->filters[$key]);
            }
        }
    }

    /**
     * @param string $propertyName
     * @return Filter[]
     */
    public function getByProperty(string $propertyName): array
    {
        $result = [];

        foreach ($this->filters as $filter) {
            if ($propertyName === $filter->property) {
                $result[] = $filter;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $aliases
     */
    public function applyAliases(array $aliases): void
    {
        foreach ($aliases as $origin => $alias) {
            $this->applyAlias($origin, $alias);
        }
    }

    /**
     * @return Filter[]
     */
    public function toArray(): array
    {
        return $this->filters;
    }
}
