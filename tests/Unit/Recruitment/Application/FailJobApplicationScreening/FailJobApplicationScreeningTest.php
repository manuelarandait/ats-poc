<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\FailJobApplicationScreening;

use App\Recruitment\Application\FailJobApplicationScreening\CvScreeningFailed;
use App\Recruitment\Application\FailJobApplicationScreening\FailJobApplicationScreeningCommand;
use App\Recruitment\Application\FailJobApplicationScreening\FailJobApplicationScreeningHandler;
use App\Recruitment\Application\FailJobApplicationScreening\FailJobApplicationScreeningOnCvScreeningFailed;
use App\Recruitment\Domain\JobApplication\Event\JobApplicationScreeningFailed;
use App\Recruitment\Domain\JobApplication\ScreeningStatus;
use App\Tests\Recruitment\Domain\JobApplication\JobApplicationMother;
use App\Tests\Recruitment\Infrastructure\InMemoryJobApplicationRepository;
use App\Tests\Shared\Infrastructure\SpyCommandBus;
use App\Tests\Shared\Infrastructure\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class FailJobApplicationScreeningTest extends TestCase
{
    private const string NOW = '2026-09-30 10:00:00';

    public function test_the_failure_from_the_other_context_becomes_our_own_command(): void
    {
        $commandBus = new SpyCommandBus();

        new FailJobApplicationScreeningOnCvScreeningFailed($commandBus)(new CvScreeningFailed('some-id', 'LLM down', new \DateTimeImmutable(self::NOW)));

        self::assertEquals([new FailJobApplicationScreeningCommand('some-id', 'LLM down')], $commandBus->dispatched);
    }

    public function test_it_marks_the_screening_as_failed(): void
    {
        $applications = new InMemoryJobApplicationRepository();
        $eventBus = new SpyEventBus();
        $application = JobApplicationMother::persisted();
        $applications->save($application);

        new FailJobApplicationScreeningHandler($applications, $eventBus, new MockClock(self::NOW))(
            new FailJobApplicationScreeningCommand($application->id->value, 'LLM down'),
        );

        self::assertSame(ScreeningStatus::Failed, $application->screeningStatus);
        self::assertEquals(
            [new JobApplicationScreeningFailed($application->id->value, 'LLM down', new \DateTimeImmutable(self::NOW))],
            $eventBus->published,
        );
    }
}
