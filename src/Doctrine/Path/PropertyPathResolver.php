<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Path;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Проходит путь `a.b.field` по метаданным Doctrine. Поля embeddable (`price.amount`) распознаются как поля.
 */
final readonly class PropertyPathResolver
{
    /**
     * @param ClassMetadata<object> $root
     * @param int|null              $maxDepth сколько связей можно пройти; null — без ограничения
     */
    public function resolve(EntityManagerInterface $em, ClassMetadata $root, string $path, ?int $maxDepth): ?ResolvedProperty
    {
        $segments = explode('.', $path);
        $metadata = $root;
        $associations = [];

        foreach ($segments as $index => $segment) {
            $rest = implode('.', array_slice($segments, $index));
            if ($metadata->hasField($rest)) {
                return new ResolvedProperty($associations, $rest, $metadata);
            }
            if (!$metadata->hasAssociation($segment) || (null !== $maxDepth && count($associations) >= $maxDepth)) {
                return null;
            }

            $associations[] = $segment;
            $metadata = $em->getClassMetadata($metadata->getAssociationTargetClass($segment));
        }

        return null;
    }
}
