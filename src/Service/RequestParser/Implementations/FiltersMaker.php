<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations;

use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;
use IWD\SymfonyDoctrineSearch\Dto\Input\Filters;
use IWD\SymfonyDoctrineSearch\Service\Filter\FilterMode;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\FiltersMakerInterface;
use Symfony\Component\HttpFoundation\Request;

class FiltersMaker implements FiltersMakerInterface
{
    public static function make(Request $request): Filters
    {
        $all = $request->query->all();
        $legacy = self::parseFilterMap($all['filter'] ?? null);
        $compact = self::parseFilterMap(self::decodeCompactFilters($all['filters'] ?? null));

        if ([] === $compact) {
            return new Filters($legacy);
        }

        $compactProperties = [];
        foreach ($compact as $filter) {
            $compactProperties[$filter->property] = true;
        }

        $merged = [];
        foreach ($legacy as $filter) {
            if (isset($compactProperties[$filter->property])) {
                continue;
            }
            $merged[] = $filter;
        }

        return new Filters([...$merged, ...$compact]);
    }

    /**
     * @return Filter[]
     */
    private static function parseFilterMap(mixed $filterRaw): array
    {
        if (!isset($filterRaw) || !is_array($filterRaw)) {
            return [];
        }

        $filters = [];
        /** @var int|string|null $property */
        /** @var array<string, mixed> $filterExpression */
        foreach ($filterRaw as $property => $filterExpression) {
            if (!self::propertyIsValid($property)) {
                continue;
            }
            if (!self::filterExpressionIsValid($filterExpression)) {
                continue;
            }

            foreach ($filterExpression as $modeKey => $rawValue) {
                $value = self::normalizeValue($rawValue);
                if (!self::valueIsValid($value)) {
                    continue;
                }
                $filterMode = FilterMode::tryFrom(mb_strtolower((string) $modeKey)) ?? FilterMode::Equals;
                $filters[] = new Filter(
                    property: (string) $property,
                    mode: $filterMode,
                    value: $value,
                );
            }
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeCompactFilters(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ('' === $trimmed) {
                return null;
            }

            try {
                $decoded = json_decode($trimmed, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }

            return is_array($decoded) ? $decoded : null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        return null;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_int($key)) {
                return $value;
            }
            if (is_bool($item)) {
                $normalized[$key] = $item ? '1' : '0';
                continue;
            }
            if (is_int($item) || is_float($item)) {
                $normalized[$key] = (string) $item;
                continue;
            }
            $normalized[$key] = $item;
        }

        return $normalized;
    }

    private static function valueIsValid(mixed $value): bool
    {
        if (!isset($value)) {
            return false;
        }
        if (!(is_string($value) || is_array($value))) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (!is_int($key)) {
                    return false;
                }
                if (!is_string($val)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function filterExpressionIsValid(mixed $filterExpression): bool
    {
        if (!isset($filterExpression)) {
            return false;
        }
        if (empty($filterExpression)) {
            return false;
        }
        if (!is_array($filterExpression)) {
            return false;
        }

        return true;
    }

    private static function propertyIsValid(mixed $property): bool
    {
        if (!isset($property)) {
            return false;
        }
        if (is_int($property)) {
            return false;
        }

        return true;
    }
}
