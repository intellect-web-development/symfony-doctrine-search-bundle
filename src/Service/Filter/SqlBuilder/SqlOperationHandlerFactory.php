<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class SqlOperationHandlerFactory
{
    /**
     * @param iterable<FilterSqlOperationHandlerInterface> $handlers
     */
    public function __construct(
        #[TaggedIterator('iwd.symfony_doctrine_search.sql_operation_handler')]
        private iterable $handlers,
    ) {
    }

    public function resolve(FilterSqlOperation $operation): ?FilterSqlOperationHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($operation)) {
                return $handler;
            }
        }

        return null;
    }
}
