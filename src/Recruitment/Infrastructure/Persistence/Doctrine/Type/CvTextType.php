<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\CvText;
use App\Shared\Infrastructure\Persistence\Doctrine\StringValueObjectType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @extends StringValueObjectType<CvText>
 */
final class CvTextType extends StringValueObjectType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    protected function valueObjectClass(): string
    {
        return CvText::class;
    }

    protected function fromString(string $value): object
    {
        return CvText::fromString($value);
    }

    protected function toString(object $valueObject): string
    {
        return $valueObject->value;
    }
}
