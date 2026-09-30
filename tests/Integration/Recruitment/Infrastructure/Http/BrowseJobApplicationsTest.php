<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Http;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Infrastructure\Http\JobApplication\ListJobApplicationsController;
use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Applications page: newest first, real-time filters (Turbo Frame), search,
 * score column and polling while the AI is analysing.
 */
final class BrowseJobApplicationsTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function test_the_list_is_newest_first_with_status_and_ai_score(): void
    {
        JobApplicationFactory::new()->candidate('Older Candidate', 'older@example.com')->appliedAt('-2 days')->screened(91)->create();
        JobApplicationFactory::new()->candidate('Newer Candidate', 'newer@example.com')->appliedAt('-1 hour')->inStatus(JobApplicationStatus::InReview)->create();

        $crawler = $this->client->request('GET', '/applications');

        self::assertResponseIsSuccessful();
        self::assertSame(['Newer Candidate', 'Older Candidate'], $this->names($crawler));
        self::assertSelectorTextContains('tbody tr:nth-child(1)', 'In review');
        self::assertSelectorTextContains('tbody tr:nth-child(2)', '91');
    }

    public function test_it_filters_by_status_and_position_and_searches_by_name_or_email(): void
    {
        $php = JobOfferFactory::createOne(['title' => 'PHP Developer']);
        JobApplicationFactory::new()->forOffer($php)->candidate('Jane Match', 'jane@acme.io')->appliedAt('-1 hour')->inStatus(JobApplicationStatus::InReview)->create();
        JobApplicationFactory::new()->forOffer($php)->candidate('Jane Received', 'jane2@example.com')->appliedAt('-2 hours')->create();
        JobApplicationFactory::new()->candidate('John Other', 'john@acme.io')->appliedAt('-3 hours')->inStatus(JobApplicationStatus::InReview)->create();

        self::assertSame(['Jane Match', 'John Other'], $this->names($this->client->request('GET', '/applications?status=in_review')));
        self::assertSame(['Jane Match', 'Jane Received'], $this->names($this->client->request('GET', '/applications?position='.$php->id->value)));
        self::assertSame(['Jane Match', 'John Other'], $this->names($this->client->request('GET', '/applications?q=acme.io')));
        self::assertSame(['Jane Match'], $this->names($this->client->request('GET', '/applications?q=jane&status=in_review&position='.$php->id->value)));
    }

    public function test_live_filtering_only_renders_the_results_frame(): void
    {
        JobApplicationFactory::createOne();

        $this->client->request('GET', '/applications?q=a', server: ['HTTP_TURBO_FRAME' => ListJobApplicationsController::RESULTS_FRAME]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#applications-results');
        self::assertSelectorNotExists('header', 'No layout, no filters form: just the results.');
        self::assertSelectorNotExists('form[role="search"]');
    }

    public function test_it_explains_when_nothing_matches(): void
    {
        JobApplicationFactory::createOne();

        $this->client->request('GET', '/applications?q=nobody-matches-this');

        self::assertSelectorTextContains('turbo-frame', 'No applications match these filters');
    }

    public function test_it_keeps_polling_while_an_analysis_is_pending(): void
    {
        JobApplicationFactory::new()->screened(70)->create();
        JobApplicationFactory::createOne(); // pending

        $this->client->request('GET', '/applications');

        self::assertSelectorTextContains('tbody', 'Analysing…');
        self::assertSelectorExists('turbo-frame#applications-results [data-controller="poll"]');
    }

    public function test_it_stops_polling_once_nothing_is_pending(): void
    {
        JobApplicationFactory::new()->screened(70)->create();

        $this->client->request('GET', '/applications');

        self::assertSelectorNotExists('[data-controller="poll"]');
    }

    public function test_an_invalid_filter_is_a_bad_request(): void
    {
        $this->client->request('GET', '/applications?status=on_hold');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return list<string>
     */
    private function names(Crawler $crawler): array
    {
        return $crawler->filter('tbody tr td:first-child a')->each(static fn (Crawler $link): string => trim($link->text()));
    }
}
