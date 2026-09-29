<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * Optional free text from the candidate. Blank input means "no notes".
 */
final readonly class Notes
{
    public const int MAX_LENGTH = 2_000;

    private function __construct(public string $value)
    {
    }

    public static function fromNullable(?string $value): ?self
    {
        $trimmed = trim($value ?? '');

        if ('' === $trimmed) {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidNotes::tooLong(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }
}
