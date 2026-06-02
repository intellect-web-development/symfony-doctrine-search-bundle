<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'test_entity')]
class TestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    public string $name = '';

    #[ORM\Column(type: 'integer')]
    public int $rating = 0;
}
