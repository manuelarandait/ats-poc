<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Http;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Infrastructure\Http\JobApplication\ListJobApplicationsController;
use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use App\Tests\Shared\Infrastructure\Security\RecruiterLogin;
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
        $this->client = RecruiterLogin::as(self::createClient());
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

    public function test_status_tabs_count_what_the_search_and_position_filters_cover(): void
    {
        $php = JobOfferFactory::createOne(['title' => 'PHP Developer']);
        JobApplicationFactory::new()->forOffer($php)->many(2)->create();
        JobApplicationFactory::new()->forOffer($php)->inStatus(JobApplicationStatus::InReview)->create();
        JobApplicationFactory::new()->inStatus(JobApplicationStatus::InReview)->create(); // another offer

        $crawler = $this->client->request('GET', '/applications?status=in_review&position='.$php->id->value);

        self::assertSame(
            ['All 3', 'Received 2', 'In review 1', 'Interviewing 0', 'Hired 0', 'Rejected 0'],
            $crawler->filter('fieldset label')->each(static fn (Crawler $tab): string => $tab->text()),
            'The counts ignore the selected status: the tabs are the status.',
        );
        self::assertSame('in_review', $crawler->filter('fieldset input[checked]')->attr('value'));
        // The tabs live inside the results frame but belong to the filters form.
        self::assertSame('applications-filters', $crawler->filter('fieldset input')->attr('form'));
    }

    public function test_the_overview_shows_totals_ongoing_analyses_and_the_average_score(): void
    {
        JobApplicationFactory::new()->screened(80)->create();
        JobApplicationFactory::new()->screened(60)->create();
        JobApplicationFactory::new()->inStatus(JobApplicationStatus::Interviewing)->screened(70)->create();
        JobApplicationFactory::createOne(); // still being analysed

        $crawler = $this->client->request('GET', '/applications');

        $kpis = [];
        $crawler->filter('turbo-frame dl > div')->each(static function (Crawler $kpi) use (&$kpis): void {
            $kpis[$kpi->filter('dt')->text()] = $kpi->filter('dd')->text();
        });
        self::assertSame([
            'Applications' => '4',
            'AI analysing now' => '1',
            'Interviewing' => '1',
            'Average AI score' => '70 / 100',
        ], $kpis);
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

    public function test_the_page_size_can_be_chosen_and_page_links_keep_it_and_the_filters(): void
    {
        JobApplicationFactory::createMany(12);

        $crawler = $this->client->request('GET', '/applications?status=received&perPage=10');

        self::assertCount(10, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('nav[aria-label="Pagination"]', 'Showing 1–10 of 12');
        self::assertSelectorTextContains('nav[aria-label="Pagination"]', 'Page 1 of 2');
        self::assertSame('10', $crawler->filter('select[name="perPage"] option[selected]')->attr('value'));
        // The size select belongs to the filters form, so changing it keeps the filters.
        self::assertSame('applications-filters', $crawler->filter('select[name="perPage"]')->attr('form'));
        self::assertSame('/applications?status=received&perPage=10&page=2', $crawler->filter('a[aria-label="Next page"]')->attr('href'));
    }

    public function test_an_unsupported_page_size_falls_back_to_the_default(): void
    {
        JobApplicationFactory::createOne();

        $crawler = $this->client->request('GET', '/applications?perPage=7');

        self::assertSame('20', $crawler->filter('select[name="perPage"] option[selected]')->attr('value'));
    }

    public function test_long_page_lists_show_first_last_and_neighbours_with_ellipses(): void
    {
        JobApplicationFactory::createMany(60);

        $crawler = $this->client->request('GET', '/applications?perPage=10&page=4');

        $pages = $crawler->filter('nav[aria-label="Pagination"] span.sm\\:flex > *')->each(static fn (Crawler $item): string => trim($item->text()));
        self::assertSame(['1', '…', '3', '4', '5', '6'], $pages);
        self::assertSelectorTextContains('nav[aria-label="Pagination"] [aria-current="page"]', '4');
        self::assertSelectorTextContains('nav[aria-label="Pagination"]', 'Page 4 of 6');
    }

    public function test_a_page_past_the_end_shows_the_last_page(): void
    {
        JobApplicationFactory::createMany(12);

        $crawler = $this->client->request('GET', '/applications?perPage=10&page=9');

        self::assertCount(2, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('nav[aria-label="Pagination"]', 'Page 2 of 2');
    }
}
