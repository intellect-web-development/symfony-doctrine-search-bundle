<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Exception;

use DomainException;

/**
 * Ошибка, вызванная данными клиента: её текст можно показать пользователю (приложения отвечают 4xx).
 */
class SymfonyDoctrineSearchException extends DomainException
{
}
