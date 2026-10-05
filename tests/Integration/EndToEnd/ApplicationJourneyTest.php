<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use App\Tests\Shared\Infrastructure\Messenger\ConsumesAsyncMessages;
use App\Tests\Shared\Infrastructure\Security\RecruiterLogin;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The whole journey through the real UI, with a real worker in between:
 *
 *   candidate submits the form → worker screens the CV → recruiter sees the
 *   summary and score in the list and on the detail page
 */
final class ApplicationJourneyTest extends WebTestCase
{
    use ConsumesAsyncMessages;

    public function test_a_candidate_applies_and_the_recruiter_sees_the_ai_enrichment(): void
    {
        $client = self::createClient();
        $offer = JobOfferFactory::createOne(['title' => 'Senior PHP Backend Engineer', 'description' => 'PHP, Symfony, DDD and RabbitMQ.']);

        $client->request('GET', '/jobs/'.$offer->id->value);
        $client->submitForm('Submit application', [
            'apply[fullName]' => 'Jane Doe',
            'apply[email]' => 'jane@example.com',
            'apply[cv]' => 'Backend engineer, 7 years with PHP, Symfony, DDD and RabbitMQ.',
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(ScreeningStatus::Pending, JobApplicationFactory::repository()->findOneBy([])?->screeningStatus);

        // Before the next request: the test client reboots the kernel, and with it the in-memory transport.
        $this->consumeAsyncMessages();

        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Application received');

        RecruiterLogin::as($client);
        $client->clickLink('Open in the recruiter area');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Jane Doe');
        self::assertSelectorTextContains('section[aria-labelledby="ai-title"]', 'Matches 4 of 4 key skills for Senior PHP Backend Engineer');
        self::assertSelectorExists('[role="img"][aria-label="Relevance score 98 out of 100"]'); // 80 × 4/4 + 20 × 7/8
        self::assertSelectorNotExists('[data-controller="poll"]');

        $client->request('GET', '/applications');
        self::assertSelectorTextContains('tbody tr', 'Jane Doe');
        self::assertSelectorTextContains('tbody tr', '98');
    }
}
