<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobOffer;

/**
 * An open position candidates can apply to. The catalog is fixed (seeded), so
 * the offer has no lifecycle of its own yet.
 */
final class JobOffer
{
    private const int TITLE_MAX_LENGTH = 120;

    private function __construct(
        public readonly JobOfferId $id,
        public readonly string $title,
        public readonly string $description,
    ) {
    }

    public static function create(JobOfferId $id, string $title, string $description): self
    {
        $title = trim($title);
        $description = trim($description);

        if ('' === $title || mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw InvalidJobOffer::title($title, self::TITLE_MAX_LENGTH);
        }

        if ('' === $description) {
            throw InvalidJobOffer::emptyDescription();
        }

        return new self($id, $title, $description);
    }
}
