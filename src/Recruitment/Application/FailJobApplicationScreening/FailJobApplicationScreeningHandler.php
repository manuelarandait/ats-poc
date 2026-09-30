<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FailJobApplicationScreening;

use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class FailJobApplicationScreeningHandler implements CommandHandler
{
    public function __construct(
        private JobApplicationRepository $applications,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(FailJobApplicationScreeningCommand $command): void
    {
        $id = JobApplicationId::fromString($command->id);
        $application = $this->applications->find($id) ?? throw JobApplicationNotFound::withId($id);

        // A late failure never overrides a completed screening (aggregate rule).
        $application->failScreening($command->reason, $this->clock->now());

        $this->applications->save($application);
        $this->eventBus->publish(...$application->pullDomainEvents());
    }
}
