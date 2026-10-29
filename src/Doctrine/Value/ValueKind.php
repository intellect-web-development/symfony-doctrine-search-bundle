<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Value;

use DateTimeImmutable;
use Exception;

/**
 * Какие значения принимает столбец. Нужен, чтобы `filter[id][eq]=abc` по uuid-столбцу
 * давал пустую выборку, а не ошибку базы данных.
 */
enum ValueKind
{
    case Numeric;
    case Boolean;
    case Temporal;
    case Uuid;
    case String;
    case Other;

    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function accepts(string|int|float|bool $value): bool
    {
        return match ($this) {
            self::Numeric => !is_bool($value) && is_numeric($value),
            self::Boolean => null !== self::toBool($value),
            self::Temporal => is_string($value) && self::isDate($value),
            self::Uuid => is_string($value) && 1 === preg_match(self::UUID_PATTERN, $value),
            self::String, self::Other => true,
        };
    }

    public function supportsLike(): bool
    {
        return self::String === $this || self::Other === $this;
    }

    /**
     * Значение в том виде, в каком его передавать параметром запроса. Вызывать только после `accepts()`.
     */
    public function prepare(string|int|float|bool $value): string|int|float|bool
    {
        return self::Boolean === $this ? (bool) self::toBool($value) : $value;
    }

    private static function toBool(string|int|float|bool $value): ?bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private static function isDate(string $value): bool
    {
        if ('' === trim($value)) {
            return false;
        }

        try {
            new DateTimeImmutable($value);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
