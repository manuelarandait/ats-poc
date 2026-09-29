<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\Notes;
use App\Shared\Infrastructure\Persistence\Doctrine\StringValueObjectType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @extends StringValueObjectType<Notes>
 */
final class NotesType extends StringValueObjectType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    protected function valueObjectClass(): string
    {
        return Notes::class;
    }

    protected function fromString(string $value): object
    {
        // Blank notes are stored as NULL, so a stored value is never blank.
        return Notes::fromNullable($value) ?? throw new \UnexpectedValueException('Blank notes found in the database.');
    }

    protected function toString(object $valueObject): string
    {
        return $valueObject->value;
    }
}
