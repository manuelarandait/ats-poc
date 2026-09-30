<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\Candidate\Candidate;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationScreened;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationScreeningFailed;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationStatusChanged;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationSubmitted;
use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Shared\Domain\AggregateRoot;

/**
 * A candidate's application to a job offer.
 *
 * State is readable from outside (public) but only mutable through behaviour
 * methods (private(set)), so every change goes through the business rules.
 */
final class JobApplication extends AggregateRoot
{
    private function __construct(
        public readonly JobApplicationId $id,
        public readonly JobOfferId $jobOfferId,
        public readonly Candidate $candidate,
        public readonly CvText $cv,
        public readonly ?Notes $notes,
        public readonly \DateTimeImmutable $appliedAt,
        public private(set) JobApplicationStatus $status,
        public private(set) ScreeningStatus $screeningStatus,
        public private(set) ?AiScreening $aiScreening,
        public private(set) ?\DateTimeImmutable $screenedAt,
        public private(set) \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * The offer is passed whole so the event can carry the position applied to
     * (event-carried state transfer): the screening needs no call back to us.
     */
    public static function submit(
        JobApplicationId $id,
        JobOffer $jobOffer,
        Candidate $candidate,
        CvText $cv,
        ?Notes $notes,
        \DateTimeImmutable $appliedAt,
    ): self {
        $application = new self(
            id: $id,
            jobOfferId: $jobOffer->id,
            candidate: $candidate,
            cv: $cv,
            notes: $notes,
            appliedAt: $appliedAt,
            status: JobApplicationStatus::initial(),
            screeningStatus: ScreeningStatus::Pending,
            aiScreening: null,
            screenedAt: null,
            updatedAt: $appliedAt,
        );

        $application->record(new JobApplicationSubmitted(
            $id->value,
            $jobOffer->id->value,
            $jobOffer->title,
            $jobOffer->description,
            $cv->value,
            $appliedAt,
        ));

        return $application;
    }

    /**
     * Idempotent: messages are delivered at least once, so a repeated result
     * for an already screened application is ignored.
     * A previously failed screening can still be completed (e.g. on retry).
     */
    public function completeScreening(AiScreening $screening, \DateTimeImmutable $at): void
    {
        if (ScreeningStatus::Completed === $this->screeningStatus) {
            return;
        }

        $this->aiScreening = $screening;
        $this->screeningStatus = ScreeningStatus::Completed;
        $this->screenedAt = $at;
        $this->updatedAt = $at;

        $this->record(new JobApplicationScreened($this->id->value, $screening->score->value, $at));
    }

    /**
     * Only a pending screening can fail: a late failure never overrides a
     * completed result, and a repeated failure is ignored.
     */
    public function failScreening(string $reason, \DateTimeImmutable $at): void
    {
        if (ScreeningStatus::Pending !== $this->screeningStatus) {
            return;
        }

        $this->screeningStatus = ScreeningStatus::Failed;
        $this->updatedAt = $at;

        $this->record(new JobApplicationScreeningFailed($this->id->value, $reason, $at));
    }

    public function changeStatus(JobApplicationStatus $next, \DateTimeImmutable $at): void
    {
        if ($next === $this->status) {
            return;
        }

        if (!$this->status->canTransitionTo($next)) {
            throw InvalidStatusTransition::between($this->status, $next);
        }

        $previous = $this->status;
        $this->status = $next;
        $this->updatedAt = $at;

        $this->record(new JobApplicationStatusChanged($this->id->value, $previous->value, $next->value, $at));
    }
}
