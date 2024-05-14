<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Dto\Input;

use IWD\SymfonyDoctrineSearch\Exception\SymfonyDoctrineSearchException;

class Sort
{
    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    private string $field;
    private string $direction;

    public function __construct(string $field, string $direction = self::SORT_DESC)
    {
        $this->field = $field;

        if (!in_array($direction, [self::SORT_ASC, self::SORT_DESC])) {
            throw new SymfonyDoctrineSearchException('Sort direction should be ASC or DESC only', 400);
        }

        $this->direction = $direction;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }
}
