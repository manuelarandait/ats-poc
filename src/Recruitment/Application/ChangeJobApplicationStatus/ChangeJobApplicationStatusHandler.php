<?php

declare(strict_types=1);

namespace App\Recruitment\Application\ChangeJobApplicationStatus;

use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class ChangeJobApplicationStatusHandler implements CommandHandler
{
    public function __construct(
        private JobApplicationRepository $applications,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeJobApplicationStatusCommand $command): void
    {
        $id = JobApplicationId::fromString($command->id);
        $application = $this->applications->find($id) ?? throw JobApplicationNotFound::withId($id);

        $application->changeStatus(JobApplicationStatus::fromValue($command->status), $this->clock->now());

        $this->applications->save($application);
        $this->eventBus->publish(...$application->pullDomainEvents());
    }
}
