<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * The CV exactly as pasted by the candidate (plain text, no uploads/OCR).
 * Only surrounding whitespace is trimmed: the original layout is kept.
 */
final readonly class CvText
{
    public const int MAX_LENGTH = 20_000;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw InvalidCvText::empty();
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidCvText::tooLong(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }
}
