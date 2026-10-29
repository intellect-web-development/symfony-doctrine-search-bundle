<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use IWD\SymfonyDoctrineSearch\Doctrine\Condition\CriteriaApplier;
use IWD\SymfonyDoctrineSearch\Doctrine\Eager\EagerLoader;
use IWD\SymfonyDoctrineSearch\Exception\InvalidSearchQueryException;
use IWD\SymfonyDoctrineSearch\Query\Pagination;
use IWD\SymfonyDoctrineSearch\Query\SearchQuery;
use IWD\SymfonyDoctrineSearch\Query\Sort;
use IWD\SymfonyDoctrineSearch\Result\OutputPagination;
use IWD\SymfonyDoctrineSearch\Result\SearchResult;
use IWD\SymfonyDoctrineSearch\Search\EntitySearch;
use LogicException;
use Stringable;

/**
 * Количество и страница считаются по одному и тому же условию. С жадной загрузкой страница берётся в два шага:
 * сначала идентификаторы с LIMIT, затем сущности со связями — иначе fetch-join to-many связей ломает LIMIT.
 */
final readonly class DoctrineEntitySearch implements EntitySearch
{
    private const string ROOT = 'root';

    public function __construct(
        private ManagerRegistry $registry,
        private CriteriaApplier $criteria,
        private EagerLoader $eagerLoader,
        private int $defaultPageSize,
        private bool $rejectUnknownProperties,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return SearchResult<T>
     */
    public function search(string $entityClass, SearchQuery $query, bool $eager = true): SearchResult
    {
        $em = $this->registry->getManagerForClass($entityClass);
        if (!$em instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('%s is not managed by Doctrine ORM.', $entityClass));
        }
        $metadata = $em->getClassMetadata($entityClass);
        $idField = SingleIdentifier::fieldOf($metadata);
        $pagination = $query->pagination ?? new Pagination(1, $this->defaultPageSize);

        $base = $em->createQueryBuilder()->from($entityClass, self::ROOT);
        $this->criteria->apply($em, $base, self::ROOT, $metadata, $query->constraints, $query->filters, $query->logic);

        $count = (int) (clone $base)
            ->select(sprintf('COUNT(%s.%s)', self::ROOT, $idField))
            ->getQuery()
            ->getSingleScalarResult();

        $page = (clone $base)
            ->setFirstResult($pagination->offset())
            ->setMaxResults($pagination->size);
        $this->applySorts($page, $metadata, $query->sorts, $idField);

        /** @var list<T> $entities */
        $entities = match (true) {
            $count <= $pagination->offset() => [],
            $eager => $this->hydrate($em, $metadata, $idField, array_values($page->select(self::ROOT . '.' . $idField)->getQuery()->getSingleColumnResult())),
            default => $page->select(self::ROOT)->getQuery()->getResult(),
        };

        return new SearchResult(
            $entities,
            new OutputPagination(
                count: $count,
                totalPages: (int) ceil($count / $pagination->size),
                page: $pagination->number,
                size: count($entities),
            ),
        );
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<Sort>            $sorts
     */
    private function applySorts(QueryBuilder $query, ClassMetadata $metadata, array $sorts, string $idField): void
    {
        $sortedById = false;
        foreach ($sorts as $sort) {
            if (!$metadata->hasField($sort->property)) {
                if ($this->rejectUnknownProperties) {
                    throw InvalidSearchQueryException::unknownSortProperty($sort->property);
                }
                continue;
            }
            $query->addOrderBy(self::ROOT . '.' . $sort->property, $sort->direction->value);
            $sortedById = $sortedById || $sort->property === $idField;
        }

        // Без однозначного порядка одна и та же запись может оказаться на двух страницах.
        if (!$sortedById) {
            $query->addOrderBy(self::ROOT . '.' . $idField, 'ASC');
        }
    }

    /**
     * Загружает сущности со связями и возвращает их в порядке идентификаторов страницы.
     *
     * @param ClassMetadata<object> $metadata
     * @param list<mixed>           $ids      идентификаторы страницы: скаляр из базы или VO, если ORM его сконвертировал
     *
     * @return list<object>
     */
    private function hydrate(EntityManagerInterface $em, ClassMetadata $metadata, string $idField, array $ids): array
    {
        $type = Type::getType((string) $metadata->getTypeOfField($idField));
        $platform = $em->getConnection()->getDatabasePlatform();
        // Собственные типы приложений часто принимают в convertToDatabaseValue только свой VO, поэтому скаляр не трогаем.
        $toDatabase = static fn (mixed $id): mixed => is_object($id) ? $type->convertToDatabaseValue($id, $platform) : $id;
        $key = static function (mixed $id) use ($toDatabase): string {
            $value = $toDatabase($id);

            return is_scalar($value) || $value instanceof Stringable ? (string) $value : throw new LogicException('Identifier cannot be used as a key.');
        };
        $dbIds = array_map($toDatabase, $ids);

        $query = $em->createQueryBuilder()
            ->select(self::ROOT)
            ->from($metadata->getName(), self::ROOT)
            ->where(sprintf('%s.%s IN (:dsb_ids)', self::ROOT, $idField))
            ->setParameter(
                'dsb_ids',
                $dbIds,
                [] === array_filter($dbIds, static fn (mixed $id): bool => !is_int($id)) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING,
            );
        $this->eagerLoader->apply($em, $query, self::ROOT, $metadata);

        /** @var list<object> $entities */
        $entities = $query->getQuery()->getResult();
        $byId = [];
        foreach ($entities as $entity) {
            $byId[$key($metadata->getIdentifierValues($entity)[$idField])] = $entity;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$key($id)])) {
                $ordered[] = $byId[$key($id)];
            }
        }

        return $ordered;
    }
}
