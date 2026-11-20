<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workshop_value')]
class WorkshopValue
{
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $title,
        #[ORM\ManyToOne(targetEntity: Workshop::class, inversedBy: 'values')]
        #[ORM\JoinColumn(nullable: false)]
        public Workshop $workshop,
    ) {
        $workshop->values->add($this);
    }
}
