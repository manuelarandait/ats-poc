<?php

declare(strict_types=1);

namespace App\Recruitment\Application\SubmitJobApplication;

use App\Recruitment\Domain\JobApplication\Candidate\Candidate;
use App\Recruitment\Domain\JobApplication\Candidate\Email;
use App\Recruitment\Domain\JobApplication\Candidate\FullName;
use App\Recruitment\Domain\JobApplication\Candidate\Phone;
use App\Recruitment\Domain\JobApplication\CvText;
use App\Recruitment\Domain\JobApplication\JobApplication;
use App\Recruitment\Domain\JobApplication\JobApplicationId;
use App\Recruitment\Domain\JobApplication\JobApplicationRepository;
use App\Recruitment\Domain\JobApplication\Notes;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Recruitment\Domain\JobOffer\JobOfferNotFound;
use App\Recruitment\Domain\JobOffer\JobOfferRepository;
use App\Shared\Domain\Bus\Command\CommandHandler;
use App\Shared\Domain\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class SubmitJobApplicationHandler implements CommandHandler
{
    public function __construct(
        private JobApplicationRepository $applications,
        private JobOfferRepository $offers,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SubmitJobApplicationCommand $command): void
    {
        $jobOfferId = JobOfferId::fromString($command->jobOfferId);
        $jobOffer = $this->offers->find($jobOfferId) ?? throw JobOfferNotFound::withId($jobOfferId);

        $application = JobApplication::submit(
            JobApplicationId::fromString($command->id),
            $jobOffer,
            new Candidate(
                FullName::fromString($command->fullName),
                Email::fromString($command->email),
                Phone::fromNullable($command->phone),
            ),
            CvText::fromString($command->cv),
            Notes::fromNullable($command->notes),
            $this->clock->now(),
        );

        $this->applications->save($application);
        $this->eventBus->publish(...$application->pullDomainEvents());
    }
}
