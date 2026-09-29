<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\JobApplicationStatus as Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JobApplicationStatusTest extends TestCase
{
    public function test_every_application_starts_as_received(): void
    {
        self::assertSame(Status::Received, Status::initial());
    }

    #[DataProvider('allowedTransitions')]
    public function test_it_allows_moving_forward_in_the_pipeline(Status $from, Status $to): void
    {
        self::assertTrue($from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{Status, Status}>
     */
    public static function allowedTransitions(): iterable
    {
        yield 'received → in_review' => [Status::Received, Status::InReview];
        yield 'in_review → interviewing' => [Status::InReview, Status::Interviewing];
        yield 'interviewing → hired' => [Status::Interviewing, Status::Hired];
        yield 'received → rejected' => [Status::Received, Status::Rejected];
        yield 'in_review → rejected' => [Status::InReview, Status::Rejected];
        yield 'interviewing → rejected' => [Status::Interviewing, Status::Rejected];
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_it_forbids_skipping_steps_going_back_or_leaving_a_final_status(Status $from, Status $to): void
    {
        self::assertFalse($from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{Status, Status}>
     */
    public static function forbiddenTransitions(): iterable
    {
        yield 'skip: received → hired' => [Status::Received, Status::Hired];
        yield 'skip: received → interviewing' => [Status::Received, Status::Interviewing];
        yield 'back: interviewing → in_review' => [Status::Interviewing, Status::InReview];
        yield 'final: hired → rejected' => [Status::Hired, Status::Rejected];
        yield 'final: rejected → in_review' => [Status::Rejected, Status::InReview];
    }

    public function test_only_hired_and_rejected_are_final(): void
    {
        $final = array_values(array_filter(Status::cases(), static fn (Status $status): bool => $status->isFinal()));

        self::assertSame([Status::Hired, Status::Rejected], $final);
    }
}
