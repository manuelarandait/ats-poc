<?php

declare(strict_types=1);

namespace App\Recruitment\Application\CompleteJobApplicationScreening;

use App\Recruitment\Domain\JobApplication\AiScore;
use App\Recruitment\Domain\JobApplication\AiScreening;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class CompleteJobApplicationScreeningHandler implements CommandHandler
{
    public function __construct(
        private JobApplicationRepository $applications,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CompleteJobApplicationScreeningCommand $command): void
    {
        $id = JobApplicationId::fromString($command->id);
        $application = $this->applications->find($id) ?? throw JobApplicationNotFound::withId($id);

        // Idempotent in the aggregate: a redelivered result is ignored.
        $application->completeScreening(AiScreening::create($command->summary, AiScore::fromInt($command->score)), $this->clock->now());

        $this->applications->save($application);
        $this->eventBus->publish(...$application->pullDomainEvents());
    }
}
