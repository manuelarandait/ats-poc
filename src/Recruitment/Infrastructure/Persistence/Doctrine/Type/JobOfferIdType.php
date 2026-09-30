<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine\Type;

use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * @extends UuidType<JobOfferId>
 */
final class JobOfferIdType extends UuidType
{
    protected function uuidClass(): string
    {
        return JobOfferId::class;
    }
}
