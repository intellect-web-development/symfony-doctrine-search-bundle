<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

final class SqlCollector extends AbstractLogger
{
    /** @var list<string> */
    public array $queries = [];

    private int $from = 0;

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $sql = $context['sql'] ?? null;
        if (is_string($sql)) {
            $this->queries[] = $sql;

            return;
        }

        $this->queries[] = (string) $message;
    }

    public function mark(): void
    {
        $this->from = count($this->queries);
    }

    /**
     * Ни один запрос с отметки не соединяет обе таблицы, и to-one есть в запросе без них.
     */
    public function keepsCollectionsApart(string $leftTable, string $rightTable, string $toOne): bool
    {
        $sawToOne = false;
        foreach (array_slice($this->queries, $this->from) as $query) {
            $left = str_contains($query, $leftTable);
            $right = str_contains($query, $rightTable);
            if ($left && $right) {
                return false;
            }
            if (!$left && !$right && str_contains(strtolower($query), strtolower($toOne))) {
                $sawToOne = true;
            }
        }

        return $sawToOne;
    }
}
