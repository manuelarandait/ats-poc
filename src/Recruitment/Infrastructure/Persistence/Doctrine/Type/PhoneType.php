<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\Candidate\Phone;
use App\Shared\Infrastructure\Persistence\Doctrine\StringValueObjectType;

/**
 * @extends StringValueObjectType<Phone>
 */
final class PhoneType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return Phone::class;
    }

    protected function fromString(string $value): object
    {
        return Phone::fromString($value);
    }

    protected function toString(object $valueObject): string
    {
        return $valueObject->value;
    }
}
