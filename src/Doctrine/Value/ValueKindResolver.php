<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Doctrine\Value;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\AsciiStringType;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\EnumType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\PhpDateTimeMappingType;
use Doctrine\DBAL\Types\SmallFloatType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Throwable;

/**
 * Определяет ValueKind поля: по классу DBAL-типа, а для собственных типов приложения — по их SQL-объявлению.
 */
final class ValueKindResolver
{
    /** @var array<string, ValueKind> */
    private array $cache = [];

    /**
     * @param ClassMetadata<object> $metadata
     */
    public function resolve(EntityManagerInterface $em, ClassMetadata $metadata, string $field): ValueKind
    {
        return $this->cache[$metadata->getName() . '::' . $field] ??= $this->detect($em, $metadata, $field);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function detect(EntityManagerInterface $em, ClassMetadata $metadata, string $field): ValueKind
    {
        $typeName = $metadata->getTypeOfField($field);
        if (null === $typeName || !Type::hasType($typeName)) {
            return ValueKind::Other;
        }
        $type = Type::getType($typeName);

        return match (true) {
            $type instanceof IntegerType, $type instanceof SmallIntType, $type instanceof BigIntType,
            $type instanceof DecimalType, $type instanceof FloatType, $type instanceof SmallFloatType => ValueKind::Numeric,
            $type instanceof BooleanType => ValueKind::Boolean,
            $type instanceof PhpDateTimeMappingType => ValueKind::Temporal,
            $type instanceof GuidType => ValueKind::Uuid,
            $type instanceof StringType, $type instanceof TextType,
            $type instanceof AsciiStringType, $type instanceof EnumType => ValueKind::String,
            default => $this->fromDeclaration($type, $metadata, $field, $em->getConnection()->getDatabasePlatform()),
        };
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function fromDeclaration(Type $type, ClassMetadata $metadata, string $field, AbstractPlatform $platform): ValueKind
    {
        $mapping = $metadata->getFieldMapping($field);
        try {
            $declaration = mb_strtolower($type->getSQLDeclaration([
                'length' => $mapping->length,
                'precision' => $mapping->precision ?? 10,
                'scale' => $mapping->scale ?? 0,
                'fixed' => $mapping->options['fixed'] ?? false,
            ], $platform));
        } catch (Throwable) {
            return ValueKind::Other;
        }

        return match (true) {
            str_contains($declaration, 'uuid') => ValueKind::Uuid,
            str_contains($declaration, 'bool') => ValueKind::Boolean,
            1 === preg_match('/int|numeric|decimal|double|real|float/', $declaration) => ValueKind::Numeric,
            1 === preg_match('/timestamp|date|time/', $declaration) => ValueKind::Temporal,
            1 === preg_match('/char|text|clob/', $declaration) => ValueKind::String,
            default => ValueKind::Other,
        };
    }
}
