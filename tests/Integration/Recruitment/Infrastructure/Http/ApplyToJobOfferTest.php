<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Http;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Apply page end to end: job description + form → application stored and
 * the AI enrichment queued.
 */
final class ApplyToJobOfferTest extends WebTestCase
{
    private KernelBrowser $client;
    private JobOffer $offer;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->offer = JobOfferFactory::createOne(['title' => 'Senior PHP Developer', 'description' => 'Symfony and DDD.']);
    }

    public function test_the_home_page_leads_to_the_open_positions(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/jobs');

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Open positions');
        self::assertAnySelectorTextContains('li h2', 'Senior PHP Developer');
    }

    public function test_the_job_page_shows_the_description_next_to_the_form(): void
    {
        $this->client->request('GET', '/jobs/'.$this->offer->id->value);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Senior PHP Developer');
        self::assertSelectorTextContains('article', 'Symfony and DDD.');
        self::assertSelectorExists('textarea[name="apply[cv]"]');
    }

    public function test_submitting_stores_a_received_application_and_queues_the_ai_enrichment(): void
    {
        $this->client->request('GET', '/jobs/'.$this->offer->id->value);
        $this->client->submitForm('Submit application', [
            'apply[fullName]' => 'Jane Doe',
            'apply[email]' => 'jane@example.com',
            'apply[phone]' => '',
            'apply[cv]' => "Jane Doe\nBackend engineer, 6 years with PHP.",
            'apply[notes]' => 'Available in October',
        ]);

        self::assertResponseStatusCodeSame(303);
        // Checked before following the redirect: the test client reboots the kernel
        // (and so the in-memory transport) between requests.
        $queued = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $queued);
        self::assertCount(1, $queued->getSent());

        $application = JobApplicationFactory::repository()->findOneBy([]);
        self::assertNotNull($application);
        self::assertSame('Jane Doe', $application->candidate->fullName->value);
        self::assertSame(JobApplicationStatus::Received, $application->status);
        self::assertSame(ScreeningStatus::Pending, $application->screeningStatus);

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Application received');
    }

    public function test_too_many_applications_from_one_ip_are_refused_until_the_window_passes(): void
    {
        // Same kernel for every request, so the limiter keeps its in-memory counters.
        $this->client->disableReboot();

        for ($i = 1; $i <= 5; ++$i) {
            $this->apply(\sprintf('candidate%d@example.com', $i));
            self::assertResponseStatusCodeSame(303);
        }

        $this->apply('candidate6@example.com');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertSelectorTextContains('[role="alert"]', 'Too many applications from your network. Please try again in');
        self::assertSame('candidate6@example.com', $this->client->getCrawler()->filter('input[name="apply[email]"]')->attr('value'), 'What the candidate typed is kept.');
        self::assertCount(5, JobApplicationFactory::repository()->findAll());
    }

    public function test_invalid_submissions_do_not_use_up_the_limit(): void
    {
        $this->client->disableReboot();

        for ($i = 1; $i <= 6; ++$i) {
            $this->client->request('GET', '/jobs/'.$this->offer->id->value);
            $this->client->submitForm('Submit application', ['apply[fullName]' => '', 'apply[email]' => 'jane@example.com', 'apply[cv]' => 'PHP']);
            self::assertResponseStatusCodeSame(422);
        }

        $this->apply('jane@example.com');
        self::assertResponseStatusCodeSame(303);
    }

    public function test_invalid_data_shows_field_errors_and_stores_nothing(): void
    {
        $this->client->request('GET', '/jobs/'.$this->offer->id->value);
        $this->client->submitForm('Submit application', [
            'apply[fullName]' => '',
            'apply[email]' => 'not-an-email',
            'apply[cv]' => '   ',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Please enter your full name.');
        self::assertSelectorTextContains('form', 'Please enter a valid email address.');
        self::assertSelectorTextContains('form', 'Please paste your CV as plain text.');
        JobApplicationFactory::assert()->empty();
    }

    public function test_a_rule_only_the_domain_knows_is_shown_on_its_field(): void
    {
        $this->client->request('GET', '/jobs/'.$this->offer->id->value);
        $this->client->submitForm('Submit application', [
            'apply[fullName]' => 'Jane Doe',
            'apply[email]' => 'jane@example.com',
            'apply[phone]' => '123', // allowed characters, but too few digits for the Phone value object
            'apply[cv]' => 'PHP developer.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', '"123" is not a valid phone number.');
        JobApplicationFactory::assert()->empty();
    }

    public function test_an_unknown_job_offer_is_not_found(): void
    {
        $this->client->request('GET', '/jobs/0192f5a0-0000-7000-8000-00000000dead');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/jobs/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    private function apply(string $email): void
    {
        $this->client->request('GET', '/jobs/'.$this->offer->id->value);
        $this->client->submitForm('Submit application', [
            'apply[fullName]' => 'Jane Doe',
            'apply[email]' => $email,
            'apply[cv]' => 'Backend engineer, 6 years with PHP.',
        ]);
    }
}
