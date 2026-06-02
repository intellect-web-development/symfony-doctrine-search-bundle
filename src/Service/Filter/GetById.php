<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter;

use IWD\SymfonyDoctrineSearch\Exception\SymfonyDoctrineSearchException;

readonly class GetById
{
    public function __construct(
        private RelationLoader $relationLoader,
    ) {
    }

    public function fetch(
        FetcherContext $context,
        string $id,
        bool $eager = true,
        array $hints = []
    ): object {
        $aggregateAlias = Fetcher::AGGREGATE_ALIAS;
        $idPropertyName = $context->entityClassMetadata->identifier[0];

        $context->queryBuilder
            ->andWhere("$aggregateAlias.{$idPropertyName} = :id")
            ->setParameter('id', $id);

        if ($eager) {
            $this->relationLoader->addEagerQueryToRelations($context, $hints, $context->queryBuilder);
        }
        $result = $context->queryBuilder->getQuery()->getResult();
        if (empty($result)) {
            throw new SymfonyDoctrineSearchException("Entity with {$idPropertyName} '{$id}' not exist", 400);
        }

        return current($result);
    }
}
