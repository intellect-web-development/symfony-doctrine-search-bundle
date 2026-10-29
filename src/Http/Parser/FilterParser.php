<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Http\Parser;

use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Query\FilterOperator;
use JsonException;

/**
 * Разбирает `filter[свойство][оператор]=значение` и JSON-вариант `filters`. Битый ввод пропускается, а не роняет запрос:
 * неизвестный оператор считается равенством, значение не той формы отбрасывается.
 *
 * @internal
 */
final readonly class FilterParser
{
    /**
     * @return list<Filter>
     */
    public function parse(mixed $legacy, mixed $compact): array
    {
        $legacyFilters = $this->parseMap($legacy);
        $compactFilters = $this->parseMap($this->decode($compact));
        if ([] === $compactFilters) {
            return $legacyFilters;
        }

        // Для свойства, заданного в JSON, фильтры из `filter[...]` игнорируются целиком.
        $overridden = array_flip(array_map(static fn (Filter $filter): string => $filter->property, $compactFilters));

        return [
            ...array_values(array_filter($legacyFilters, static fn (Filter $filter): bool => !isset($overridden[$filter->property]))),
            ...$compactFilters,
        ];
    }

    /**
     * @return list<Filter>
     */
    private function parseMap(mixed $map): array
    {
        if (!is_array($map)) {
            return [];
        }

        $filters = [];
        foreach ($map as $property => $expression) {
            if (!is_string($property) || '' === trim($property) || !is_array($expression)) {
                continue;
            }
            foreach ($expression as $operatorName => $rawValue) {
                $operator = FilterOperator::fromName((string) $operatorName) ?? FilterOperator::Equals;
                $value = $this->value($operator, $this->normalize($rawValue));
                if (null !== $value || !$operator->expectsValue()) {
                    $filters[] = new Filter($property, $operator, $value);
                }
            }
        }

        return $filters;
    }

    /**
     * Значение в форме, которую ждёт оператор, или null, если привести нельзя.
     *
     * @param string|list<string>|null $value
     *
     * @return string|list<string>|array{0: ?string, 1: ?string}|null
     */
    private function value(FilterOperator $operator, string|array|null $value): string|array|null
    {
        if (null === $value) {
            return null;
        }

        return match (true) {
            !$operator->expectsValue() => null,
            $operator->expectsList() => is_array($value) ? $value : [$value],
            FilterOperator::Range === $operator => $this->range($value),
            default => is_string($value) ? $value : null,
        };
    }

    /**
     * `"from,to"`, `"from"` (только нижняя граница), `",to"` или `[from, to]`.
     *
     * @param string|list<string> $value
     *
     * @return array{0: ?string, 1: ?string}|null
     */
    private function range(string|array $value): ?array
    {
        $parts = is_string($value) ? explode(',', $value, 2) : $value;
        if (count($parts) > 2) {
            return null;
        }
        $from = trim($parts[0] ?? '');
        $to = trim($parts[1] ?? '');
        if ('' === $from && '' === $to) {
            return null;
        }

        return ['' === $from ? null : $from, '' === $to ? null : $to];
    }

    /**
     * Типизированные значения из JSON приводятся к строкам, как будто пришли из query string.
     *
     * @return string|list<string>|null
     */
    private function normalize(mixed $value): string|array|null
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }

        $items = [];
        foreach ($value as $item) {
            $item = $this->normalize($item);
            if (!is_string($item)) {
                return null;
            }
            $items[] = $item;
        }

        return $items;
    }

    private function decode(mixed $compact): mixed
    {
        if (!is_string($compact)) {
            return $compact;
        }
        if ('' === trim($compact)) {
            return null;
        }

        try {
            return json_decode($compact, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
}
