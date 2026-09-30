<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\CompleteJobApplicationScreening;

use App\Recruitment\Application\CompleteJobApplicationScreening\CompleteJobApplicationScreeningCommand;
use App\Recruitment\Application\CompleteJobApplicationScreening\CompleteJobApplicationScreeningHandler;
use App\Recruitment\Application\CompleteJobApplicationScreening\CompleteJobApplicationScreeningOnCvScreened;
use App\Recruitment\Application\CompleteJobApplicationScreening\CvScreened;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationScreened;
use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Tests\Recruitment\Domain\JobApplication\JobApplicationMother;
use App\Tests\Recruitment\Infrastructure\InMemoryJobApplicationRepository;
use App\Tests\Shared\Infrastructure\SpyCommandBus;
use App\Tests\Shared\Infrastructure\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CompleteJobApplicationScreeningTest extends TestCase
{
    private const string NOW = '2026-09-30 10:00:00';

    private InMemoryJobApplicationRepository $applications;
    private SpyEventBus $eventBus;
    private CompleteJobApplicationScreeningHandler $handler;
    private JobApplication $application;

    protected function setUp(): void
    {
        $this->applications = new InMemoryJobApplicationRepository();
        $this->eventBus = new SpyEventBus();
        $this->handler = new CompleteJobApplicationScreeningHandler($this->applications, $this->eventBus, new MockClock(self::NOW));

        $this->application = JobApplicationMother::persisted();
        $this->applications->save($this->application);
    }

    public function test_the_screening_result_from_the_other_context_becomes_our_own_command(): void
    {
        $commandBus = new SpyCommandBus();

        new CompleteJobApplicationScreeningOnCvScreened($commandBus)(new CvScreened('some-id', 'Great fit.', 90, new \DateTimeImmutable(self::NOW)));

        self::assertEquals([new CompleteJobApplicationScreeningCommand('some-id', 'Great fit.', 90)], $commandBus->dispatched);
    }

    public function test_it_attaches_summary_and_score_to_the_application(): void
    {
        ($this->handler)(new CompleteJobApplicationScreeningCommand($this->application->id->value, 'Great fit.', 90));

        self::assertSame(ScreeningStatus::Completed, $this->application->screeningStatus);
        self::assertSame('Great fit.', $this->application->aiScreening?->summary);
        self::assertSame(90, $this->application->aiScreening->score->value);
        self::assertEquals(
            [new JobApplicationScreened($this->application->id->value, 90, new \DateTimeImmutable(self::NOW))],
            $this->eventBus->published,
        );
    }

    public function test_a_redelivered_result_changes_nothing(): void
    {
        ($this->handler)(new CompleteJobApplicationScreeningCommand($this->application->id->value, 'Great fit.', 90));

        ($this->handler)(new CompleteJobApplicationScreeningCommand($this->application->id->value, 'Other result.', 10));

        self::assertSame(90, $this->application->aiScreening?->score->value);
        self::assertCount(1, $this->eventBus->published);
    }

    public function test_it_fails_for_an_unknown_application(): void
    {
        $this->expectException(JobApplicationNotFound::class);

        ($this->handler)(new CompleteJobApplicationScreeningCommand(JobApplicationMother::id()->value, 'Great fit.', 90));
    }
}
