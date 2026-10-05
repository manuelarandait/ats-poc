<?php

declare(strict_types=1);

namespace App\Recruitment\Application\CompleteJobApplicationScreening;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\Shared\Domain\Bus\Event\DomainEventSubscriber;

/**
 * Translates the other context's fact into our own intention. The change then
 * goes through the command bus like any write (transaction, rules, events).
 */
final readonly class CompleteJobApplicationScreeningOnCvScreened implements DomainEventSubscriber
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    public function __invoke(CvScreened $event): void
    {
        $this->commandBus->dispatch(new CompleteJobApplicationScreeningCommand($event->aggregateId, $event->summary, $event->score, $event->skills));
    }
}
