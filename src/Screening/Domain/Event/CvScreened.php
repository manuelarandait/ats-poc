<?php

declare(strict_types=1);

namespace App\Screening\Domain\Event;

use App\Shared\Domain\DomainEvent;

/**
 * Public event. The aggregate id is the screened job application's id: it is
 * the only identifier Screening shares with the context that asked for it.
 */
final readonly class CvScreened extends DomainEvent
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

    public function toPrimitives(): array
    {
        return ['summary' => $this->summary, 'score' => $this->score];
    }
}
