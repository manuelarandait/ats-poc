<?php

declare(strict_types=1);

namespace App\Tests\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\Candidate\Candidate;
use App\Recruitment\Domain\JobApplication\Candidate\Email;
use App\Recruitment\Domain\JobApplication\Candidate\FullName;
use App\Recruitment\Domain\JobApplication\Candidate\Phone;
use App\Tests\Shared\Domain\MotherCreator;

final class CandidateMother
{
    public static function create(
        ?string $fullName = null,
        ?string $email = null,
        ?string $phone = '+34 600 123 456',
    ): Candidate {
        return new Candidate(
            FullName::fromString($fullName ?? MotherCreator::faker()->name()),
            Email::fromString($email ?? MotherCreator::faker()->unique()->safeEmail()),
            null === $phone ? null : Phone::fromString($phone),
        );
    }
}
