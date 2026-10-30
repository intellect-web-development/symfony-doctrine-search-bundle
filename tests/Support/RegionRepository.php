<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Tests\Support;

use Doctrine\ORM\EntityRepository;
use IWD\SymfonyDoctrineSearch\Tests\Support\Entity\Region;

/**
 * @extends EntityRepository<Region>
 */
final class RegionRepository extends EntityRepository
{
}
