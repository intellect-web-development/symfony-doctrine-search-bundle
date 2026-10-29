<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Publisher
{
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {
    }
}
