<?php

declare(strict_types=1);

namespace App\Screening\Domain;

/**
 * The job a CV is screened against, as Screening understands it: just the text
 * describing it. Screening has no notion of Recruitment's JobOffer.
 */
final readonly class Position
{
    public function __construct(
        public string $title,
        public string $description,
    ) {
    }
}
