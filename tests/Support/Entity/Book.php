<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Book
{
    public function __construct(
        #[ORM\Id, ORM\Column(type: 'guid')]
        public string $id,
        #[ORM\Column(type: 'string')]
        public string $title,
        #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'books')]
        #[ORM\JoinColumn(nullable: false)]
        public Author $author,
        #[ORM\Column(type: 'integer', nullable: true)]
        public ?int $pages = null,
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        public ?DateTimeImmutable $publishedAt = null,
    ) {
        $author->books->add($this);
    }
}
