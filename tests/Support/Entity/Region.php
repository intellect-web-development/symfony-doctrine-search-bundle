<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support\Entity;

use Doctrine\ORM\Mapping as ORM;
use IWD\SymfonyDoctrineSearch\Tests\Support\RegionRepository;

/**
 * Сущность с собственным репозиторием: ограничение, проходящее через неё, раньше терялось молча.
 */
#[ORM\Entity(repositoryClass: RegionRepository::class)]
class Region
{
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $name,
        #[ORM\ManyToOne(targetEntity: Country::class)]
        #[ORM\JoinColumn(referencedColumnName: 'code')]
        public ?Country $country = null,
    ) {
    }
}
