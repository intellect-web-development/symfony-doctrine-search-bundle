<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Exception;

use LogicException;

/**
 * Ограничение ссылается на путь, которого нет в маппинге. Это ошибка в коде приложения, а не в запросе:
 * молча пропустить такое ограничение значит отдать лишние данные, поэтому запрос прерывается.
 */
final class UnresolvableConstraintException extends LogicException
{
    /**
     * @param class-string $entityClass
     */
    public static function create(string $entityClass, string $property): self
    {
        return new self(sprintf('Constraint property "%s" cannot be resolved on %s.', $property, $entityClass));
    }
}
