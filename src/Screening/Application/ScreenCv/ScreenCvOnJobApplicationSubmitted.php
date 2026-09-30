<?php

declare(strict_types=1);

namespace App\Screening\Application\ScreenCv;

use App\Screening\Domain\CvAnalyzer;
use App\Screening\Domain\Event\CvScreened;
use App\Screening\Domain\Position;
use App\Shared\Domain\Bus\Event\DomainEventSubscriber;
use App\Shared\Domain\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * Runs in the worker. If the AI is unavailable the exception bubbles up on
 * purpose: Messenger retries the message, and only when retries are exhausted
 * a CvScreeningFailed event is published (see infrastructure).
 */
final readonly class ScreenCvOnJobApplicationSubmitted implements DomainEventSubscriber
{
    public function __construct(
        private CvAnalyzer $analyzer,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(JobApplicationSubmitted $event): void
    {
        $analysis = $this->analyzer->analyse($event->cv, new Position($event->positionTitle, $event->positionDescription));

        $this->eventBus->publish(new CvScreened($event->aggregateId, $analysis->summary, $analysis->score, $this->clock->now()));
    }
}
