<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobApplication\Candidate\Email;
use App\Shared\Infrastructure\Persistence\Doctrine\StringValueObjectType;

/**
 * @extends StringValueObjectType<Email>
 */
final class EmailType extends StringValueObjectType
{
    protected function valueObjectClass(): string
    {
        return Email::class;
    }

    protected function fromString(string $value): object
    {
        return Email::fromString($value);
    }

    protected function toString(object $valueObject): string
    {
        return $valueObject->value;
    }
}
