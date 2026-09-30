<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Form;

use App\Recruitment\Domain\JobApplication\CvText;
use App\Recruitment\Domain\JobApplication\Notes;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the apply form collects. Validated here for friendly field errors; the
 * domain value objects validate again (the domain never trusts the edge).
 */
final class ApplyRequest
{
    // Sequentially: one message per field (an empty name is not also "too short").
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Please enter your full name.'),
        new Assert\Length(min: 2, max: 150),
    ])]
    public string $fullName = '';

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Please enter your email address.'),
        new Assert\Length(max: 254),
        new Assert\Email(message: 'Please enter a valid email address.'),
    ])]
    public string $email = '';

    #[Assert\Length(max: 25)]
    #[Assert\Regex(pattern: '/^\+?[0-9\s().-]*$/', message: 'Use digits, spaces and + ( ) - only.')]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'Please paste your CV as plain text.')]
    #[Assert\Length(max: CvText::MAX_LENGTH)]
    public string $cv = '';

    #[Assert\Length(max: Notes::MAX_LENGTH)]
    public ?string $notes = null;
}
