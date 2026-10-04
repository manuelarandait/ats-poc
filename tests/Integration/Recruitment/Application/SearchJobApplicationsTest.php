<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Application;

use App\Recruitment\Application\SearchJobApplications\JobApplicationPage;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSummary;
use App\Recruitment\Application\SearchJobApplications\SearchJobApplicationsQuery;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\UnknownJobApplicationStatus;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Query bus → handler → SQL read model, against PostgreSQL.
 */
final class SearchJobApplicationsTest extends KernelTestCase
{
    public function test_applications_are_listed_newest_first(): void
    {
        JobApplicationFactory::new()->candidate('Middle', 'middle@example.com')->appliedAt('2026-09-10 10:00')->create();
        JobApplicationFactory::new()->candidate('Newest', 'newest@example.com')->appliedAt('2026-09-20 10:00')->create();
        JobApplicationFactory::new()->candidate('Oldest', 'oldest@example.com')->appliedAt('2026-09-01 10:00')->create();

        self::assertSame(['Newest', 'Middle', 'Oldest'], $this->names($this->search()));
    }

    public function test_it_filters_by_status(): void
    {
        JobApplicationFactory::new()->candidate('Received', 'a@example.com')->create();
        JobApplicationFactory::new()->candidate('In review', 'b@example.com')->inStatus(JobApplicationStatus::InReview)->create();
        JobApplicationFactory::new()->candidate('Hired', 'c@example.com')->inStatus(JobApplicationStatus::Hired)->create();

        self::assertSame(['In review'], $this->names($this->search(status: 'in_review')));
    }

    public function test_it_filters_by_position(): void
    {
        $php = JobOfferFactory::createOne(['title' => 'PHP Developer']);
        $data = JobOfferFactory::createOne(['title' => 'Data Engineer']);
        JobApplicationFactory::new()->forOffer($php)->candidate('Php candidate', 'php@example.com')->create();
        JobApplicationFactory::new()->forOffer($data)->candidate('Data candidate', 'data@example.com')->create();

        $page = $this->search(jobOfferId: $data->id->value);

        self::assertSame(['Data candidate'], $this->names($page));
        self::assertSame('Data Engineer', $page->items[0]->positionTitle);
    }

    public function test_it_searches_by_part_of_the_name_ignoring_case(): void
    {
        JobApplicationFactory::new()->candidate('Lucía Fernández', 'lucia@example.com')->create();
        JobApplicationFactory::new()->candidate('Marco Rossi', 'marco@example.com')->create();

        self::assertSame(['Lucía Fernández'], $this->names($this->search(search: 'FERNÁN')));
    }

    public function test_it_searches_by_part_of_the_email(): void
    {
        JobApplicationFactory::new()->candidate('Jane Doe', 'jane.doe@acme.io')->create();
        JobApplicationFactory::new()->candidate('John Roe', 'john@example.com')->create();

        self::assertSame(['Jane Doe'], $this->names($this->search(search: 'acme.io')));
    }

    public function test_wildcard_characters_in_the_search_are_taken_literally(): void
    {
        JobApplicationFactory::new()->candidate('Ann Smith', 'ann_smith@example.com')->create();
        JobApplicationFactory::new()->candidate('Annie Brown', 'annxsmith@example.com')->create();

        self::assertSame(['Ann Smith'], $this->names($this->search(search: 'ann_smith')));
        self::assertSame([], $this->names($this->search(search: '%')));
    }

    public function test_filters_and_search_combine(): void
    {
        $php = JobOfferFactory::createOne();
        JobApplicationFactory::new()->forOffer($php)->candidate('Jane Match', 'jane@example.com')->inStatus(JobApplicationStatus::InReview)->create();
        JobApplicationFactory::new()->forOffer($php)->candidate('Jane Other status', 'jane2@example.com')->create();
        JobApplicationFactory::new()->candidate('Jane Other offer', 'jane3@example.com')->inStatus(JobApplicationStatus::InReview)->create();

        $page = $this->search(status: 'in_review', jobOfferId: $php->id->value, search: 'jane');

        self::assertSame(['Jane Match'], $this->names($page));
    }

    public function test_blank_filters_mean_any(): void
    {
        JobApplicationFactory::createMany(3);

        self::assertSame(3, $this->search(status: '', jobOfferId: ' ', search: '  ')->pagination->total);
    }

    public function test_it_paginates_and_reports_the_total(): void
    {
        foreach (range(1, 5) as $day) {
            JobApplicationFactory::new()->candidate("Candidate {$day}", "c{$day}@example.com")->appliedAt("2026-09-0{$day} 10:00")->create();
        }

        $page = $this->search(page: 2, perPage: 2);

        self::assertSame(['Candidate 3', 'Candidate 2'], $this->names($page));
        self::assertSame(5, $page->pagination->total);
        self::assertSame(3, $page->pagination->pages());
        self::assertTrue($page->pagination->hasNextPage());
    }

    public function test_a_page_past_the_end_returns_the_last_page(): void
    {
        foreach (range(1, 5) as $day) {
            JobApplicationFactory::new()->candidate("Candidate {$day}", "c{$day}@example.com")->appliedAt("2026-09-0{$day} 10:00")->create();
        }

        $page = $this->search(page: 9, perPage: 2);

        self::assertSame(['Candidate 1'], $this->names($page));
        self::assertSame(3, $page->pagination->page);
    }

    public function test_the_list_shows_the_ai_score_once_screened(): void
    {
        JobApplicationFactory::new()->candidate('Screened', 'screened@example.com')->appliedAt('2026-09-02')->screened(84)->create();
        JobApplicationFactory::new()->candidate('Pending', 'pending@example.com')->appliedAt('2026-09-01')->create();

        [$screened, $pending] = $this->search()->items;

        self::assertSame(84, $screened->aiScore);
        self::assertSame('completed', $screened->screeningStatus);
        self::assertNull($pending->aiScore);
        self::assertSame('pending', $pending->screeningStatus);
    }

    public function test_it_sorts_by_candidate_and_by_position_alphabetically(): void
    {
        $zeta = JobOfferFactory::createOne(['title' => 'Zeta role']);
        $alpha = JobOfferFactory::createOne(['title' => 'Alpha role']);
        JobApplicationFactory::new()->forOffer($zeta)->candidate('Bruno', 'b@example.com')->create();
        JobApplicationFactory::new()->forOffer($alpha)->candidate('Carla', 'c@example.com')->create();
        JobApplicationFactory::new()->forOffer($zeta)->candidate('Ana', 'a@example.com')->create();

        self::assertSame(['Ana', 'Bruno', 'Carla'], $this->names($this->search(sort: 'candidate', direction: 'asc')));
        self::assertSame(['Carla', 'Bruno', 'Ana'], $this->names($this->search(sort: 'candidate', direction: 'desc')));
        self::assertSame('Carla', $this->names($this->search(sort: 'position', direction: 'asc'))[0]);
    }

    public function test_the_status_sorts_in_pipeline_order_not_alphabetically(): void
    {
        JobApplicationFactory::new()->candidate('Rejected', 'r@example.com')->inStatus(JobApplicationStatus::Rejected)->create();
        JobApplicationFactory::new()->candidate('Hired', 'h@example.com')->inStatus(JobApplicationStatus::Hired)->create();
        JobApplicationFactory::new()->candidate('Received', 'a@example.com')->create();
        JobApplicationFactory::new()->candidate('In review', 'i@example.com')->inStatus(JobApplicationStatus::InReview)->create();

        self::assertSame(['Received', 'In review', 'Hired', 'Rejected'], $this->names($this->search(sort: 'status', direction: 'asc')));
        self::assertSame(['Rejected', 'Hired', 'In review', 'Received'], $this->names($this->search(sort: 'status', direction: 'desc')));
    }

    public function test_applications_without_a_score_yet_go_last_in_both_directions(): void
    {
        JobApplicationFactory::new()->candidate('Fifty', 'f@example.com')->screened(50)->create();
        JobApplicationFactory::new()->candidate('Pending', 'p@example.com')->create();
        JobApplicationFactory::new()->candidate('Ninety', 'n@example.com')->screened(90)->create();

        self::assertSame(['Ninety', 'Fifty', 'Pending'], $this->names($this->search(sort: 'score', direction: 'desc')));
        self::assertSame(['Fifty', 'Ninety', 'Pending'], $this->names($this->search(sort: 'score', direction: 'asc')));
    }

    public function test_ties_are_broken_newest_first_and_an_unknown_sort_falls_back_to_newest_first(): void
    {
        JobApplicationFactory::new()->candidate('Older', 'o@example.com')->appliedAt('-2 days')->create();
        JobApplicationFactory::new()->candidate('Newer', 'n@example.com')->appliedAt('-1 day')->create();

        self::assertSame(['Newer', 'Older'], $this->names($this->search(sort: 'status', direction: 'asc')), 'Same status: newest first.');
        self::assertSame(['Newer', 'Older'], $this->names($this->search(sort: 'salary', direction: 'sideways')));
    }

    public function test_each_row_counts_the_applications_from_its_email_whatever_the_filters(): void
    {
        $php = JobOfferFactory::createOne();
        JobApplicationFactory::new()->forOffer($php)->candidate('Jane Doe', 'jane@example.com')->create();
        JobApplicationFactory::new()->candidate('Jane Doe', 'jane@example.com')->create();
        JobApplicationFactory::new()->candidate('John Smith', 'john@example.com')->create();

        $counts = [];
        foreach ($this->search(jobOfferId: $php->id->value)->items as $item) {
            $counts[$item->candidateEmail] = $item->applicationsFromEmail;
        }
        self::assertSame(['jane@example.com' => 2], $counts, 'The other application is counted even though the position filter hides it.');
        self::assertSame(1, $this->search(search: 'john')->items[0]->applicationsFromEmail);
    }

    public function test_an_unknown_status_filter_is_rejected(): void
    {
        $this->expectException(UnknownJobApplicationStatus::class);

        $this->search(status: 'on_hold');
    }

    private function search(?string $status = null, ?string $jobOfferId = null, ?string $search = null, int $page = 1, int $perPage = 20, ?string $sort = null, ?string $direction = null): JobApplicationPage
    {
        return self::getContainer()->get(QueryBus::class)->ask(new SearchJobApplicationsQuery($status, $jobOfferId, $search, $page, $perPage, $sort, $direction));
    }

    /**
     * @return list<string>
     */
    private function names(JobApplicationPage $page): array
    {
        return array_map(static fn (JobApplicationSummary $item): string => $item->candidateName, $page->items);
    }
}
