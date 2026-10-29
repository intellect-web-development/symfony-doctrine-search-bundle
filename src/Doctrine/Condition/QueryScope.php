<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Condition;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use IWD\SymfonyDoctrineSearch\Doctrine\Path\ResolvedProperty;

/**
 * Запрос, в котором строятся условия: join-ы добавляются в него (основной запрос или подзапрос),
 * а параметры — всегда во внешний запрос, потому что подзапрос встраивается в него строкой DQL.
 *
 * @internal
 */
final class QueryScope
{
    /** @var array<string, string> путь связей => алиас join-а */
    private array $joins = [];

    public function __construct(
        private readonly QueryBuilder $builder,
        private readonly string $rootAlias,
        private readonly QueryBuilder $parameters,
    ) {
    }

    /**
     * DQL-выражение поля; недостающие join-ы по пути добавляются один раз на путь.
     */
    public function field(ResolvedProperty $property): string
    {
        $alias = $this->rootAlias;
        $path = '';
        foreach ($property->associations as $association) {
            $path = '' === $path ? $association : $path . '.' . $association;
            if (!isset($this->joins[$path])) {
                $this->joins[$path] = $this->rootAlias . '_j' . (count($this->joins) + 1);
                $this->builder->leftJoin($alias . '.' . $association, $this->joins[$path]);
            }
            $alias = $this->joins[$path];
        }

        return $alias . '.' . $property->field;
    }

    /**
     * @param string|int|float|bool|list<string|int|float|bool> $value
     *
     * @return string плейсхолдер вида `:dsb_3`
     */
    public function bind(string|int|float|bool|array $value): string
    {
        $name = 'dsb_' . (count($this->parameters->getParameters()) + 1);

        if (is_array($value)) {
            $allIntegers = [] === array_filter($value, static fn (mixed $item): bool => !is_int($item));
            $this->parameters->setParameter($name, $value, $allIntegers ? ArrayParameterType::INTEGER : ArrayParameterType::STRING);
        } else {
            $this->parameters->setParameter($name, $value);
        }

        return ':' . $name;
    }
}
