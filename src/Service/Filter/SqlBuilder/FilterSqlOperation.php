<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\Filter\SqlBuilder;

enum FilterSqlOperation: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Like = 'like';
    case NotLike = 'not_like';
    case IsNull = 'is_null';
    case NotNull = 'not_null';
    case In = 'in';
    case NotIn = 'not_in';
    case LessThan = 'less_than';
    case GreaterThan = 'greater_than';
    case LessOrEquals = 'less_or_equals';
    case GreaterOrEquals = 'greater_or_equals';
    case Between = 'between';
}
