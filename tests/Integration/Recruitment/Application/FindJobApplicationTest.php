<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Application;

use App\Recruitment\Application\FindJobApplication\FindJobApplicationQuery;
use App\Recruitment\Application\FindJobApplication\JobApplicationDetails;
use App\Recruitment\Application\ListJobOffers\JobOfferView;
use App\Recruitment\Application\ListJobOffers\ListJobOffersQuery;
use App\Recruitment\Domain\JobApplication\CvText;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\Notes;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FindJobApplicationTest extends KernelTestCase
{
    public function test_the_detail_shows_candidate_data_cv_enrichment_status_and_timestamps(): void
    {
        $offer = JobOfferFactory::createOne(['title' => 'Senior PHP Developer']);
        $application = JobApplicationFactory::new()
            ->forOffer($offer)
            ->candidate('Jane Doe', 'jane@example.com', '+34 600 123 456')
            ->with(['cv' => CvText::fromString("Jane Doe\n  PHP, 6 years"), 'notes' => Notes::fromNullable('Available in October')])
            ->appliedAt('2026-09-01 10:00:00')
            ->screened(88, 'Strong PHP profile.')
            ->inStatus(JobApplicationStatus::InReview)
            ->create();

        $details = $this->find($application->id->value);

        self::assertSame('Jane Doe', $details->candidateName);
        self::assertSame('jane@example.com', $details->candidateEmail);
        self::assertSame('+34600123456', $details->candidatePhone);
        self::assertSame('Senior PHP Developer', $details->positionTitle);
        self::assertSame("Jane Doe\n  PHP, 6 years", $details->cv);
        self::assertSame('Available in October', $details->notes);
        self::assertSame('in_review', $details->status);
        self::assertSame(['interviewing', 'rejected'], $details->nextStatuses());
        self::assertSame('completed', $details->screeningStatus);
        self::assertSame('Strong PHP profile.', $details->aiSummary);
        self::assertSame(88, $details->aiScore);
        self::assertEquals(new \DateTimeImmutable('2026-09-01 10:00:00'), $details->appliedAt);
        self::assertEquals(new \DateTimeImmutable('2026-09-01 10:01:00'), $details->screenedAt);
        self::assertEquals(new \DateTimeImmutable('2026-09-02 10:00:00'), $details->updatedAt);
    }

    public function test_a_pending_application_has_no_ai_results_yet(): void
    {
        $application = JobApplicationFactory::createOne();

        $details = $this->find($application->id->value);

        self::assertSame('pending', $details->screeningStatus);
        self::assertNull($details->aiSummary);
        self::assertNull($details->aiScore);
        self::assertNull($details->screenedAt);
    }

    public function test_an_unknown_application_is_not_found(): void
    {
        $this->expectException(JobApplicationNotFound::class);

        $this->find('0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b');
    }

    public function test_job_offers_are_listed_by_title(): void
    {
        JobOfferFactory::createOne(['title' => 'Frontend Engineer']);
        JobOfferFactory::createOne(['title' => 'Backend Engineer']);

        $offers = self::getContainer()->get(QueryBus::class)->ask(new ListJobOffersQuery());

        self::assertSame(['Backend Engineer', 'Frontend Engineer'], array_map(static fn (JobOfferView $offer): string => $offer->title, $offers));
    }

    private function find(string $id): JobApplicationDetails
    {
        return self::getContainer()->get(QueryBus::class)->ask(new FindJobApplicationQuery($id));
    }
}
