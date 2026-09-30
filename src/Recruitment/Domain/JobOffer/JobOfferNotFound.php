<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobOffer;

use App\Shared\Domain\DomainError;

final class JobOfferNotFound extends DomainError
{
    public static function withId(JobOfferId $id): self
    {
        return new self(\sprintf('Job offer "%s" does not exist.', $id->value));
    }
}
