<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Query;

enum FilterOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case In = 'in';
    case NotIn = 'not-in';
    case Like = 'like';
    case NotLike = 'not-like';
    case IsNull = 'is-null';
    case NotNull = 'not-null';
    case LessThan = 'lt';
    case LessOrEquals = 'lte';
    case GreaterThan = 'gt';
    case GreaterOrEquals = 'gte';
    case Range = 'range';

    private const array ALIASES = [
        'equals' => self::Equals,
        '=' => self::Equals,
        'not-equals' => self::NotEquals,
        '!=' => self::NotEquals,
        '<>' => self::NotEquals,
        'less-than' => self::LessThan,
        '<' => self::LessThan,
        'less-or-equals' => self::LessOrEquals,
        '<=' => self::LessOrEquals,
        'greater-than' => self::GreaterThan,
        '>' => self::GreaterThan,
        'greater-or-equals' => self::GreaterOrEquals,
        '>=' => self::GreaterOrEquals,
    ];

    /**
     * Канонический код или любой из его синонимов (`equals`, `=`, `gte`, `>=`...), без учёта регистра.
     */
    public static function fromName(string $name): ?self
    {
        $name = mb_strtolower(trim($name));

        return self::tryFrom($name) ?? self::ALIASES[$name] ?? null;
    }

    /**
     * Оператор с отрицанием: несовместимое со столбцом значение делает его условие тождественно истинным.
     */
    public function isNegation(): bool
    {
        return in_array($this, [self::NotEquals, self::NotIn, self::NotLike], true);
    }

    public function expectsList(): bool
    {
        return self::In === $this || self::NotIn === $this;
    }

    public function expectsValue(): bool
    {
        return self::IsNull !== $this && self::NotNull !== $this;
    }
}
