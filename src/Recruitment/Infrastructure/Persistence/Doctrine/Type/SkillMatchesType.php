<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\SkillMatch;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * The AI's skill-by-skill breakdown as one JSON column. Loading rebuilds every
 * SkillMatch through its named constructor; NULL (screened before the
 * breakdown existed) reads as an empty list.
 */
final class SkillMatchesType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * @return list<SkillMatch>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (null === $value) {
            return [];
        }

        $skills = \is_string($value) ? json_decode($value, true) : null;

        if (!\is_array($skills)) {
            throw ValueNotConvertible::new($value, 'skill matches');
        }

        return array_values(array_map(static function (mixed $skill) use ($value): SkillMatch {
            if (!\is_array($skill) || !\is_string($skill['skill'] ?? null) || !\is_bool($skill['required'] ?? null) || !\is_bool($skill['matched'] ?? null)) {
                throw ValueNotConvertible::new($value, 'skill matches');
            }

            return SkillMatch::create($skill['skill'], $skill['required'], $skill['matched']);
        }, $skills));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['null', 'list<'.SkillMatch::class.'>']);
        }

        return json_encode(array_values(array_map(static function (mixed $skill) use ($value): array {
            if (!$skill instanceof SkillMatch) {
                throw InvalidType::new($value, self::class, ['null', 'list<'.SkillMatch::class.'>']);
            }

            return ['skill' => $skill->skill, 'required' => $skill->required, 'matched' => $skill->matched];
        }, $value)), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
