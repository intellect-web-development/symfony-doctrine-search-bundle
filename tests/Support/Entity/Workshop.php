<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workshop')]
class Workshop
{
    /** @var Collection<int, WorkshopProblem> */
    #[ORM\OneToMany(targetEntity: WorkshopProblem::class, mappedBy: 'workshop')]
    public Collection $problems;

    /** @var Collection<int, WorkshopValue> */
    #[ORM\OneToMany(targetEntity: WorkshopValue::class, mappedBy: 'workshop')]
    public Collection $values;

    public function __construct(
        #[ORM\Id, ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $title,
        #[ORM\ManyToOne(targetEntity: Publisher::class)]
        public ?Publisher $publisher = null,
    ) {
        $this->problems = new ArrayCollection();
        $this->values = new ArrayCollection();
    }
}
