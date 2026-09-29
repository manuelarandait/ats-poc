<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\ChangeJobApplicationStatus;

use App\Recruitment\Application\ChangeJobApplicationStatus\ChangeJobApplicationStatusCommand;
use App\Recruitment\Application\ChangeJobApplicationStatus\ChangeJobApplicationStatusHandler;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationStatusChanged;
use App\Recruitment\Domain\JobApplication\InvalidStatusTransition;
use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\UnknownJobApplicationStatus;
use App\Tests\Recruitment\Domain\JobApplication\JobApplicationMother;
use App\Tests\Recruitment\Infrastructure\InMemoryJobApplicationRepository;
use App\Tests\Shared\Infrastructure\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ChangeJobApplicationStatusHandlerTest extends TestCase
{
    private const string NOW = '2026-09-29 10:00:00';

    private InMemoryJobApplicationRepository $applications;
    private SpyEventBus $eventBus;
    private ChangeJobApplicationStatusHandler $handler;
    private JobApplication $application;

    protected function setUp(): void
    {
        $this->applications = new InMemoryJobApplicationRepository();
        $this->eventBus = new SpyEventBus();
        $this->handler = new ChangeJobApplicationStatusHandler($this->applications, $this->eventBus, new MockClock(self::NOW));

        $this->application = JobApplicationMother::persisted();
        $this->applications->save($this->application);
    }

    public function test_it_moves_the_application_forward_and_publishes_the_change(): void
    {
        ($this->handler)(new ChangeJobApplicationStatusCommand($this->application->id->value, 'in_review'));

        self::assertSame(JobApplicationStatus::InReview, $this->application->status);
        self::assertEquals(
            [new JobApplicationStatusChanged($this->application->id->value, 'received', 'in_review', new \DateTimeImmutable(self::NOW))],
            $this->eventBus->published,
        );
    }

    public function test_it_refuses_a_forbidden_transition(): void
    {
        $this->expectException(InvalidStatusTransition::class);

        ($this->handler)(new ChangeJobApplicationStatusCommand($this->application->id->value, 'hired'));
    }

    public function test_it_refuses_an_unknown_status(): void
    {
        $this->expectException(UnknownJobApplicationStatus::class);

        ($this->handler)(new ChangeJobApplicationStatusCommand($this->application->id->value, 'on_hold'));
    }

    public function test_it_fails_when_the_application_does_not_exist(): void
    {
        $this->expectException(JobApplicationNotFound::class);

        ($this->handler)(new ChangeJobApplicationStatusCommand(JobApplicationMother::id()->value, 'in_review'));
    }
}
