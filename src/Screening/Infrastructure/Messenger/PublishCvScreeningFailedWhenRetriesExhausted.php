<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Messenger;

use App\Screening\Application\ScreenCv\JobApplicationSubmitted;
use App\Screening\Domain\Event\CvScreeningFailed;
use App\Shared\Domain\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Retrying is Messenger's job (transport retry strategy). This listener only
 * acts on the *last* failure of a screening, turning a technical problem into
 * a business fact other contexts can react to, instead of leaving the
 * application "pending" forever. The message itself still lands in the
 * failure transport, so it can be inspected or replayed.
 */
#[AsEventListener]
final readonly class PublishCvScreeningFailedWhenRetriesExhausted
{
    public function __construct(
        private EventBus $eventBus,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if ($event->willRetry() || !$message instanceof JobApplicationSubmitted) {
            return;
        }

        $attempts = RedeliveryStamp::getRetryCountFromEnvelope($event->getEnvelope()) + 1;

        $this->logger->error('CV screening failed after {attempts} attempts', [
            'attempts' => $attempts,
            'jobApplicationId' => $message->aggregateId,
            'exception' => $event->getThrowable(),
        ]);

        $this->eventBus->publish(new CvScreeningFailed(
            $message->aggregateId,
            \sprintf('AI analysis unavailable after %d attempts', $attempts),
            $this->clock->now(),
        ));
    }
}
