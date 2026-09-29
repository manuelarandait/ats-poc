<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\Event\JobApplicationScreened;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationScreeningFailed;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationStatusChanged;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationSubmitted;
use App\Recruitment\Domain\JobApplication\InvalidStatusTransition;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Tests\Recruitment\Domain\JobApplication\JobApplicationMother;
use PHPUnit\Framework\TestCase;

final class JobApplicationTest extends TestCase
{
    private const string LATER = '2026-09-02 12:00:00';

    // --- Submission -----------------------------------------------------------

    public function test_a_submitted_application_is_received_with_its_applied_at_date(): void
    {
        $appliedAt = new \DateTimeImmutable('2026-09-01 10:00:00');

        $application = JobApplicationMother::submitted(appliedAt: $appliedAt);

        self::assertSame(JobApplicationStatus::Received, $application->status);
        self::assertEquals($appliedAt, $application->appliedAt);
        self::assertEquals($appliedAt, $application->updatedAt);
    }

    public function test_a_submitted_application_is_pending_screening_without_ai_results(): void
    {
        $application = JobApplicationMother::submitted();

        self::assertSame(ScreeningStatus::Pending, $application->screeningStatus);
        self::assertNull($application->aiScreening);
        self::assertNull($application->screenedAt);
    }

    public function test_submitting_records_a_job_application_submitted_event(): void
    {
        $id = JobApplicationMother::id();
        $jobOfferId = JobOfferId::fromString('0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b');
        $appliedAt = new \DateTimeImmutable('2026-09-01 10:00:00');

        $application = JobApplicationMother::submitted(id: $id, jobOfferId: $jobOfferId, appliedAt: $appliedAt);

        self::assertEquals(
            [new JobApplicationSubmitted($id->value, $jobOfferId->value, $appliedAt)],
            $application->pullDomainEvents(),
        );
    }

    public function test_pulling_events_empties_the_recorded_events(): void
    {
        $application = JobApplicationMother::submitted();

        $application->pullDomainEvents();

        self::assertSame([], $application->pullDomainEvents());
    }

    // --- AI screening ---------------------------------------------------------

    public function test_completing_the_screening_stores_summary_and_score(): void
    {
        $application = JobApplicationMother::persisted();
        $screening = JobApplicationMother::aiScreening(score: 85);
        $at = new \DateTimeImmutable(self::LATER);

        $application->completeScreening($screening, $at);

        self::assertSame(ScreeningStatus::Completed, $application->screeningStatus);
        self::assertSame($screening, $application->aiScreening);
        self::assertEquals($at, $application->screenedAt);
        self::assertEquals($at, $application->updatedAt);
        self::assertEquals(
            [new JobApplicationScreened($application->id->value, 85, $at)],
            $application->pullDomainEvents(),
        );
    }

    public function test_completing_an_already_completed_screening_is_ignored(): void
    {
        $application = JobApplicationMother::persisted();
        $first = JobApplicationMother::aiScreening(score: 85);
        $application->completeScreening($first, new \DateTimeImmutable(self::LATER));
        $application->pullDomainEvents();

        $application->completeScreening(JobApplicationMother::aiScreening(score: 10), new \DateTimeImmutable('2026-09-03'));

        self::assertSame($first, $application->aiScreening);
        self::assertSame([], $application->pullDomainEvents());
    }

    public function test_a_failed_screening_is_recorded_without_ai_results(): void
    {
        $application = JobApplicationMother::persisted();
        $at = new \DateTimeImmutable(self::LATER);

        $application->failScreening('LLM timeout', $at);

        self::assertSame(ScreeningStatus::Failed, $application->screeningStatus);
        self::assertNull($application->aiScreening);
        self::assertEquals(
            [new JobApplicationScreeningFailed($application->id->value, 'LLM timeout', $at)],
            $application->pullDomainEvents(),
        );
    }

    public function test_a_failed_screening_can_still_be_completed_later(): void
    {
        $application = JobApplicationMother::persisted();
        $application->failScreening('LLM timeout', new \DateTimeImmutable(self::LATER));

        $application->completeScreening(JobApplicationMother::aiScreening(), new \DateTimeImmutable('2026-09-03'));

        self::assertSame(ScreeningStatus::Completed, $application->screeningStatus);
        self::assertNotNull($application->aiScreening);
    }

    public function test_a_late_failure_never_overrides_a_completed_screening(): void
    {
        $application = JobApplicationMother::persisted();
        $application->completeScreening(JobApplicationMother::aiScreening(), new \DateTimeImmutable(self::LATER));
        $application->pullDomainEvents();

        $application->failScreening('duplicated message', new \DateTimeImmutable('2026-09-03'));

        self::assertSame(ScreeningStatus::Completed, $application->screeningStatus);
        self::assertSame([], $application->pullDomainEvents());
    }

    // --- Hiring pipeline ------------------------------------------------------

    public function test_changing_to_an_allowed_status_records_the_transition(): void
    {
        $application = JobApplicationMother::persisted();
        $at = new \DateTimeImmutable(self::LATER);

        $application->changeStatus(JobApplicationStatus::InReview, $at);

        self::assertSame(JobApplicationStatus::InReview, $application->status);
        self::assertEquals($at, $application->updatedAt);
        self::assertEquals(
            [new JobApplicationStatusChanged($application->id->value, 'received', 'in_review', $at)],
            $application->pullDomainEvents(),
        );
    }

    public function test_changing_to_a_forbidden_status_fails_and_keeps_the_current_one(): void
    {
        $application = JobApplicationMother::persisted();

        try {
            $application->changeStatus(JobApplicationStatus::Hired, new \DateTimeImmutable(self::LATER));
            self::fail('Expected an invalid transition.');
        } catch (InvalidStatusTransition) {
            self::assertSame(JobApplicationStatus::Received, $application->status);
            self::assertSame([], $application->pullDomainEvents());
        }
    }

    public function test_changing_to_the_current_status_is_a_no_op(): void
    {
        $application = JobApplicationMother::persisted();

        $application->changeStatus(JobApplicationStatus::Received, new \DateTimeImmutable(self::LATER));

        self::assertSame([], $application->pullDomainEvents());
    }

    public function test_the_hiring_pipeline_is_independent_from_the_screening(): void
    {
        $application = JobApplicationMother::persisted();

        $application->changeStatus(JobApplicationStatus::InReview, new \DateTimeImmutable(self::LATER));

        self::assertSame(ScreeningStatus::Pending, $application->screeningStatus);
    }
}
