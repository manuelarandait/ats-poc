<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Candidate;

final readonly class FullName
{
    private const int MIN_LENGTH = 2;
    private const int MAX_LENGTH = 150;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = (string) preg_replace('/\s+/u', ' ', trim($value));
        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw InvalidFullName::length($value, self::MIN_LENGTH, self::MAX_LENGTH);
        }

        return new self($normalized);
    }
}
