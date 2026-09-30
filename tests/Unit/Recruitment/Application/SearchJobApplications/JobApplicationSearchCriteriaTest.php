<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\SearchJobApplications;

use App\Recruitment\Application\SearchJobApplications\JobApplicationPage;
use App\Recruitment\Application\SearchJobApplications\JobApplicationSearchCriteria;
use App\Recruitment\Application\SearchJobApplications\SearchJobApplicationsQuery;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\UnknownJobApplicationStatus;
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
        self::assertSame(20, $criteria->offset());
    }

    public function test_blank_filters_mean_any(): void
    {
        $criteria = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(' ', '', "\t"));

        self::assertNull($criteria->status);
        self::assertNull($criteria->jobOfferId);
        self::assertNull($criteria->search);
    }

    public function test_page_and_page_size_are_kept_within_bounds(): void
    {
        $tooLow = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(page: 0, perPage: 0));
        $tooHigh = JobApplicationSearchCriteria::fromQuery(new SearchJobApplicationsQuery(perPage: 1_000));

        self::assertSame(1, $tooLow->page);
        self::assertSame(1, $tooLow->perPage);
        self::assertSame(JobApplicationSearchCriteria::MAX_PER_PAGE, $tooHigh->perPage);
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

    public function test_an_empty_result_still_has_one_page(): void
    {
        $page = new JobApplicationPage([], 0, 1, 20);

        self::assertSame(1, $page->pages());
        self::assertFalse($page->hasNextPage());
    }
}
