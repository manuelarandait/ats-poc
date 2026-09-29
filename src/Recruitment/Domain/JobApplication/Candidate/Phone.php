<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Candidate;

/**
 * Stored normalised (optional leading "+" followed by digits only) so it can be
 * compared and searched regardless of how the candidate formatted it.
 */
final readonly class Phone
{
    private const string ALLOWED_CHARACTERS = '/^\+?[0-9\s().-]+$/';
    private const int MIN_DIGITS = 6;
    private const int MAX_DIGITS = 15; // E.164

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if (1 !== preg_match(self::ALLOWED_CHARACTERS, $trimmed)) {
            throw InvalidPhone::fromValue($value);
        }

        $digits = (string) preg_replace('/\D/', '', $trimmed);
        $digitCount = \strlen($digits);

        if ($digitCount < self::MIN_DIGITS || $digitCount > self::MAX_DIGITS) {
            throw InvalidPhone::fromValue($value);
        }

        return new self((str_starts_with($trimmed, '+') ? '+' : '').$digits);
    }
}
