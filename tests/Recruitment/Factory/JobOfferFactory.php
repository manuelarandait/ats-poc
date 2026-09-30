<?php

declare(strict_types=1);

namespace App\Tests\Recruitment\Factory;

use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Persisted job offers for integration tests, created through JobOffer::create().
 *
 * @extends PersistentObjectFactory<JobOffer>
 */
final class JobOfferFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return JobOffer::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => JobOfferId::fromString(self::faker()->uuid()),
            'title' => self::faker()->jobTitle(),
            'description' => self::faker()->paragraph(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create'));
    }
}
