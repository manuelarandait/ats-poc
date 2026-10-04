<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\SearchJobApplications;

use App\Recruitment\Application\SearchJobApplications\JobApplicationPage;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSearchCriteria;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSort;
use App\Recruitment\Application\SearchJobApplications\SearchJobApplicationsQuery;
use App\Recruitment\Application\SearchJobApplications\SortDirection;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\UnknownJobApplicationStatus;
use App\Shared\Domain\Pagination\Pagination;
use App\Shared\Domain\ValueObject\InvalidUuid;
use PHPUnit\Framework\TestCase;

final class JobApplicationSearchCriteriaTest extends TestCase
{
    public function test_raw_filters_become_typed_criteria(): void
    {
        $criteria = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery('in_review', '0192f5a0-0000-7000-8000-000000000001', '  jane  ', 3, 10));

        self::assertSame(JobApplicationStatus::InReview, $criteria->status);
        self::assertSame('0192f5a0-0000-7000-8000-000000000001', $criteria->jobOfferId?->value);
        self::assertSame('jane', $criteria->search);
        self::assertSame(3, $criteria->pageRequest->page);
        self::assertSame(10, $criteria->pageRequest->perPage);
    }

    public function test_blank_filters_mean_any(): void
    {
        $criteria = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(' ', '', "\t"));

        self::assertNull($criteria->status);
        self::assertNull($criteria->jobOfferId);
        self::assertNull($criteria->search);
    }

    public function test_moving_to_another_page_keeps_the_filters_and_the_order(): void
    {
        $criteria = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery('hired', search: 'jane', page: 9, sort: 'score'))->onPage(2);

        self::assertSame(2, $criteria->pageRequest->page);
        self::assertSame(JobApplicationStatus::Hired, $criteria->status);
        self::assertSame('jane', $criteria->search);
        self::assertSame(JobApplicationSort::Score, $criteria->sort);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->expectException(UnknownJobApplicationStatus::class);

        JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(status: 'on_hold'));
    }

    public function test_a_malformed_position_id_is_rejected(): void
    {
        $this->expectException(InvalidUuid::class);

        JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(jobOfferId: 'php'));
    }

    public function test_newest_first_is_the_default_order(): void
    {
        $criteria = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery());

        self::assertSame(JobApplicationSort::AppliedAt, $criteria->sort);
        self::assertSame(SortDirection::Desc, $criteria->direction);
    }

    public function test_a_column_without_a_direction_starts_in_its_natural_direction(): void
    {
        self::assertSame(SortDirection::Asc, JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(sort: 'candidate'))->direction);
        self::assertSame(SortDirection::Desc, JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(sort: 'score'))->direction);
        self::assertSame(SortDirection::Asc, JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(sort: 'score', direction: 'asc'))->direction);
    }

    public function test_an_unknown_sort_or_direction_falls_back_instead_of_reaching_the_query(): void
    {
        $criteria = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(sort: 'candidate_email; DROP TABLE', direction: 'up'));

        self::assertSame(JobApplicationSort::AppliedAt, $criteria->sort);
        self::assertSame(SortDirection::Desc, $criteria->direction);
    }

    public function test_clicking_the_sorted_column_flips_it_and_another_column_starts_in_its_natural_direction(): void
    {
        $page = new JobApplicationPage([], new Pagination(0, 1, 20), JobApplicationSort::Score, SortDirection::Desc);

        self::assertSame(SortDirection::Asc, $page->nextDirection('score'));
        self::assertSame(SortDirection::Asc, $page->nextDirection('candidate'));
        self::assertSame(SortDirection::Desc, $page->nextDirection('applied'));
        self::assertFalse($page->isDefaultSort());
        self::assertTrue($page->isDefaultSortFor('applied', 'desc'));
    }
}
