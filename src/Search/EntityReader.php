<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Search;

use IWD\SymfonyDoctrineSearch\Exception\EntityNotFoundException;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use Stringable;

/**
 * Чтение одной сущности по идентификатору с теми же ограничениями, что и у поиска
 * (например, «только если принадлежит текущему владельцу»).
 */
interface EntityReader
{
    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     * @param list<Filter>    $constraints
     *
     * @return T
     *
     * @throws EntityNotFoundException
     */
    public function get(string $entityClass, string|int|Stringable $id, array $constraints = [], bool $eager = false): object;

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     * @param list<Filter>    $constraints
     *
     * @return T|null
     */
    public function find(string $entityClass, string|int|Stringable $id, array $constraints = [], bool $eager = false): ?object;
}
