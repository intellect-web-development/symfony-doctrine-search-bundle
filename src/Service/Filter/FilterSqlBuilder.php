<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use IWD\SymfonyDoctrineSearch\Dto\Input\FilterStrategy;
use IWD\SymfonyDoctrineSearch\Dto\Input\Pagination;
use IWD\SymfonyDoctrineSearch\Dto\Input\Sort;
use IWD\SymfonyDoctrineSearch\Dto\Input\Sorts;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\FilterSqlOperation;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler\ArrayOperationHandler;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler\BetweenOperationHandler;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler\CompareOperationHandler;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler\EqualityOperationHandler;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler\LikeOperationHandler;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\Handler\NullOperationHandler;
use IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder\SqlOperationHandlerFactory;

class FilterSqlBuilder
{
    public QueryBuilder $queryBuilder;
    private string $alias;
    private int $bindCounter = 0;
    private SqlOperationHandlerFactory $operationHandlerFactory;

    public function __construct(QueryBuilder $queryBuilder, ?SqlOperationHandlerFactory $operationHandlerFactory = null)
    {
        $this->queryBuilder = $queryBuilder;
        $this->operationHandlerFactory = $operationHandlerFactory ?? new SqlOperationHandlerFactory([
            new EqualityOperationHandler(),
            new LikeOperationHandler(),
            new NullOperationHandler(),
            new ArrayOperationHandler(),
            new CompareOperationHandler(),
            new BetweenOperationHandler(),
        ]);
        $aliases = $this->queryBuilder->getAllAliases();
        if (0 !== count($aliases)) {
            $this->alias = current($aliases);
        } else {
            $this->alias = 'entity';
        }
    }

    public function addSorts(Sorts $sorts): self
    {
        foreach ($sorts->toArray() as $sort) {
            $this->addSort($sort);
        }

        return $this;
    }

    public function addSort(?Sort $sort): self
    {
        if ($sort) {
            $this->queryBuilder->addOrderBy("{$this->alias}.{$sort->getField()}", $sort->getDirection());
        }

        return $this;
    }

    public function setPagination(Pagination $pagination): self
    {
        $this->queryBuilder
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getPageSize())
        ;

        return $this;
    }

    public function applyCondition(string $condition, FilterStrategy $filterStrategy): self
    {
        if (FilterStrategy::And === $filterStrategy) {
            $this->queryBuilder->andWhere($condition);
        }
        if (FilterStrategy::Or === $filterStrategy) {
            $this->queryBuilder->orWhere($condition);
        }

        return $this;
    }

    public function equalsBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::Equals, $field, $value);
    }

    public function equals(string $field, mixed $value, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (null !== $value) {
            $this->applyCondition(
                condition: $this->equalsBindAndReturnCondition($field, $value),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function notEquals(string $field, mixed $value, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (null !== $value) {
            $this->applyCondition(
                condition: $this->notEqualsBindAndReturnCondition($field, $value),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function notEqualsBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::NotEquals, $field, $value);
    }

    public function likeBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::Like, $field, $value);
    }

    public function like(string $field, mixed $value, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (!empty($value)) {
            $this->applyCondition(
                condition: $this->likeBindAndReturnCondition($field, $value),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function isNullBindAndReturnCondition(string $field): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::IsNull, $field);
    }

    public function isNull(string $field, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        $this->applyCondition(
            condition: $this->isNullBindAndReturnCondition($field),
            filterStrategy: $filterStrategy
        );

        return $this;
    }

    public function notNullBindAndReturnCondition(string $field): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::NotNull, $field);
    }

    public function notNull(string $field, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        $this->applyCondition(
            condition: $this->notNullBindAndReturnCondition($field),
            filterStrategy: $filterStrategy
        );

        return $this;
    }

    public function notLikeBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::NotLike, $field, $value);
    }

    public function notLike(string $field, mixed $value, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (!empty($value)) {
            $this->applyCondition(
                condition: $this->notLikeBindAndReturnCondition($field, $value),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function inBindAndReturnCondition(string $field, array $values): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::In, $field, $values);
    }

    public function in(string $field, ?array $values, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (!empty($values)) {
            $this->applyCondition(
                condition: $this->inBindAndReturnCondition($field, $values),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function notInBindAndReturnCondition(string $field, array $values): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::NotIn, $field, $values);
    }

    public function notIn(string $field, ?array $values, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (!empty($values)) {
            $this->applyCondition(
                condition: $this->notInBindAndReturnCondition($field, $values),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function lessThanBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::LessThan, $field, $value);
    }

    public function lessThan(string $field, mixed $lte, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (null !== $lte) {
            $this->applyCondition(
                condition: $this->lessThanBindAndReturnCondition($field, $lte),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function greaterThanBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::GreaterThan, $field, $value);
    }

    public function greaterThan(string $field, mixed $gte, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (null !== $gte) {
            $this->applyCondition(
                condition: $this->greaterThanBindAndReturnCondition($field, $gte),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function lessOrEqualsBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::LessOrEquals, $field, $value);
    }

    public function lessOrEquals(string $field, mixed $lte, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (null !== $lte) {
            $this->applyCondition(
                condition: $this->lessOrEqualsBindAndReturnCondition($field, $lte),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function greaterOrEqualsBindAndReturnCondition(string $field, mixed $value): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::GreaterOrEquals, $field, $value);
    }

    public function greaterOrEquals(string $field, mixed $gte, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        if (null !== $gte) {
            $this->applyCondition(
                condition: $this->greaterOrEqualsBindAndReturnCondition($field, $gte),
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function betweenThanBindAndReturnCondition(string $field, mixed $gte, mixed $lte): string
    {
        return (string) $this->buildConditionByOperation(FilterSqlOperation::Between, $field, $gte, $lte);
    }

    public function rangeBindAndReturnCondition(string $field, mixed $gte, mixed $lte): ?string
    {
        if (null !== $gte && null !== $lte) {
            return $this->betweenThanBindAndReturnCondition($field, $gte, $lte);
        }
        if (null !== $gte) {
            return $this->greaterOrEqualsBindAndReturnCondition($field, $gte);
        }
        if (null !== $lte) {
            return $this->lessOrEqualsBindAndReturnCondition($field, $lte);
        }

        return null;
    }

    public function rangeDateTimeBindAndReturnCondition(string $field, ?DateTimeInterface $gte, ?DateTimeInterface $lte): ?string
    {
        return $this->rangeBindAndReturnCondition($field, $gte?->format('Y-m-d H:i:s'), $lte?->format('Y-m-d H:i:s'));
    }

    public function range(string $field, mixed $gte, mixed $lte, FilterStrategy $filterStrategy = FilterStrategy::And): self
    {
        $condition = $this->rangeBindAndReturnCondition($field, $gte, $lte);
        if (null !== $condition) {
            $this->applyCondition(
                condition: $condition,
                filterStrategy: $filterStrategy
            );
        }

        return $this;
    }

    public function rangeDateTime(
        string $field,
        ?DateTimeInterface $gte,
        ?DateTimeInterface $lte,
        FilterStrategy $filterStrategy = FilterStrategy::And
    ): self {
        $this->range(
            $field,
            $gte?->format('Y-m-d H:i:s'),
            $lte?->format('Y-m-d H:i:s'),
            $filterStrategy,
        );

        return $this;
    }

    public function bindValue(mixed $value): string
    {
        ++$this->bindCounter;
        $bind = 'bind_' . $this->bindCounter;

        if (is_array($value)) {
            $this->queryBuilder->setParameter($bind, $value, ArrayParameterType::STRING);
        } else {
            $this->queryBuilder->setParameter($bind, $value);
        }

        return $bind;
    }

    public function normalizeScalar(mixed $value): mixed
    {
        if ('true' === $value) {
            $value = true;
        }
        if ('false' === $value) {
            $value = false;
        }

        return is_bool($value) ? (int) $value : $value;
    }

    private function buildConditionByOperation(FilterSqlOperation $operation, string $field, mixed ...$values): ?string
    {
        $handler = $this->operationHandlerFactory->resolve($operation);

        return $handler?->buildCondition($this, $operation, $field, ...$values);
    }

    public function getAlias(): string
    {
        return $this->alias;
    }
}
