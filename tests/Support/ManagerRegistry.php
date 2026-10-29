<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ObjectManager;

final class ManagerRegistry extends AbstractManagerRegistry
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct('test', ['default' => 'connection'], ['default' => 'em'], 'default', 'default', ObjectManager::class);
    }

    protected function getService(string $name): object
    {
        return 'connection' === $name ? $this->em->getConnection() : $this->em;
    }

    protected function resetService(string $name): void
    {
    }
}
