<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use IWD\SymfonyDoctrineSearch\Doctrine\Condition\CriteriaApplier;
use IWD\SymfonyDoctrineSearch\Doctrine\Eager\EagerLoader;
use IWD\SymfonyDoctrineSearch\Doctrine\Value\ValueKindResolver;
use IWD\SymfonyDoctrineSearch\Exception\EntityNotFoundException;
use IWD\SymfonyDoctrineSearch\Query\Filter;
use IWD\SymfonyDoctrineSearch\Search\EntityReader;
use LogicException;
use Stringable;

final readonly class DoctrineEntityReader implements EntityReader
{
    private const string ROOT = 'root';

    public function __construct(
        private ManagerRegistry $registry,
        private CriteriaApplier $criteria,
        private ValueKindResolver $kinds,
        private EagerLoader $eagerLoader,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     * @param list<Filter>    $constraints
     *
     * @return T
     */
    public function get(string $entityClass, string|int|Stringable $id, array $constraints = [], bool $eager = false): object
    {
        return $this->find($entityClass, $id, $constraints, $eager) ?? throw EntityNotFoundException::create(
            $entityClass,
            SingleIdentifier::fieldOf($this->entityManager($entityClass)->getClassMetadata($entityClass)),
            (string) $id,
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     * @param list<Filter>    $constraints
     *
     * @return T|null
     */
    public function find(string $entityClass, string|int|Stringable $id, array $constraints = [], bool $eager = false): ?object
    {
        $em = $this->entityManager($entityClass);
        $metadata = $em->getClassMetadata($entityClass);
        $idField = SingleIdentifier::fieldOf($metadata);
        $id = $id instanceof Stringable ? (string) $id : $id;

        // Идентификатор не того формата (не uuid для uuid-столбца) не может существовать — в базу не ходим.
        if (!$this->kinds->resolve($em, $metadata, $idField)->accepts($id)) {
            return null;
        }

        $query = $em->createQueryBuilder()
            ->select(self::ROOT)
            ->from($entityClass, self::ROOT)
            ->where(sprintf('%s.%s = :dsb_id', self::ROOT, $idField))
            ->setParameter('dsb_id', $id);
        $this->criteria->apply($em, $query, self::ROOT, $metadata, $constraints);
        if ($eager) {
            $this->eagerLoader->apply($em, $query, self::ROOT, $metadata);
        }

        /** @var T|null $entity */
        $entity = $query->getQuery()->getOneOrNullResult();

        return $entity;
    }

    /**
     * @param class-string $entityClass
     */
    private function entityManager(string $entityClass): EntityManagerInterface
    {
        $em = $this->registry->getManagerForClass($entityClass);
        if (!$em instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('%s is not managed by Doctrine ORM.', $entityClass));
        }

        return $em;
    }
}
