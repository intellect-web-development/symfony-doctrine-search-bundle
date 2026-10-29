<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Author
{
    /** @var Collection<int, Book> */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author')]
    public Collection $books;

    public function __construct(
        #[ORM\Id, ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $name,
        #[ORM\Column(type: 'boolean')]
        public bool $active,
        #[ORM\ManyToOne(targetEntity: Publisher::class)]
        public ?Publisher $publisher = null,
    ) {
        $this->books = new ArrayCollection();
    }
}
