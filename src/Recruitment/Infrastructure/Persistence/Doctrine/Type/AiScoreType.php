<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\AiScore;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class AiScoreType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AiScore
    {
        return match (true) {
            null === $value => null,
            \is_int($value) => AiScore::fromInt($value),
            \is_string($value) && ctype_digit($value) => AiScore::fromInt((int) $value),
            default => throw InvalidType::new($value, self::class, ['null', 'int']),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return match (true) {
            null === $value => null,
            $value instanceof AiScore => $value->value,
            default => throw InvalidType::new($value, self::class, ['null', AiScore::class]),
        };
    }
}
