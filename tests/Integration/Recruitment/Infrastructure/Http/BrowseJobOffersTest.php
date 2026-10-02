<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Http;

use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use App\Tests\Shared\Infrastructure\Security\RecruiterLogin;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Open positions: public to candidates; recruiters also see how many
 * applications each offer has received.
 */
final class BrowseJobOffersTest extends WebTestCase
{
    public function test_candidates_see_the_offers_without_internal_figures(): void
    {
        $client = self::createClient();
        $offer = JobOfferFactory::createOne(['title' => 'PHP Developer', 'description' => "Build our backend.\n\nRequirements: PHP, Symfony."]);
        JobApplicationFactory::new()->forOffer($offer)->create();

        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('ul li h2', 'PHP Developer');
        self::assertSelectorTextContains('ul li p', 'Build our backend.');
        self::assertSelectorTextNotContains('ul li p', 'Requirements', 'Cards show only the pitch.');
        self::assertSelectorNotExists('ul li .badge', 'No applications count for candidates.');
    }

    public function test_recruiters_see_how_many_applications_each_offer_has(): void
    {
        $client = RecruiterLogin::as(self::createClient());
        $offer = JobOfferFactory::createOne(['title' => 'PHP Developer']);
        JobApplicationFactory::new()->forOffer($offer)->many(2)->create();

        $client->request('GET', '/jobs');

        self::assertSelectorTextContains('ul li', '2 applications');
    }
}
