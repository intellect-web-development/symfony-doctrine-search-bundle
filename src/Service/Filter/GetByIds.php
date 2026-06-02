<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use Doctrine\DBAL\ArrayParameterType;

readonly class GetByIds
{
    public function __construct(
        private RelationLoader $relationLoader,
    ) {
    }

    /**
     * @param array<string> $ids
     *
     * @return array<object>
     */
    public function fetch(FetcherContext $context, array $ids, bool $eager = true, array $hints = []): array
    {
        $aggregateAlias = Fetcher::AGGREGATE_ALIAS;
        $idPropertyName = current($context->entityClassMetadata->identifier);

        if (empty($ids)) {
            return [];
        }

        $context->queryBuilder
            ->andWhere("$aggregateAlias.{$idPropertyName} IN (:ids)")
            ->setParameter('ids', $ids, ArrayParameterType::STRING);

        if ($eager) {
            $this->relationLoader->addEagerQueryToRelations($context, $hints, $context->queryBuilder);
        }

        return $context->queryBuilder->getQuery()->getResult();
    }
}
