<?php

declare(strict_types=1);

namespace App\Tests\Recruitment\Domain\JobOffer;

use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Tests\Shared\Domain\MotherCreator;

final class JobOfferMother
{
    public static function create(
        ?JobOfferId $id = null,
        ?string $title = null,
        ?string $description = null,
    ): JobOffer {
        return JobOffer::create(
            $id ?? self::id(),
            $title ?? 'Senior PHP Developer',
            $description ?? 'We are looking for a backend engineer with Symfony, DDD and RabbitMQ experience.',
        );
    }

    public static function id(): JobOfferId
    {
        return JobOfferId::fromString(MotherCreator::faker()->uuid());
    }
}
