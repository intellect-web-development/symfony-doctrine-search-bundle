<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use DateTime;
use IWD\SymfonyDoctrineSearch\Dto\Input\Filter;

class DefaultFilterHandler implements CompositeConditionFilterHandlerInterface
{
    public function supports(Filter $filter): bool
    {
        return in_array($filter->mode, FilterMode::cases(), true);
    }

    public function apply(
        Filter $filter,
        FilterSqlBuilder $appSqlBuilder,
        string $fieldPrefix,
        bool $isRelation
    ): void {
        $condition = $this->buildCondition($filter, $appSqlBuilder, $fieldPrefix, $isRelation);
        if (null === $condition) {
            return;
        }

        $appSqlBuilder->applyCondition($condition, $filter->strategy);
    }

    public function buildCondition(
        Filter $filter,
        FilterSqlBuilder $appSqlBuilder,
        string $fieldPrefix,
        bool $isRelation
    ): ?string {
        $aliasPath = $isRelation
            ? Helper::makeAliasPathFromPropertyPath("$fieldPrefix.$filter->property")
            : "$fieldPrefix.$filter->property";

        $value = $filter->value;

        switch ($filter->mode) {
            case FilterMode::NotIn:
                $values = $this->normalizeArrayValue($value);
                if (null !== $values) {
                    return $appSqlBuilder->notInBindAndReturnCondition($aliasPath, $values);
                }
                return null;
            case FilterMode::In:
                $values = $this->normalizeArrayValue($value);
                if (null !== $values) {
                    return $appSqlBuilder->inBindAndReturnCondition($aliasPath, $values);
                }
                return null;
            case FilterMode::Range:
                if (is_string($value)) {
                    return $this->rangeDecoratorCondition($appSqlBuilder, $value, $aliasPath);
                }
                return null;
            case FilterMode::IsNull:
                return $appSqlBuilder->isNullBindAndReturnCondition($aliasPath);
            case FilterMode::NotNull:
                return $appSqlBuilder->notNullBindAndReturnCondition($aliasPath);
            case FilterMode::LessThan:
            case FilterMode::LessThanAlias1:
            case FilterMode::LessThanAlias2:
                return null !== $value ? $appSqlBuilder->lessThanBindAndReturnCondition($aliasPath, $value) : null;
            case FilterMode::GreaterThan:
            case FilterMode::GreaterThanAlias1:
            case FilterMode::GreaterThanAlias2:
                return null !== $value ? $appSqlBuilder->greaterThanBindAndReturnCondition($aliasPath, $value) : null;
            case FilterMode::LessOrEquals:
            case FilterMode::LessOrEqualsAlias1:
            case FilterMode::LessOrEqualsAlias2:
                return null !== $value ? $appSqlBuilder->lessOrEqualsBindAndReturnCondition($aliasPath, $value) : null;
            case FilterMode::GreaterOrEquals:
            case FilterMode::GreaterOrEqualsAlias1:
            case FilterMode::GreaterOrEqualsAlias2:
                return null !== $value ? $appSqlBuilder->greaterOrEqualsBindAndReturnCondition($aliasPath, $value) : null;
            case FilterMode::Like:
                return !empty($value) ? $appSqlBuilder->likeBindAndReturnCondition($aliasPath, $value) : null;
            case FilterMode::NotLike:
                return !empty($value) ? $appSqlBuilder->notLikeBindAndReturnCondition($aliasPath, $value) : null;
            case FilterMode::Equals:
            case FilterMode::EqualsAlias1:
            case FilterMode::EqualsAlias2:
                return null !== $value ? $appSqlBuilder->equalsBindAndReturnCondition($aliasPath, $value) : null;
            case FilterMode::NotEquals:
            case FilterMode::NotEqualsAlias1:
            case FilterMode::NotEqualsAlias2:
            case FilterMode::NotEqualsAlias3:
                return null !== $value ? $appSqlBuilder->notEqualsBindAndReturnCondition($aliasPath, $value) : null;
        }

        return null;
    }

    /**
     * @return array<mixed>|null
     */
    private function normalizeArrayValue(mixed $value): ?array
    {
        if (!isset($value)) {
            return null;
        }

        if (!is_array($value)) {
            return [$value];
        }

        return $value;
    }

    private function rangeDecoratorCondition(
        FilterSqlBuilder $appSqlBuilder,
        string $value,
        string $field
    ): ?string {
        $parts = explode(',', $value, 2);
        if (2 !== count($parts)) {
            return null;
        }

        [$gte, $lte] = $parts;

        if ($this->isDateTime($gte) && $this->isDateTime($lte)) {
            return $appSqlBuilder->rangeDateTimeBindAndReturnCondition($field, new DateTime($gte), new DateTime($lte));
        }

        return $appSqlBuilder->rangeBindAndReturnCondition($field, $gte, $lte);
    }

    private function isDateTime(mixed $date): bool
    {
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            $d = DateTime::createFromFormat($format, (string) $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }

        return false;
    }
}
