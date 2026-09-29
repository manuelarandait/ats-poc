<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\Candidate\FullName;
use App\Shared\Infrastructure\Persistence\Doctrine\StringValueObjectType;

/**
 * @extends StringValueObjectType<FullName>
 */
final class FullNameType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return FullName::class;
    }

    protected function fromString(string $value): object
    {
        return FullName::fromString($value);
    }

    protected function toString(object $valueObject): string
    {
        return $valueObject->value;
    }
}
