<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FailJobApplicationScreening;

use App\Shared\Domain\Bus\Command\CommandBus;
use App\Shared\Domain\Bus\Event\DomainEventSubscriber;

final readonly class FailJobApplicationScreeningOnCvScreeningFailed implements DomainEventSubscriber
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    public function __invoke(CvScreeningFailed $event): void
    {
        $this->commandBus->dispatch(new FailJobApplicationScreeningCommand($event->aggregateId, $event->reason));
    }
}
