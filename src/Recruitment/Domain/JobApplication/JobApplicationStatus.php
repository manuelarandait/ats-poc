<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

/**
 * Hiring pipeline. Allowed moves:
 *
 *   received → in_review → interviewing → hired
 *        └──────────┴────────────┴──────→ rejected
 *
 * hired and rejected are final.
 */
enum JobApplicationStatus: string
{
    case Received = 'received';
    case InReview = 'in_review';
    case Interviewing = 'interviewing';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public static function initial(): self
    {
        return self::Received;
    }

    /**
     * Like from(), but raises a domain error instead of PHP's \ValueError.
     */
    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw UnknownJobApplicationStatus::fromValue($value);
    }

    public function canTransitionTo(self $next): bool
    {
        return \in_array($next, $this->nextAllowed(), true);
    }

    /**
     * @return list<self>
     */
    public function nextAllowed(): array
    {
        return match ($this) {
            self::Received => [self::InReview, self::Rejected],
            self::InReview => [self::Interviewing, self::Rejected],
            self::Interviewing => [self::Hired, self::Rejected],
            self::Hired, self::Rejected => [],
        };
    }

    public function isFinal(): bool
    {
        return [] === $this->nextAllowed();
    }
}
