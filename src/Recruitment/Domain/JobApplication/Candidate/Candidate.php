<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication\Candidate;

/**
 * The person applying. Email is the mandatory contact channel; phone is optional.
 */
final readonly class Candidate
{
    public function __construct(
        public FullName $fullName,
        public Email $email,
        public ?Phone $phone = null,
    ) {
    }
}
