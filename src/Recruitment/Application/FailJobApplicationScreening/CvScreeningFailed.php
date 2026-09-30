<?php

declare(strict_types=1);

namespace App\Recruitment\Application\FailJobApplicationScreening;

use App\Shared\Domain\Bus\Event\DeserializableEvent;
use App\Shared\Domain\Bus\Event\EventPayload;
use App\Shared\Domain\DomainEvent;

/**
 * Recruitment's own view of Screening's "screening.cv_screening_failed" event.
 */
final readonly class CvScreeningFailed extends DomainEvent implements DeserializableEvent
{
    public function __construct(
        string $aggregateId,
        public string $reason,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'screening.cv_screening_failed';
    }

    public static function fromPrimitives(string $aggregateId, EventPayload $payload, \DateTimeImmutable $occurredOn): static
    {
        return new self($aggregateId, $payload->string('reason'), $occurredOn);
    }

    public function toPrimitives(): array
    {
        return ['reason' => $this->reason];
    }
}
