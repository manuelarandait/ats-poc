<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobOffer;

use App\Recruitment\Domain\JobOffer\InvalidJobOffer;
use App\Tests\Recruitment\Domain\JobOffer\JobOfferMother;
use PHPUnit\Framework\TestCase;

final class JobOfferTest extends TestCase
{
    public function test_it_creates_an_offer_with_trimmed_title_and_description(): void
    {
        $offer = JobOfferMother::create(title: '  Senior PHP Developer ', description: ' Symfony + DDD ');

        self::assertSame('Senior PHP Developer', $offer->title);
        self::assertSame('Symfony + DDD', $offer->description);
    }

    public function test_it_rejects_an_empty_title(): void
    {
        $this->expectException(InvalidJobOffer::class);

        JobOfferMother::create(title: '  ');
    }

    public function test_it_rejects_a_too_long_title(): void
    {
        $this->expectException(InvalidJobOffer::class);

        JobOfferMother::create(title: str_repeat('a', 121));
    }

    public function test_it_rejects_an_empty_description(): void
    {
        $this->expectException(InvalidJobOffer::class);

        JobOfferMother::create(description: '');
    }
}
