<?php

declare(strict_types=1);

namespace App\Recruitment\Application\CompleteJobApplicationScreening;

use App\Shared\Domain\Bus\Event\DeserializableEvent;
use App\Shared\Domain\Bus\Event\EventPayload;
use App\Shared\Domain\DomainEvent;

/**
 * Recruitment's own view of Screening's "screening.cv_screened" event.
 */
final readonly class CvScreened extends DomainEvent implements DeserializableEvent
{
    public function __construct(
        string $aggregateId,
        public string $summary,
        public int $score,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'screening.cv_screened';
    }

    public static function fromPrimitives(string $aggregateId, EventPayload $payload, \DateTimeImmutable $occurredOn): static
    {
        return new self($aggregateId, $payload->string('summary'), $payload->int('score'), $occurredOn);
    }

    public function toPrimitives(): array
    {
        return ['summary' => $this->summary, 'score' => $this->score];
    }
}
