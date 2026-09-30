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

        self::assertSame(3, $this->search(status: '', jobOfferId: ' ', search: '  ')->total);
    }

    public function test_it_paginates_and_reports_the_total(): void
    {
        foreach (range(1, 5) as $day) {
            JobApplicationFactory::new()->candidate("Candidate {$day}", "c{$day}@example.com")->appliedAt("2026-09-0{$day} 10:00")->create();
        }

        $page = $this->search(page: 2, perPage: 2);

        self::assertSame(['Candidate 3', 'Candidate 2'], $this->names($page));
        self::assertSame(5, $page->total);
        self::assertSame(3, $page->pages());
        self::assertTrue($page->hasNextPage());
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

    public function test_an_unknown_status_filter_is_rejected(): void
    {
        $this->expectException(UnknownJobApplicationStatus::class);

        $this->search(status: 'on_hold');
    }

    private function search(?string $status = null, ?string $jobOfferId = null, ?string $search = null, int $page = 1, int $perPage = 20): JobApplicationPage
    {
        return self::getContainer()->get(QueryBus::class)->ask(new SearchJobApplicationsQuery($status, $jobOfferId, $search, $page, $perPage));
    }

    /**
     * @return list<string>
     */
    private function names(JobApplicationPage $page): array
    {
        return array_map(static fn (JobApplicationSummary $item): string => $item->candidateName, $page->items);
    }
}
